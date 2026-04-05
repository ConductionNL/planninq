<?php

/**
 * Planix Time Entry Controller
 *
 * Controller for time entry CRUD operations.
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

// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\TimeEntryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for time entry CRUD operations.
 */
class TimeEntryController extends Controller
{
    /**
     * Constructor for the TimeEntryController.
     *
     * @param IRequest         $request          The request object
     * @param TimeEntryService $timeEntryService The time entry service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private TimeEntryService $timeEntryService,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Create a new time entry.
     *
     * Expects JSON body with: taskId, duration (minutes, > 0), date (ISO 8601), description (optional).
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function create(): JSONResponse
    {
        $userId = $this->timeEntryService->getCurrentUserId();
        if ($userId === null) {
            return new JSONResponse(
                ['error' => 'Authentication required.'],
                Http::STATUS_FORBIDDEN
            );
        }

        try {
            $data  = $this->request->getParams();
            $entry = $this->timeEntryService->createTimeEntry($data);

            return new JSONResponse($entry, Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end create()

    /**
     * List time entries for a task.
     *
     * Expects query parameter: taskId.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        $userId = $this->timeEntryService->getCurrentUserId();
        if ($userId === null) {
            return new JSONResponse(
                ['error' => 'Authentication required.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $taskId = $this->request->getParam('taskId', '');
        if (empty($taskId) === true) {
            return new JSONResponse(
                ['error' => 'taskId query parameter is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $taskId) !== 1) {
            return new JSONResponse(
                ['error' => 'taskId must be a valid UUID.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $entries = $this->timeEntryService->listTimeEntries($taskId);

            return new JSONResponse($entries);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_FORBIDDEN
            );
        }

    }//end index()

    /**
     * Delete a time entry (owner only).
     *
     * @param string $id The time entry UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function destroy(string $id): JSONResponse
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            return new JSONResponse(
                ['error' => 'Invalid identifier format.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $userId = $this->timeEntryService->getCurrentUserId();
        if ($userId === null) {
            return new JSONResponse(
                ['error' => 'Authentication required.'],
                Http::STATUS_FORBIDDEN
            );
        }

        try {
            $this->timeEntryService->deleteTimeEntry($id);

            return new JSONResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_FORBIDDEN
            );
        }

    }//end destroy()
}//end class
