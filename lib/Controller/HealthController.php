<?php

/**
 * Planix Health Controller
 *
 * Controller for the health check endpoint used by load balancers
 * and monitoring systems.
 *
 * @spec     openspec/changes/status-api/tasks.md#task-1
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

// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Health check controller for load balancer and monitoring use.
 *
 * Returns the application status, version, and OpenRegister availability.
 * This endpoint is public (no authentication required).
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */
class HealthController extends Controller
{
    /**
     * Constructor for the HealthController.
     *
     * @param IRequest    $request    The request object
     * @param IAppManager $appManager The app manager for checking installed apps
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
     * Return application health status.
     *
     * Returns HTTP 200 with status "ok" when OpenRegister is available,
     * or HTTP 503 with status "degraded" when OpenRegister is unavailable.
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
        $openRegisterAvailable = $this->appManager->isInstalled('openregister');
        $version = $this->getAppVersion();

        if ($openRegisterAvailable === true) {
            return new JSONResponse(
                [
                    'status'                => 'ok',
                    'version'               => $version,
                    'openRegisterAvailable' => true,
                ],
                Http::STATUS_OK
            );
        }

        return new JSONResponse(
            [
                'status'                => 'degraded',
                'version'               => $version,
                'openRegisterAvailable' => false,
            ],
            Http::STATUS_SERVICE_UNAVAILABLE
        );
    }//end index()

    /**
     * Read the app version from appinfo/info.xml.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return string The application version or "unknown" on failure
     */
    private function getAppVersion(): string
    {
        $infoPath = dirname(__DIR__, 2).'/appinfo/info.xml';
        if (file_exists($infoPath) === false) {
            return 'unknown';
        }

        $xml = simplexml_load_file($infoPath);
        if ($xml === false || isset($xml->version) === false) {
            return 'unknown';
        }

        return (string) $xml->version;
    }//end getAppVersion()
}//end class
