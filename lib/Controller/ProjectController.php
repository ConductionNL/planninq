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
 *
 * ## C3 — Leave-project proxy
 * Non-owner members cannot update the project (OR RBAC `match: { owner: "$userId"}`
 * blocks them). This endpoint server-validates that the caller is a member,
 * then performs the update with `_rbac: false` so OR honours the write without
 * the RBAC match check. The owner-only delete/edit rules are deliberately left
 * untouched — only "removing myself from members" is unlocked here.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
 */
class ProjectController extends Controller
{
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
     * Create a new project with server-side policy enforcement (C1 fix).
     *
     * Replaces the SPA's direct POST to OR's generic object API.
     * Enforces `allow_project_creation` before persisting anything;
     * then proxies to ObjectService::saveObject so that the create
     * happens with the caller's identity and OR's normal CREATE rules
     * (e.g. `create: ["authenticated"]` still applies at the schema layer,
     * but the policy check happens HERE first).
     *
     * @NoAdminRequired
     *
     * @return JSONResponse 201 with the created project; 403 if policy forbids it;
     *                      503 if OpenRegister is unavailable.
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
        if ($this->settingsService->canCurrentUserCreateProject() === false) {
            return new JSONResponse(
                ['error' => 'Project creation is restricted to administrators.'],
                Http::STATUS_FORBIDDEN
            );
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
            $saved = $objectService->saveObject(
                object: $body,
                register: 'planix',
                schema: 'project'
            );

            return new JSONResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('Planix: project creation failed', ['exception' => $e->getMessage(), 'uid' => $uid]);
            return new JSONResponse(['error' => 'Failed to create project.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

    }//end create()

    /**
     * Allow a non-owner member to leave a project (C3 fix).
     *
     * Normal OR RBAC blocks non-owner members from updating the project object
     * (the update rule requires `match: { owner: "$userId" }`). This endpoint:
     *   1. Validates the caller is authenticated.
     *   2. Fetches the project and verifies the caller is in `members`.
     *   3. Performs the update with `_rbac: false` (OR's explicit server-trust
     *      escape hatch) so only the members array is mutated.
     *   4. Refuses to leave if the caller is the last remaining member.
     *
     * The update does NOT change ownership, title, or any other field.
     *
     * @param string $projectId The OR UUID of the project to leave.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse 200 with updated project; 403/404/422 on guard failures;
     *                      503 if OpenRegister is unavailable.
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
        $remainingMembers = array_values(array_filter($members, static fn($m) => $m !== $uid));
        if (count($remainingMembers) === 0) {
            return new JSONResponse(
                ['error' => 'Cannot leave a project with no remaining members.'],
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }

        // Merge only the members change; all other fields stay as-is.
        $updated            = $project;
        $updated['members'] = $remainingMembers;

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
            $this->logger->error(
                'Planix: leave-project update failed',
                ['exception' => $e->getMessage(), 'uid' => $uid, 'projectId' => $projectId]
            );
            return new JSONResponse(['error' => 'Failed to leave project.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }//end try

    }//end leaveProject()
}//end class
