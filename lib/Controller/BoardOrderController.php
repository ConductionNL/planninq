<?php

/**
 * Planninq BoardOrderController
 *
 * The board writes that touch several rows, each one request: moving or
 * reordering cards, reordering the lanes, and removing a lane with its cards.
 * BoardOrderService checks the caller's rights on the project first.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\BoardOrderService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Multi-row board writes.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
class BoardOrderController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest          $request The request.
	 * @param BoardOrderService $board   Checks the caller's rights and writes a board order.
	 * @param LoggerInterface   $logger  The logger.
	 */
	public function __construct(
		IRequest $request,
		private BoardOrderService $board,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Move or reorder cards in one request. A person who works on the project only.
	 *
	 * @param string                         $projectId The project UUID.
	 * @param array<int,array<string,mixed>> $patches   Each `{id, column?, columnOrder?, status?}`.
	 *
	 * @return JSONResponse 200 with the cards as stored; 400, 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
	 */
	#[NoAdminRequired]
	public function orderTasks(string $projectId, array $patches = []): JSONResponse {
		return $this->run(action: fn (): array => $this->board->orderTasks(projectId: $projectId, patches: $patches), context: 'card order');
	}//end orderTasks()

	/**
	 * Reorder the lanes in one request. The project owner or an admin only.
	 *
	 * @param string            $projectId The project UUID.
	 * @param array<int,string> $columns   The lanes in their new order.
	 *
	 * @return JSONResponse 200 with the new order; 400, 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
	 */
	#[NoAdminRequired]
	public function orderColumns(string $projectId, array $columns = []): JSONResponse {
		return $this->run(action: fn (): array => $this->board->orderColumns(projectId: $projectId, columnIds: $columns), context: 'column order');
	}//end orderColumns()

	/**
	 * Remove a lane, moving its cards to another lane or the backlog. The project owner or an admin only.
	 *
	 * @param string      $columnId The lane UUID.
	 * @param string|null $target   The lane the cards move to, null for the backlog.
	 *
	 * @return JSONResponse 200 {moved}; 400, 401, 403, 404, 409 otherwise.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
	 */
	#[NoAdminRequired]
	public function removeColumn(string $columnId, ?string $target = null): JSONResponse {
		return $this->run(
			action: fn (): array => ['moved' => $this->board->removeColumn(columnId: $columnId, targetId: $target)],
			context: 'column remove'
		);
	}//end removeColumn()

	/**
	 * Run a board write and answer with its result or its refusal.
	 *
	 * @param callable $action  The write; it checks the caller's rights itself.
	 * @param string   $context What runs, for the log.
	 *
	 * @return JSONResponse
	 */
	private function run(callable $action, string $context): JSONResponse {
		try {
			return new JSONResponse($action());
		} catch (ProjectActionException $e) {
			return new JSONResponse(['error' => $e->getMessage(), 'code' => $e->getReason()], $e->getStatus());
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: ' . $context . ' failed', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'Something went wrong. Please try again.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end run()
}//end class
