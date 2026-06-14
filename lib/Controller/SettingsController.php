<?php

/**
 * Planix Settings Controller
 *
 * Controller for managing Planix application settings.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
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

/**
 * Controller for managing Planix application settings.
 */
class SettingsController extends Controller
{
    /**
     * Constructor for the SettingsController.
     *
     * @param IRequest        $request         The request object
     * @param SettingsService $settingsService The settings service
     * @param IUserSession    $userSession     The user session
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
        private IUserSession $userSession,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Retrieve all current settings.
     *
     * Returns app configuration including an isAdmin flag consumed by
     * the frontend. Any authenticated user may read settings.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function index(): JSONResponse
    {
        if ($this->userSession->getUser() === null) {
            return new JSONResponse(['error' => 'Not authenticated.'], Http::STATUS_UNAUTHORIZED);
        }

        return new JSONResponse(
            $this->settingsService->getSettings()
        );
    }//end index()

    /**
     * Update settings with provided data. Only admin users may write settings.
     *
     * Admin access is enforced by both the NC admin middleware (no @NoAdminRequired)
     * and the explicit isCurrentUserAdmin() body check (defence-in-depth).
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
     */
    public function create(): JSONResponse
    {
        if ($this->settingsService->isCurrentUserAdmin() === false) {
            return new JSONResponse(
                ['error' => 'Admin privileges required to modify settings.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $data   = $this->request->getParams();
        $config = $this->settingsService->updateSettings($data);

        return new JSONResponse(
            [
                'success' => true,
                'config'  => $config,
            ]
        );
    }//end create()

    /**
     * Update the current user's personal settings (notification toggles).
     *
     * Available to any authenticated user for their own per-user preferences
     * (stored via OCP\IConfig + written through to the OpenRegister notification
     * override). Distinct from create(), which is admin-only IAppConfig.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
     */
    public function updateUser(): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated.'], Http::STATUS_UNAUTHORIZED);
        }

        $data   = $this->request->getParams();
        $config = $this->settingsService->updateUserSettings($user->getUID(), $data);

        return new JSONResponse(
            [
                'success' => true,
                'config'  => $config,
            ]
        );
    }//end updateUser()

    /**
     * Re-import the configuration from planix_register.json.
     *
     * Forces a fresh import regardless of version, auto-configuring
     * all schema and register IDs from the import result.
     * Only admin users may trigger this operation.
     *
     * Admin access is enforced by both the NC admin middleware (no @NoAdminRequired)
     * and the explicit isCurrentUserAdmin() body check (defence-in-depth).
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
     */
    public function load(): JSONResponse
    {
        if ($this->settingsService->isCurrentUserAdmin() === false) {
            return new JSONResponse(
                ['error' => 'Admin privileges required to trigger configuration import.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $result = $this->settingsService->loadConfiguration(force: true);

        return new JSONResponse($result);
    }//end load()
}//end class
