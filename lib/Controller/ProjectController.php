<?php

/**
 * Planix Project Controller
 *
 * Controller for project creation with server-side policy enforcement.
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

/**
 * Controller for project operations with policy enforcement.
 *
 * Provides a server-side enforcement point for the `allow_project_creation`
 * admin setting. The frontend enforces this client-side via `canCreateProject`,
 * but a motivated user could bypass that by calling the OR API directly.
 * This controller closes the gap (closes #H2).
 */
class ProjectController extends Controller
{

    /**
     * Constructor for the ProjectController.
     *
     * @param IRequest        $request         The request object
     * @param SettingsService $settingsService The settings service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
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

}//end class
