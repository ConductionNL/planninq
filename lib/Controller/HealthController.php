<?php

/**
 * Planix Health Controller
 *
 * Controller for exposing health check status.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for health check endpoint.
 *
 * Admin-only: no @NoAdminRequired — NC middleware enforces admin access.
 */
class HealthController extends Controller
{
    /**
     * Constructor for the HealthController.
     *
     * @param IRequest        $request The request object
     * @param IDBConnection   $db      The database connection
     * @param LoggerInterface $logger  Logger for error reporting
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly IDBConnection $db,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Return health check status.
     *
     * @NoCSRFRequired
     *
     * @spec exclude observability endpoint per ADR-006 (health plumbing, no business capability)
     *
     * @return JSONResponse JSON response with health status and checks.
     */
    public function index(): JSONResponse
    {
        $checks = [];
        $status = 'ok';

        // Database check.
        try {
            $qb     = $this->db->getQueryBuilder();
            $result = $qb->select($qb->createFunction('1'))->executeQuery();
            $result->closeCursor();
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $status = 'error';
            $this->logger->error(
                'Planix health check: database failed',
                ['exception' => $e->getMessage()]
            );
        }

        return new JSONResponse(
            [
                'status' => $status,
                'checks' => $checks,
            ]
        );
    }//end index()
}//end class
