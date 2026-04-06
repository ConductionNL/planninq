<?php

/**
 * Planix Health Controller
 *
 * Public health check endpoint for load balancer probes and monitoring.
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
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
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Public health check endpoint for load balancer probes and monitoring.
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */
class HealthController extends Controller
{
    /**
     * Constructor for the HealthController.
     *
     * @param IRequest    $request    The request object
     * @param IAppManager $appManager The Nextcloud app manager
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private IAppManager $appManager,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Return application health status as JSON.
     *
     * Returns HTTP 200 when healthy (OpenRegister available) or HTTP 503 when
     * OpenRegister is unavailable. The response always includes `status` and
     * `openRegisterAvailable` fields.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        // NOTE: isEnabledForUser() (not isInstalled()) is used intentionally here.
        // SettingsService::isOpenRegisterAvailable() uses isInstalled(), which is true
        // even when an app is installed but disabled. A health probe must reflect whether
        // the dependency is operational (i.e. enabled), not merely present on the filesystem.
        $openRegisterAvailable = $this->appManager->isEnabledForUser('openregister');

        if ($openRegisterAvailable === true) {
            return new JSONResponse(
                [
                    'status'                => 'ok',
                    'openRegisterAvailable' => true,
                ],
                Http::STATUS_OK
            );
        }

        return new JSONResponse(
            [
                'status'                => 'degraded',
                'openRegisterAvailable' => false,
            ],
            Http::STATUS_SERVICE_UNAVAILABLE
        );
    }//end index()
}//end class
