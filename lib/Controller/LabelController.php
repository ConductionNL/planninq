<?php

/**
 * Planix Label Controller
 *
 * Controller for label CRUD endpoints.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 * @spec     openspec/changes/label-crud/tasks.md#task-1
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

use InvalidArgumentException;
use OCA\Planix\AppInfo\Application;
use OCA\Planix\Service\LabelService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use RuntimeException;

/**
 * Controller for label CRUD endpoints.
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
     * @spec openspec/changes/label-crud/tasks.md#task-1
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
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return JSONResponse
     */
    public function index(): JSONResponse
    {
        try {
            $labels = $this->labelService->findAll();
            return new JSONResponse($labels);
        } catch (RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end index()

    /**
     * Create a new label.
     *
     * @NoAdminRequired
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return JSONResponse
     */
    public function create(): JSONResponse
    {
        try {
            $data  = $this->request->getParams();
            $label = $this->labelService->create($data);

            return new JSONResponse($label, Http::STATUS_CREATED);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        } catch (RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end create()

    /**
     * Delete a label by its UUID.
     *
     * @param string $id The UUID of the label
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
            $this->labelService->delete($id);

            return new JSONResponse(['success' => true]);
        } catch (RuntimeException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end destroy()
}//end class
