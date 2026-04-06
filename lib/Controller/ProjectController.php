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

/**
 * Controller for managing Planix project CRUD operations.
 */
class ProjectController extends Controller
{

    /**
     * Constructor for the ProjectController.
     *
     * @param IRequest       $request        The request object
     * @param ProjectService $projectService The project service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private ProjectService $projectService,
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
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end index()

    /**
     * Retrieve a single project by ID.
     *
     * Returns 404 when the project does not exist. Returns 403 when
     * the current user is not a member of the project.
     *
     * @NoAdminRequired
     *
     * @param string $id The project UUID
     *
     * @return JSONResponse
     */
    public function show(string $id): JSONResponse
    {
        try {
            $project = $this->projectService->find(id: $id);

            if ($project === null) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $uid = $this->projectService->getCurrentUserId();
            if ($uid !== null && $this->projectService->isMember(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            return new JSONResponse(data: $project);
        } catch (\Throwable $e) {
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end show()

    /**
     * Create a new project. Title is required.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function create(): JSONResponse
    {
        try {
            $params = $this->request->getParams();
            $title  = trim((string) ($params['title'] ?? ''));

            if ($title === '') {
                return new JSONResponse(
                    data: ['error' => 'Title is required.'],
                    statusCode: Http::STATUS_BAD_REQUEST
                );
            }

            $data = [
                'title'       => $title,
                'description' => ($params['description'] ?? ''),
                'color'       => ($params['color'] ?? ''),
            ];

            $project = $this->projectService->create(data: $data);

            return new JSONResponse(
                data: $project,
                statusCode: Http::STATUS_CREATED
            );
        } catch (\Throwable $e) {
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end create()

    /**
     * Update an existing project. Only members may update.
     *
     * @NoAdminRequired
     *
     * @param string $id The project UUID
     *
     * @return JSONResponse
     */
    public function update(string $id): JSONResponse
    {
        try {
            $project = $this->projectService->find(id: $id);

            if ($project === null) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $uid = $this->projectService->getCurrentUserId();
            if ($uid !== null && $this->projectService->isMember(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Access denied.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $params = $this->request->getParams();
            $data   = [];

            foreach (['title', 'description', 'color', 'status', 'members'] as $key) {
                if (array_key_exists($key, $params) === true) {
                    $data[$key] = $params[$key];
                }
            }

            $updated = $this->projectService->update(id: $id, data: $data);

            return new JSONResponse(data: $updated);
        } catch (\Throwable $e) {
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end update()

    /**
     * Delete a project. Only the owner (first member) may delete.
     *
     * @NoAdminRequired
     *
     * @param string $id The project UUID
     *
     * @return JSONResponse
     */
    public function destroy(string $id): JSONResponse
    {
        try {
            $project = $this->projectService->find(id: $id);

            if ($project === null) {
                return new JSONResponse(
                    data: ['error' => 'Project not found.'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $uid = $this->projectService->getCurrentUserId();
            if ($uid !== null && $this->projectService->isOwner(project: $project, uid: $uid) === false) {
                return new JSONResponse(
                    data: ['error' => 'Only the project owner may delete this project.'],
                    statusCode: Http::STATUS_FORBIDDEN
                );
            }

            $this->projectService->delete(id: $id);

            return new JSONResponse(data: ['success' => true]);
        } catch (\Throwable $e) {
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end destroy()
}//end class
