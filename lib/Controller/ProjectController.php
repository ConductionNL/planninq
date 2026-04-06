<?php

/**
 * Planix Project Controller
 *
 * Controller for managing Planix project CRUD operations.
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
use OCA\Planix\Service\ProjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for managing Planix project CRUD operations.
 */
class ProjectController extends Controller
{
    /**
     * Constructor for the ProjectController.
     *
     * @param IRequest        $request        The request object
     * @param ProjectService  $projectService The project service
     * @param LoggerInterface $logger         The logger
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private ProjectService $projectService,
        private LoggerInterface $logger,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);

    }//end __construct()

    /**
     * List all projects the current user is a member of.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        try {
            $projects = $this->projectService->findAll();
            return new JSONResponse(data: $projects);
        } catch (\Throwable $e) {
            $this->logger->error('ProjectController error: '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'An unexpected error occurred.'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end index()

    /**
     * Retrieve a single project by ID.
     *
     * Authentication is checked before the datastore lookup to prevent a
     * 404/403 oracle that would allow unauthenticated callers to enumerate
     * valid project UUIDs (IDOR — CWE-284).
     *
     * Returns 403 when the caller is unauthenticated or not a project member.
     * Returns 404 only for authenticated members who look up a non-existent ID.
     *
     * @param string $id The project UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function show(string $id): JSONResponse
    {
        try {
            // Auth check BEFORE the datastore lookup — prevents 404/403 oracle enumeration.
            $uid = $this->projectService->getCurrentUserId();
            if ($uid === null) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $project = $this->projectService->find(id: $id);

            if ($project === null || $this->projectService->isMember(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            return new JSONResponse(data: $project);
        } catch (\Throwable $e) {
            $this->logger->error('ProjectController error: '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'An unexpected error occurred.'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try

    }//end show()

    /**
     * Create a new project. Title is required. Returns 403 when unauthenticated.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function create(): JSONResponse
    {
        try {
            // Guard unauthenticated callers here so we return 403, not 500.
            $uid = $this->projectService->getCurrentUserId();
            if ($uid === null) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $params = $this->request->getParams();
            $title  = trim((string) ($params['title'] ?? ''));

            if ($title === '') {
                return new JSONResponse(
                    data: ['error' => 'Title is required.'],
                    statusCode: Http::STATUS_BAD_REQUEST
                );
            }

            $color = (string) ($params['color'] ?? '');
            if ($color !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                return new JSONResponse(
                    data: ['error' => 'Invalid color format. Expected #RRGGBB hex color.'],
                    statusCode: Http::STATUS_BAD_REQUEST
                );
            }

            $data = [
                'title'       => $title,
                'description' => ($params['description'] ?? ''),
                'color'       => $color,
            ];

            $project = $this->projectService->create(data: $data);

            return new JSONResponse(
                data: $project,
                statusCode: Http::STATUS_CREATED
            );
        } catch (\Throwable $e) {
            $this->logger->error('ProjectController error: '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'An unexpected error occurred.'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try

    }//end create()

    /**
     * Partially update an existing project. Only members may update; only the owner may
     * modify the members list.
     *
     * Authentication is checked before the datastore lookup to prevent a
     * 404/403 oracle that would allow unauthenticated callers to enumerate
     * valid project UUIDs (IDOR — CWE-284).
     *
     * @param string $id The project UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function update(string $id): JSONResponse
    {
        try {
            // Auth check BEFORE the datastore lookup — prevents 404/403 oracle enumeration.
            $uid = $this->projectService->getCurrentUserId();
            if ($uid === null) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $project = $this->projectService->find(id: $id);

            if ($project === null || $this->projectService->isMember(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $params = $this->request->getParams();

            // Only the project owner may modify the members list (prevents ownership hijack).
            if (array_key_exists('members', $params) === true
                && $this->projectService->isOwner(project: $project, uid: $uid) === false
            ) {
                return new JSONResponse(
                    data: ['error' => 'Only the project owner may modify the member list.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            // Validate color format when provided.
            if (isset($params['color']) === true && $params['color'] !== '') {
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $params['color']) !== 1) {
                    return new JSONResponse(
                        data: ['error' => 'Invalid color format. Expected #RRGGBB hex color.'],
                        statusCode: Http::STATUS_BAD_REQUEST
                    );
                }
            }

            // Validate status enum when provided.
            $allowedStatuses = ['active', 'archived', 'completed'];
            if (isset($params['status']) === true
                && in_array($params['status'], $allowedStatuses, strict: true) === false
            ) {
                return new JSONResponse(
                    data: ['error' => 'Invalid status. Allowed values: active, archived, completed.'],
                    statusCode: Http::STATUS_BAD_REQUEST
                );
            }

            $data = [];

            foreach (['title', 'description', 'color', 'status', 'members'] as $key) {
                if (array_key_exists($key, $params) === true) {
                    $data[$key] = $params[$key];
                }
            }

            $updated = $this->projectService->update(id: $id, data: $data);

            return new JSONResponse(data: $updated);
        } catch (\Throwable $e) {
            $this->logger->error('ProjectController error: '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'An unexpected error occurred.'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try

    }//end update()

    /**
     * Delete a project. Only the owner (first member) may delete.
     *
     * Authentication is checked before the datastore lookup to prevent a
     * 404/403 oracle that would allow unauthenticated callers to enumerate
     * valid project UUIDs (IDOR — CWE-284).
     *
     * Note: isOwner implies membership — no separate isMember check needed.
     *
     * @param string $id The project UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function destroy(string $id): JSONResponse
    {
        try {
            // Auth check BEFORE the datastore lookup — prevents 404/403 oracle enumeration.
            $uid = $this->projectService->getCurrentUserId();
            if ($uid === null) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $project = $this->projectService->find(id: $id);

            if ($project === null || $this->projectService->isOwner(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $this->projectService->delete(id: $id);

            return new JSONResponse(data: ['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('ProjectController error: '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'An unexpected error occurred.'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try

    }//end destroy()
}//end class
