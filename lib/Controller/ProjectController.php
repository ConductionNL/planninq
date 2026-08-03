<?php

/**
 * Planix Project Controller
 *
 * Controller for project creation with server-side policy enforcement,
 * and project leave with RBAC bypass for non-owner members.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
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

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\SettingsService;
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
 * `/api/objects/planix/project` write path. Server-side policy check runs
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
class ProjectController extends Controller
{

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
     * @param IRequest           $request         The request object
     * @param SettingsService    $settingsService The settings service
     * @param IUserSession       $userSession     The user session
     * @param ContainerInterface $container       The DI container
     * @param LoggerInterface    $logger          The logger
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
        private IUserSession $userSession,
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);

    }//end __construct()

    /**
     * Classify a Throwable from ObjectService into a structured HTTP response.
     *
     * OR exceptions are matched by class name at runtime to avoid a hard
     * compile-time dependency on the openregister package.
     *
     * @param \Throwable $e       The exception to classify.
     * @param string     $context Short label used in the error log (e.g. "project creation").
     * @param array      $logCtx  Additional key-value pairs to include in the log entry.
     *
     * @return JSONResponse
     */
    private function classifyObjectServiceException(\Throwable $e, string $context, array $logCtx=[]): JSONResponse
    {
        $class = get_class($e);

        if ($class === self::OR_NOT_AUTHORIZED_EXCEPTION) {
            $this->logger->warning(
                "Planix: {$context} permission denied",
                array_merge($logCtx, ['exception' => $e->getMessage()])
            );
            return new JSONResponse(
                ['error' => 'Permission denied.', 'detail' => $e->getMessage()],
                Http::STATUS_FORBIDDEN
            );
        }

        if ($class === self::OR_VALIDATION_EXCEPTION || $class === self::OR_CUSTOM_VALIDATION_EXCEPTION) {
            $this->logger->warning(
                "Planix: {$context} validation failed",
                array_merge($logCtx, ['exception' => $e->getMessage()])
            );
            return new JSONResponse(
                ['error' => 'Validation failed.', 'detail' => $e->getMessage()],
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }

        if ($class === self::OR_PROVIDER_UNAVAILABLE_EXCEPTION) {
            $this->logger->error(
                "Planix: {$context} upstream unavailable",
                array_merge($logCtx, ['exception' => $e->getMessage()])
            );
            return new JSONResponse(
                ['error' => 'Upstream data provider is unavailable.', 'detail' => $e->getMessage()],
                Http::STATUS_BAD_GATEWAY
            );
        }

        $this->logger->error(
            "Planix: {$context} unexpected error",
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
    public function checkCreatePolicy(): JSONResponse
    {
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
    public function create(): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        // Server-side enforcement of the allow_project_creation admin setting (C1).
        // Delegate to the dedicated policy-check endpoint so the gate logic stays in one place.
        $policyCheck = $this->checkCreatePolicy();
        if ($policyCheck->getStatus() === Http::STATUS_FORBIDDEN) {
            return $policyCheck;
        }

        try {
            $objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
            return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
        }

        $uid  = $user->getUID();
        $body = $this->request->getParams();

        // Strip framework-injected routing params.
        unset($body['_route'], $body['_format']);

        // Ensure owner + initial membership are set server-side so the client
        // cannot spoof a different owner.
        $body['owner']   = $uid;
        $body['members'] = array_values(array_unique(array_merge([$uid], (array) ($body['members'] ?? []))));
        $body['status']  = ($body['status'] ?? 'active');

        try {
            // SB1 fix: pass _rbac: false so that the schema-level "create": ["admin"]
            // defence-in-depth rule does not block non-admin users who have already
            // passed the policy gate above. Without this flag, OR's checkPermission
            // evaluates the schema rule against the calling user and throws
            // NotAuthorizedException for every non-admin, regardless of the
            // allow_project_creation setting.
            $saved = $objectService->saveObject(
                object: $body,
                register: 'planix',
                schema: 'project',
                _rbac: false
            );

            $this->logger->info('Planix: project created', ['uid' => $uid]);

            return new JSONResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            return $this->classifyObjectServiceException(
                e: $e,
                context: 'project creation',
                logCtx: ['uid' => $uid]
            );
        }//end try

    }//end create()

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
    public function leaveProject(string $projectId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        $uid = $user->getUID();

        try {
            $objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
            return new JSONResponse(['error' => 'OpenRegister is not available.'], Http::STATUS_SERVICE_UNAVAILABLE);
        }

        $objectService->setRegister('planix');
        $objectService->setSchema('project');

        // Fetch with _rbac=true so the visibility check still applies;
        // the caller must be a member to even read the project.
        $entity = $objectService->find(id: $projectId);
        if ($entity === null) {
            return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
        }

        $project = $entity->getObject();
        $members = (array) ($project['members'] ?? []);

        // Guard: caller must be a member.
        if (in_array($uid, $members, strict: true) === false) {
            return new JSONResponse(
                ['error' => 'You are not a member of this project.'],
                Http::STATUS_FORBIDDEN
            );
        }

        // Guard: refuse to orphan the project.
        $remainingMembers = array_values(array_filter($members, static fn($member) => $member !== $uid));
        if (count($remainingMembers) === 0) {
            return new JSONResponse(
                ['error' => 'Cannot leave a project with no remaining members. Delete the project instead.'],
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }

        // WF2 fix: when the owner leaves, transfer ownership to the alphabetically
        // first remaining member so the project is never in an owner-less state.
        // Alphabetical sort is deterministic and requires no extra user input.
        $updated            = $project;
        $updated['members'] = $remainingMembers;

        $currentOwner = ($project['owner'] ?? '');
        if ($currentOwner === $uid) {
            $candidateMembers = $remainingMembers;
            sort($candidateMembers);
            $newOwner         = $candidateMembers[0];
            $updated['owner'] = $newOwner;
            $this->logger->info(
                'Planix: ownership transferred on owner leave',
                ['fromUid' => $uid, 'toUid' => $newOwner, 'projectId' => $projectId]
            );
        }

        try {
            // _rbac: false is the OR-approved escape hatch for explicit server-trusted writes.
            // We've already validated membership above; the write is intentional and scoped.
            $saved = $objectService->saveObject(
                object: $updated,
                register: 'planix',
                schema: 'project',
                uuid: $projectId,
                _rbac: false
            );

            $this->logger->info(
                'Planix: user left project',
                ['uid' => $uid, 'projectId' => $projectId]
            );

            return new JSONResponse($saved->jsonSerialize());
        } catch (\Throwable $e) {
            return $this->classifyObjectServiceException(
                e: $e,
                context: 'leave project',
                logCtx: ['uid' => $uid, 'projectId' => $projectId]
            );
        }//end try

    }//end leaveProject()
}//end class
