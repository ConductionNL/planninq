<?php

/**
 * Planix Health Controller
 *
 * Provides a public health check endpoint for load balancers and monitoring.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * Copyright (C) 2026 Conduction B.V.
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
 * Controller for the health check endpoint.
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
     * Return the health status of the Planix application.
     *
     * Returns HTTP 200 when healthy (OpenRegister available),
     * HTTP 503 when OpenRegister is unavailable.
     *
     * @PublicPage
     * @NoCSRFRequired
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        $hasRegister = $this->appManager->isInstalled('openregister');
        $version     = $this->appManager->getAppVersion(Application::APP_ID);

        $status     = 'degraded';
        $httpStatus = Http::STATUS_SERVICE_UNAVAILABLE;

        if ($hasRegister === true) {
            $status     = 'ok';
            $httpStatus = Http::STATUS_OK;
        }

        return new JSONResponse(
            [
                'status'                => $status,
                'version'               => $version,
                'openRegisterAvailable' => $hasRegister,
            ],
            $httpStatus
        );
    }//end index()
}//end class
