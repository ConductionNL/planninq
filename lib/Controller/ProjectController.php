<?php

/**
 * Planninq Project Controller
 *
 * Controller for project creation with server-side policy enforcement,
 * and project leave with RBAC bypass for non-owner members.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\ProjectRoles;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for project operations with policy enforcement.
 *
 * ## C1 — Project creation proxy
 * The SPA posts new projects to this endpoint instead of OR's generic
 * `/api/objects/planninq/project` write path. That path segment, and the
 * `register: 'planninq'` arguments below, are the OpenRegister register SLUG,
 * not the app id. They moved from `planix` to `planninq` together with the
 * MigrateRegisterSlug repair step, which renames the register ROW — OR resolves
 * a register by slug and by nothing else, so the literal and the row have to
 * move in the same release or neither resolves. Server-side policy check runs
 * BEFORE any object is persisted, closing the TOCTOU gap where a motivated
 * user could bypass `allow_project_creation` by calling OR directly.
 * The saveObject call uses `_rbac: false` so that OR's schema-level
 * `"create": ["admin"]` defence-in-depth rule does not block the proxy; the
 * proxy IS the authoritative gate here.
 *
 * ## C3 — Leave-project proxy
 * Non-owner members cannot update the project (OR RBAC `match: { owner: "$userId"}`
 * blocks them). This endpoint server-validates that the caller is a member,
 * then performs the update with `_rbac: false` so OR honours the write without
 * the RBAC match check. The owner-only delete/edit rules are deliberately left
 * untouched — only "removing myself from members" is unlocked here.
 *
 * ## WF2 — Owner-leave ownership handoff
 * When the leaving user is the project owner, ownership is transferred to the
 * next remaining member (sorted alphabetically for determinism). If no other
 * member exists, the leave is refused (the "last member" guard fires first).
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
 */
class ProjectController extends Controller {

	/**
	 * The fields a create takes from the request body; everything else is set by the server.
	 *
	 * @var array<int,string>
	 */
	private const CREATE_FIELDS = [
		'title',
		'description',
		'requestReason',
		'color',
		'icon',
		'key',
		'startDate',
		'endDate',
		'caseReference',
		'client',
		'labels',
		'customFields',
		'autoSchedule',
		'billable',
		'billingModel',
		'budgetHours',
		'budgetAmount',
		'hourlyRate',
	];

	/**
	 * The links a create may carry, with the register and schema they point at.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const LINK_TARGETS = [
		'caseReference' => ['dossiq', 'case'],
		'client'        => ['pipelinq', 'client'],
	];

	/**
	 * FQCN of the OR NotAuthorizedException, resolved at runtime.
	 *
	 * @var string
	 */
	private const OR_NOT_AUTHORIZED_EXCEPTION = 'OCA\\OpenRegister\\Exception\\NotAuthorizedException';

	/**
	 * FQCN of the OR ValidationException, resolved at runtime.
	 *
	 * @var string
	 */
	private const OR_VALIDATION_EXCEPTION = 'OCA\\OpenRegister\\Exception\\ValidationException';

	/**
	 * FQCN of the OR CustomValidationException, resolved at runtime.
	 *
	 * @var string
	 */
	private const OR_CUSTOM_VALIDATION_EXCEPTION = 'OCA\\OpenRegister\\Exception\\CustomValidationException';

	/**
	 * FQCN of the OR ProviderUnavailableException, resolved at runtime.
	 *
	 * @var string
	 */
	private const OR_PROVIDER_UNAVAILABLE_EXCEPTION = 'OCA\\OpenRegister\\Exception\\ProviderUnavailableException';

	/**
	 * Constructor for the ProjectController.
	 *
	 * @param IRequest $request The request object
	 * @param SettingsService $settingsService The settings service
	 * @param IUserSession $userSession The user session
	 * @param ContainerInterface $container The DI container
	 * @param LoggerInterface $logger The logger
	 * @param BoardColumnService $boardColumns Creates a new project's default columns
	 * @param WorkItemKeyService $keys The project key rules (tasks-readable-keys).
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private SettingsService $settingsService,
		private IUserSession $userSession,
		private ContainerInterface $container,
		private LoggerInterface $logger,
		private BoardColumnService $boardColumns,
		private WorkItemKeyService $keys,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * Classify a Throwable from ObjectService into a structured HTTP response.
	 *
	 * OR exceptions are matched by class name at runtime to avoid a hard
	 * compile-time dependency on the openregister package.
	 *
	 * @param \Throwable $e The exception to classify.
	 * @param string $context Short label used in the error log (e.g. "project creation").
	 * @param array $logCtx Additional key-value pairs to include in the log entry.
	 *
	 * @return JSONResponse
	 */
	private function classifyObjectServiceException(\Throwable $e, string $context, array $logCtx = []): JSONResponse {
		$class = get_class($e);

		if ($class === self::OR_NOT_AUTHORIZED_EXCEPTION) {
			$this->logger->warning(
				"Planninq: {$context} permission denied",
				array_merge($logCtx, ['exception' => $e->getMessage()])
			);
			return new JSONResponse(
				['error' => 'Permission denied.', 'detail' => $e->getMessage()],
				Http::STATUS_FORBIDDEN
			);
		}

		if ($class === self::OR_VALIDATION_EXCEPTION || $class === self::OR_CUSTOM_VALIDATION_EXCEPTION) {
			$this->logger->warning(
				"Planninq: {$context} validation failed",
				array_merge($logCtx, ['exception' => $e->getMessage()])
			);
			return new JSONResponse(
				['error' => 'Validation failed.', 'detail' => $e->getMessage()],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if ($class === self::OR_PROVIDER_UNAVAILABLE_EXCEPTION) {
			$this->logger->error(
				"Planninq: {$context} upstream unavailable",
				array_merge($logCtx, ['exception' => $e->getMessage()])
			);
			return new JSONResponse(
				['error' => 'Upstream data provider is unavailable.', 'detail' => $e->getMessage()],
				Http::STATUS_BAD_GATEWAY
			);
		}

		$this->logger->error(
			"Planninq: {$context} unexpected error",
			array_merge($logCtx, ['exception' => $e->getMessage(), 'class' => $class])
		);
		return new JSONResponse(
			['error' => "Failed to complete {$context}."],
			Http::STATUS_INTERNAL_SERVER_ERROR
		);

	}//end classifyObjectServiceException()

	/**
	 * Verify that the current user is allowed to create a project.
	 *
	 * The frontend delegates the actual save to OpenRegister via the object
	 * store. Before doing so it calls this endpoint; 403 stops the create flow.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse 200 when allowed; 403 when the policy forbids creation.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
	 */
	public function checkCreatePolicy(): JSONResponse {
		if ($this->settingsService->canCurrentUserCreateProject() === false) {
			return new JSONResponse(
				['error' => 'Project creation is restricted to administrators.'],
				Http::STATUS_FORBIDDEN
			);
		}

		return new JSONResponse(['allowed' => true]);
	}//end checkCreatePolicy()

	/**
	 * Create a new project with server-side policy enforcement (C1 fix, SB1 fix).
	 *
	 * Replaces the SPA's direct POST to OR's generic object API.
	 * Enforces `allow_project_creation` before persisting anything;
	 * then proxies to ObjectService::saveObject with `_rbac: false` so that
	 * OR's schema-level `"create": ["admin"]` defence-in-depth lock does NOT
	 * block legitimate non-admin creates that passed the policy check above.
	 * The proxy IS the authoritative gate; the schema rule is defence-in-depth
	 * for direct API calls that bypass this controller.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse 201 with the created project; 401 if unauthenticated;
	 *                      403 if policy forbids it; 422 on validation failure;
	 *                      502 if upstream unavailable; 503 if OR unavailable.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
	 */
	public function create(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		// Server-side enforcement of the allow_project_creation admin setting (C1).
		// Delegate to the dedicated policy-check endpoint so the gate logic stays in one place.
		// Someone who may not create may still REQUEST a project when requests
		// are on (projects-lifecycle-policy): it is stored as `requested`.
		$policyCheck = $this->checkCreatePolicy();
		$requesting  = ($policyCheck->getStatus() === Http::STATUS_FORBIDDEN);
		if ($requesting === true && $this->settingsService->canCurrentUserRequestProject() === false) {
			return $policyCheck;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$uid  = $user->getUID();
		$body = $this->createBody(uid: $uid, requesting: $requesting);
		if ($body instanceof JSONResponse) {
			return $body;
		}

		$body = $this->withReadableLinks(body: $body, objectService: $objectService);

		try {
			// SB1 fix: pass _rbac: false so that the schema-level "create": ["admin"]
			// defence-in-depth rule does not block non-admin users who have already
			// passed the policy gate above. Without this flag, OR's checkPermission
			// evaluates the schema rule against the calling user and throws
			// NotAuthorizedException for every non-admin, regardless of the
			// allow_project_creation setting.
			$saved = $objectService->saveObject(
				object: $body,
				register: 'planninq',
				schema: 'project',
				_rbac: false
			);

			$this->logger->info('Planninq: project created', ['uid' => $uid]);

			$project = $saved->jsonSerialize();

			// The board runs on the project's own columns, so a project is
			// created with them: the admin's default titles, the last one the
			// done column (boards-configurable-columns, decision 6).
			// A request gets its columns when it is approved (ProjectReviewListener).
			if ($requesting === false) {
				$this->boardColumns->createDefaultColumns(projectId: (string)($project['id'] ?? ($project['@self']['id'] ?? '')));
			}

			return new JSONResponse($project, Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			return $this->classifyObjectServiceException(
				e: $e,
				context: 'project creation',
				logCtx: ['uid' => $uid]
			);
		}//end try

	}//end create()

	/**
	 * The body a create saves: routing params stripped, the key normalised and
	 * checked, owner and members set by the server, a request marked as one.
	 *
	 * @param string $uid        The caller.
	 * @param bool   $requesting Whether the caller may only request.
	 *
	 * @return array<string,mixed>|JSONResponse The body, or the refusal of its key.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	private function createBody(string $uid, bool $requesting): array|JSONResponse {
		// Only the fields a person fills in: role lists, status, review,
		// handover record and computed fields are the server's.
		$body = array_intersect_key($this->request->getParams(), array_flip(self::CREATE_FIELDS));

		// The project key (tasks-readable-keys): stored uppercase, refused
		// here with a clear status when it is malformed or taken. The key
		// listener holds every other project write to the same rules.
		$body['key'] = $this->keys->normalise(key: ($body['key'] ?? null));
		if ($body['key'] === '') {
			unset($body['key']);
		}

		$keyRefusal = $this->checkKey(key: ($body['key'] ?? ''));
		if ($keyRefusal !== null) {
			return $keyRefusal;
		}

		// Ensure owner + initial membership are set server-side so the client
		// cannot spoof a different owner.
		$body['owner']   = $uid;
		$body['members'] = [$uid];
		$body['status']  = 'active';
		if ($requesting === true) {
			return $this->asRequest(body: $body, uid: $uid);
		}

		return $body;
	}//end createBody()

	/**
	 * The create body without a case or client link the caller cannot read.
	 *
	 * The links arrive from a URL, so a caller could name any case or client
	 * and the project would show it as its own. A link is kept only when the
	 * caller can read the object in its app; otherwise the project is created
	 * without it.
	 *
	 * @param array<string,mixed> $body          The create body.
	 * @param object              $objectService OpenRegister's ObjectService.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-2.1
	 */
	private function withReadableLinks(array $body, object $objectService): array {
		foreach (self::LINK_TARGETS as $field => [$register, $schema]) {
			$id = $body[$field] ?? null;
			if (is_string($id) === false || $id === '') {
				unset($body[$field]);
				continue;
			}

			try {
				$readable = ($objectService->find(id: $id, register: $register, schema: $schema) !== null);
			} catch (\Throwable $e) {
				$readable = false;
			}

			if ($readable === false) {
				$this->logger->info('Planninq: dropped a project link the caller cannot read', ['field' => $field]);
				unset($body[$field]);
			}
		}

		return $body;
	}//end withReadableLinks()

	/**
	 * A create body turned into a project request: status requested, the requester its only member, no review filled in.
	 *
	 * @param array<string,mixed> $body The create body.
	 * @param string              $uid  The requester.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	private function asRequest(array $body, string $uid): array {
		unset($body['reviewedBy'], $body['reviewedAt'], $body['reviewNote']);
		$body['status']  = 'requested';
		$body['members'] = [$uid];

		return $body;
	}//end asRequest()

	/**
	 * Whether a project key is well formed and free, without saying which project has it.
	 *
	 * @param string $key The key to check.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse {valid, available}; 401 when not logged in.
	 *
	 * @no-admin-idor-exempt Takes no object id and returns no object: only whether a key
	 *                       string is free, which the New project dialog needs before any
	 *                       project exists.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function keyAvailable(string $key = ''): JSONResponse {
		if ($this->userSession->getUser() === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$key   = $this->keys->normalise(key: $key);
		$valid = $this->keys->isValidFormat(key: $key);

		return new JSONResponse(['valid' => $valid, 'available' => ($valid === true && $this->keys->isTaken(key: $key) === false)]);
	}//end keyAvailable()

	/**
	 * The refusal for a malformed or taken key on create, or null.
	 *
	 * @param string $key The normalised key, empty for none.
	 *
	 * @return JSONResponse|null
	 */
	private function checkKey(string $key): ?JSONResponse {
		if ($key === '') {
			return null;
		}

		if ($this->keys->isValidFormat(key: $key) === false) {
			return new JSONResponse(
				['error' => 'A key has 2 to 10 letters and digits and starts with a letter.', 'code' => 'planninq-project-key-format'],
				Http::STATUS_BAD_REQUEST
			);
		}

		if ($this->keys->isTaken(key: $key) === true) {
			return new JSONResponse(
				['error' => 'This key is already used by another project.', 'code' => 'planninq-project-key-used'],
				Http::STATUS_CONFLICT
			);
		}

		return null;
	}//end checkKey()

	/**
	 * Allow a non-owner member to leave a project (C3 fix, WF2 fix).
	 *
	 * Normal OR RBAC blocks non-owner members from updating the project object
	 * (the update rule requires `match: { owner: "$userId" }`). This endpoint:
	 *   1. Validates the caller is authenticated.
	 *   2. Fetches the project and verifies the caller is in `members`.
	 *   3. Refuses to leave if the caller is the last remaining member.
	 *   4. If the caller is the project owner, transfers ownership to the
	 *      alphabetically-first remaining member (WF2 fix). This ensures the
	 *      project always has an owner who is also a member. Alphabetical sort
	 *      is deterministic and requires no extra input from the leaving user.
	 *   5. Performs the update with `_rbac: false` (OR's explicit server-trust
	 *      escape hatch) so only the members (and optionally owner) field is mutated.
	 *
	 * The update does NOT change any fields other than members and (when
	 * ownership transfers) owner.
	 *
	 * @param string $projectId The OR UUID of the project to leave.
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse 200 with updated project; 401 if unauthenticated;
	 *                      403/404/422 on guard failures; 502 if upstream
	 *                      unavailable; 503 if OR unavailable.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
	 */
	public function leaveProject(string $projectId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		return $this->leaveAs(leave: ['uid' => $user->getUID(), 'projectId' => $projectId]);

	}//end leaveProject()

	/**
	 * Take the caller off a project they are on, as the system.
	 *
	 * @param array{uid:string,projectId:string} $leave The caller and the project.
	 *
	 * @return JSONResponse The saved project, or the refusal or error.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function leaveAs(array $leave): JSONResponse {
		$objectService = $this->projectObjectService();
		if ($objectService instanceof JSONResponse) {
			return $objectService;
		}

		$project = $this->memberProject(objectService: $objectService, leave: $leave);
		if ($project instanceof JSONResponse) {
			return $project;
		}

		$updated = $this->withoutCaller(project: $project, leave: $leave);
		if ($updated instanceof JSONResponse) {
			return $updated;
		}

		return $this->saveLeave(objectService: $objectService, leave: $leave + ['updated' => $updated]);
	}//end leaveAs()

	/**
	 * OpenRegister's ObjectService set to planninq projects, or 503 when OpenRegister is missing.
	 *
	 * @return object|JSONResponse
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function projectObjectService(): object {
		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$objectService->setRegister('planninq');
		$objectService->setSchema('project');
		return $objectService;
	}//end projectObjectService()

	/**
	 * The project, read with RBAC on, when the caller is on it in any role.
	 *
	 * @param object                             $objectService OpenRegister's ObjectService.
	 * @param array{uid:string,projectId:string} $leave         The caller and the project.
	 *
	 * @return array<string,mixed>|JSONResponse The project, or 404 or 403.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function memberProject(object $objectService, array $leave): array|JSONResponse {
		// Fetch with _rbac=true so the visibility check still applies;
		// the caller must be a member to even read the project.
		$entity = $objectService->find(id: $leave['projectId']);
		if ($entity === null) {
			return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
		}

		$project = $entity->getObject();

		// Guard: the caller must be on the project, in any role.
		if ((new ProjectRoles())->holdsUser(project: $project, uid: $leave['uid']) === false) {
			return new JSONResponse(['error' => 'You are not a member of this project.'], Http::STATUS_FORBIDDEN);
		}

		return $project;
	}//end memberProject()

	/**
	 * The project without the caller, unless that leaves it without writers.
	 *
	 * Off every user list; an owner who leaves hands over to a manager first,
	 * else a member (projects-members-and-roles task 5.2), so the project is
	 * never owner-less.
	 *
	 * @param array<string,mixed>                $project The project.
	 * @param array{uid:string,projectId:string} $leave   The caller and the project.
	 *
	 * @return array<string,mixed>|JSONResponse The updated project, or 422.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function withoutCaller(array $project, array $leave): array|JSONResponse {
		$roles   = new ProjectRoles();
		$updated = $roles->withoutUser(project: $project, uid: $leave['uid']);

		// Guard: refuse to orphan the project.
		if ($roles->hasWriters(project: $updated) === false) {
			$error = ['error' => 'Cannot leave a project with no remaining members. Delete the project instead.'];
			return new JSONResponse($error, Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if (($project['owner'] ?? '') === $leave['uid']) {
			$this->logger->info(
				'Planninq: ownership transferred on owner leave',
				['fromUid' => $leave['uid'], 'toUid' => ($updated['owner'] ?? ''), 'projectId' => $leave['projectId']]
			);
		}

		return $updated;
	}//end withoutCaller()

	/**
	 * Save the project without the caller, as an explicit server-trusted write.
	 *
	 * @param object              $objectService OpenRegister's ObjectService.
	 * @param array<string,mixed> $leave         The caller, the project id and the updated project.
	 *
	 * @return JSONResponse The saved project, or the classified error.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function saveLeave(object $objectService, array $leave): JSONResponse {
		$logCtx = ['uid' => $leave['uid'], 'projectId' => $leave['projectId']];

		try {
			// _rbac: false is the OR-approved escape hatch for explicit server-trusted writes.
			// We've already validated membership above; the write is intentional and scoped.
			$saved = $objectService->saveObject(
				object: $leave['updated'],
				register: 'planninq',
				schema: 'project',
				uuid: $leave['projectId'],
				_rbac: false
			);

			return $this->leftProject(saved: $saved, logCtx: $logCtx);
		} catch (\Throwable $e) {
			return $this->classifyObjectServiceException(e: $e, context: 'leave project', logCtx: $logCtx);
		}
	}//end saveLeave()

	/**
	 * Log that the caller left, and answer with the saved project.
	 *
	 * @param object              $saved  The saved project entity.
	 * @param array<string,mixed> $logCtx The caller and the project id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	private function leftProject(object $saved, array $logCtx): JSONResponse {
		$this->logger->info('Planninq: user left project', $logCtx);

		return new JSONResponse($saved->jsonSerialize());
	}//end leftProject()
}//end class
