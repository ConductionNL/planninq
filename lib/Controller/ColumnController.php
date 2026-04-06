<?php

/**
 * Planix Column Controller
 *
 * Controller for managing kanban board columns per project.
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
use OCA\Planix\Service\ColumnService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for managing kanban board columns.
 */
class ColumnController extends Controller
{
    /**
     * Constructor for the ColumnController.
     *
     * @param IRequest      $request       The request object
     * @param ColumnService $columnService The column service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private ColumnService $columnService,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * List columns for a project, ordered by position.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        $projectId = $this->request->getParam('projectId');

        if (empty($projectId) === true) {
            return new JSONResponse(
                ['error' => 'The projectId query parameter is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if ($this->columnService->findProject($projectId) === null) {
            return new JSONResponse(
                ['error' => 'Project not found.'],
                Http::STATUS_NOT_FOUND
            );
        }

        if ($this->columnService->isProjectMember($projectId) === false) {
            return new JSONResponse(
                ['error' => 'You are not a member of this project.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $columns = $this->columnService->listColumns($projectId);

        return new JSONResponse($columns);

    }//end index()

    /**
     * Create a new column.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function create(): JSONResponse
    {
        $data      = $this->request->getParams();
        $projectId = $data['project'] ?? ($data['projectId'] ?? null);

        if (empty($projectId) === true) {
            return new JSONResponse(
                ['error' => 'The project field is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if ($this->columnService->findProject($projectId) === null) {
            return new JSONResponse(
                ['error' => 'Project not found.'],
                Http::STATUS_NOT_FOUND
            );
        }

        if ($this->columnService->isProjectMember($projectId) === false) {
            return new JSONResponse(
                ['error' => 'You are not a member of this project.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $title = $data['title'] ?? '';
        if (empty($title) === true) {
            return new JSONResponse(
                ['error' => 'The title field is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $wipLimit = null;
        if (isset($data['wipLimit']) === true) {
            $wipLimit = (int) $data['wipLimit'];
        }

        $columnData = [
            'title'    => $title,
            'project'  => $projectId,
            'order'    => (int) ($data['order'] ?? ($data['position'] ?? 0)),
            'wipLimit' => $wipLimit,
            'color'    => $data['color'] ?? null,
            'type'     => $data['type'] ?? 'active',
        ];

        $column = $this->columnService->createColumn($columnData);

        return new JSONResponse($column, Http::STATUS_CREATED);

    }//end create()

    /**
     * Update an existing column.
     *
     * @param string $id The column UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function update(string $id): JSONResponse
    {
        $column = $this->columnService->findColumn($id);
        if ($column === null) {
            return new JSONResponse(
                ['error' => 'Column not found.'],
                Http::STATUS_NOT_FOUND
            );
        }

        $projectId = $column['project'] ?? '';
        if ($this->columnService->isProjectMember($projectId) === false) {
            return new JSONResponse(
                ['error' => 'You are not a member of this project.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $data       = $this->request->getParams();
        $updateData = [];

        if (isset($data['title']) === true) {
            $updateData['title'] = $data['title'];
        }

        if (isset($data['order']) === true || isset($data['position']) === true) {
            $updateData['order'] = (int) ($data['order'] ?? $data['position']);
        }

        if (array_key_exists('wipLimit', $data) === true) {
            if ($data['wipLimit'] !== null) {
                $updateData['wipLimit'] = (int) $data['wipLimit'];
            } else {
                $updateData['wipLimit'] = null;
            }
        }

        if (isset($data['color']) === true) {
            $updateData['color'] = $data['color'];
        }

        if (isset($data['type']) === true) {
            $updateData['type'] = $data['type'];
        }

        $updated = $this->columnService->updateColumn($id, $updateData);

        return new JSONResponse($updated);

    }//end update()

    /**
     * Delete a column. Tasks in this column are moved to the backlog.
     *
     * @param string $id The column UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     */
    public function destroy(string $id): JSONResponse
    {
        $column = $this->columnService->findColumn($id);
        if ($column === null) {
            return new JSONResponse(
                ['error' => 'Column not found.'],
                Http::STATUS_NOT_FOUND
            );
        }

        $projectId = $column['project'] ?? '';
        if ($this->columnService->isProjectMember($projectId) === false) {
            return new JSONResponse(
                ['error' => 'You are not a member of this project.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $deleted = $this->columnService->deleteColumn($id);

        if ($deleted === false) {
            return new JSONResponse(
                ['error' => 'Failed to delete column.'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return new JSONResponse(null, Http::STATUS_NO_CONTENT);

    }//end destroy()
}//end class
