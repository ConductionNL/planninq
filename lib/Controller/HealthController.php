<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * Copyright (C) 2026 Conduction B.V.
 *
 * Planix Health Controller
 *
 * Public health check endpoint for load balancers and monitoring.
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
 * Public health check endpoint for load balancers and monitoring.
 *
 * Returns the application status and OpenRegister availability.
 * Version is intentionally omitted to prevent unauthenticated version
 * fingerprinting (SEC-001 / CWE-200).
 */
class HealthController extends Controller
{
    /**
     * Constructor for the HealthController.
     *
     * @param IRequest    $request    The request object
     * @param IAppManager $appManager The app manager
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
     * Return health status as JSON.
     *
     * Returns HTTP 200 when healthy (OpenRegister available),
     * HTTP 503 when degraded (OpenRegister unavailable).
     * Version is omitted from the public response to prevent unauthenticated
     * version fingerprinting (OWASP A05 / CWE-200).
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

        $status     = 'degraded';
        $httpStatus = Http::STATUS_SERVICE_UNAVAILABLE;

        if ($openRegisterAvailable === true) {
            $status     = 'ok';
            $httpStatus = Http::STATUS_OK;
        }

        $data = [
            'status'                => $status,
            'openRegisterAvailable' => $openRegisterAvailable,
        ];

        return new JSONResponse($data, $httpStatus);
    }//end index()
}//end class
