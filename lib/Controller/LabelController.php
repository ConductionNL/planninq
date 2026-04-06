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
use Psr\Log\LoggerInterface;

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
     * @param IRequest        $request      The request object
     * @param LabelService    $labelService The label service
     * @param LoggerInterface $logger       The logger
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private LabelService $labelService,
        private LoggerInterface $logger,
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
            $this->logger->error('LabelController: failed to list labels', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                ['error' => 'An internal error occurred. Please try again later.'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end index()

    /**
     * Create a new label.
     *
     * Requires 'title' and 'color' in the request body. Only admin users may create labels.
     *
     * @NoAdminRequired
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function create(): JSONResponse
    {
        if ($this->labelService->isCurrentUserAdmin() === false) {
            return new JSONResponse(
                ['error' => 'Admin privileges required to create labels.'],
                Http::STATUS_FORBIDDEN
            );
        }

        $title = $this->request->getParam('title');
        $color = $this->request->getParam('color');

        if (empty($title) === true) {
            return new JSONResponse(
                ['error' => 'The "title" field is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (empty($color) === true) {
            return new JSONResponse(
                ['error' => 'The "color" field is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color) !== 1) {
            return new JSONResponse(
                ['error' => 'The "color" field must be a valid hex color (e.g. #RRGGBB or #RRGGBBAA).'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $label = $this->labelService->create(data: ['title' => $title, 'color' => $color]);
            return new JSONResponse($label, Http::STATUS_CREATED);
        } catch (\RuntimeException $e) {
            $this->logger->error('LabelController: failed to create label', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                ['error' => 'An internal error occurred. Please try again later.'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end create()

    /**
     * Delete a label by UUID.
     *
     * Only admin users may delete labels.
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
        if ($this->labelService->isCurrentUserAdmin() === false) {
            return new JSONResponse(
                ['error' => 'Admin privileges required to delete labels.'],
                Http::STATUS_FORBIDDEN
            );
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            return new JSONResponse(['error' => 'Invalid label ID format.'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $deleted = $this->labelService->delete(id: $id);
            if ($deleted === false) {
                return new JSONResponse(['error' => 'Label not found.'], Http::STATUS_NOT_FOUND);
            }

            return new JSONResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
        } catch (\RuntimeException $e) {
            $this->logger->error('LabelController: failed to delete label', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                ['error' => 'An internal error occurred. Please try again later.'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

    }//end destroy()
}//end class
