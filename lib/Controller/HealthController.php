<?php

/**
 * Planix Health Controller
 *
 * Provides a public health check endpoint for load balancers and monitoring.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Health check controller for Planix.
 *
 * Returns application health status as JSON for use by load balancers,
 * monitoring tools, and Prometheus health probes (ADR-015).
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */
class HealthController extends Controller
{
    /**
     * Constructor for the HealthController.
     *
     * @param IRequest        $request         The request object
     * @param SettingsService $settingsService The settings service
     * @param IAppManager     $appManager      The app manager
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
        private IAppManager $appManager,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Return the health status of the Planix application.
     *
     * Reports whether the app is running and whether its OpenRegister
     * dependency is available. Returns HTTP 200 when healthy, HTTP 503
     * when OpenRegister is unavailable.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        $openRegisterAvailable = $this->settingsService->isOpenRegisterAvailable();

        if ($openRegisterAvailable === true) {
            $status = 'ok';
            $code   = Http::STATUS_OK;
        } else {
            $status = 'degraded';
            $code   = Http::STATUS_SERVICE_UNAVAILABLE;
        }

        return new JSONResponse(
            [
                'status'                => $status,
                'version'               => $this->appManager->getAppVersion(appId: Application::APP_ID),
                'openRegisterAvailable' => $openRegisterAvailable,
            ],
            $code
        );
    }//end index()
}//end class
