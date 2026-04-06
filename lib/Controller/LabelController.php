<?php

/**
 * Planix Label Controller
 *
 * Controller for managing labels via REST API.
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
 * @spec openspec/changes/label-crud/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\LabelService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for managing labels via REST API.
 *
 * @spec openspec/changes/label-crud/tasks.md#task-1
 */
class LabelController extends Controller
{
    /**
     * Constructor for the LabelController.
     *
     * @param IRequest     $request      The request object
     * @param LabelService $labelService The label service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private LabelService $labelService,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * List all labels.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function index(): JSONResponse
    {
        try {
            $labels = $this->labelService->findAll();
            return new JSONResponse($labels);
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end index()

    /**
     * Create a new label.
     *
     * Requires 'name' in the request body. 'color' is optional.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function create(): JSONResponse
    {
        $name  = $this->request->getParam('name');
        $color = $this->request->getParam('color');

        if (empty($name) === true) {
            return new JSONResponse(
                ['error' => 'The "name" field is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $data = ['title' => $name];
        if (empty($color) === false) {
            $data['color'] = $color;
        }

        try {
            $label = $this->labelService->create(data: $data);
            return new JSONResponse($label, Http::STATUS_CREATED);
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end create()

    /**
     * Delete a label by UUID.
     *
     * @param string $id The label UUID
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function destroy(string $id): JSONResponse
    {
        try {
            $this->labelService->delete(id: $id);
            return new JSONResponse(['success' => true]);
        } catch (\RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end destroy()
}//end class
