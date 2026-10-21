<?php

/**
 * Planninq ProjectActionController
 *
 * The endpoints for the actions that write several objects at once: delete a
 * project with everything in it, ship a release, delete a parent task with
 * its subtasks, and copy a task with its subtasks. Each checks the caller's
 * rights on the object it names (ProjectActionService) before it writes.
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
 * @spec openspec/specs/projects.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\ProjectActionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Server-side multi-object actions.
 *
 * @spec openspec/specs/projects.md
 */
class ProjectActionController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest             $request The request.
	 * @param ProjectActionService $actions Checks the caller's rights and runs the action.
	 * @param LoggerInterface      $logger  The logger.
	 */
	public function __construct(
		IRequest $request,
		private ProjectActionService $actions,
		private LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Delete a project with all its objects. The owner, an owning group member or an admin only.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return JSONResponse 200 {deleted}; 401, 403, 404 otherwise.
	 *
	 * @spec openspec/specs/projects.md
	 */
	#[NoAdminRequired]
	public function deleteProject(string $projectId): JSONResponse {
		return $this->run(action: fn (): array => ['deleted' => $this->actions->deleteProject(projectId: $projectId)], context: 'project delete');
	}//end deleteProject()

	/**
	 * Ship a release at the server's time. A person who works on the project only.
	 *
	 * @param string      $releaseId The release UUID.
	 * @param string      $choice    `move`, `clear` or `keep` for the unfinished tasks.
	 * @param string|null $target    The release to move them to.
	 *
	 * @return JSONResponse 200 with the release; 400, 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
	 */
	#[NoAdminRequired]
	public function shipRelease(string $releaseId, string $choice = 'keep', ?string $target = null): JSONResponse {
		return $this->run(
			action: fn (): array => $this->actions->shipRelease(releaseId: $releaseId, choice: $choice, targetId: $target),
			context: 'release ship'
		);
	}//end shipRelease()

	/**
	 * Delete a parent task, deleting or keeping its subtasks. Its reporter, the project owner or an admin only.
	 *
	 * @param string $taskId   The task UUID.
	 * @param string $subtasks `delete` or `detach`.
	 *
	 * @return JSONResponse 200 {deleted}; 400, 401, 403, 404, 409 otherwise.
	 *
	 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.2
	 */
	#[NoAdminRequired]
	public function deleteTaskTree(string $taskId, string $subtasks = 'delete'): JSONResponse {
		return $this->run(action: fn (): array => ['deleted' => $this->actions->deleteTaskTree(taskId: $taskId, mode: $subtasks)], context: 'task delete');
	}//end deleteTaskTree()

	/**
	 * Copy a task with its subtasks. A person who works on the project only.
	 *
	 * @param string $taskId The task UUID.
	 *
	 * @return JSONResponse 201 with the copy; 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
	 */
	#[NoAdminRequired]
	public function duplicateTask(string $taskId): JSONResponse {
		$response = $this->run(action: fn (): array => $this->actions->duplicateTask(taskId: $taskId), context: 'task copy');
		if ($response->getStatus() === Http::STATUS_OK) {
			$response->setStatus(Http::STATUS_CREATED);
		}

		return $response;
	}//end duplicateTask()

	/**
	 * Move a phase one place up or down. A person who works on the project only.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $phase     The phase UUID.
	 * @param int    $step      -1 or +1.
	 *
	 * @return JSONResponse 200 with the phases' new order; 400, 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
	 */
	#[NoAdminRequired]
	public function movePhase(string $projectId, string $phase = '', int $step = 0): JSONResponse {
		return $this->run(action: fn (): array => $this->actions->movePhase(projectId: $projectId, phaseId: $phase, step: $step), context: 'phase move');
	}//end movePhase()

	/**
	 * Create an action task from a log entry and link it. A person who works on the project only.
	 *
	 * @param string              $entryId The log entry UUID.
	 * @param array<string,mixed> $task    The task fields.
	 *
	 * @return JSONResponse 201 with the task and the entry; 401, 403, 404 otherwise.
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
	 */
	#[NoAdminRequired]
	public function addAction(string $entryId, array $task = []): JSONResponse {
		$response = $this->run(action: fn (): array => $this->actions->addAction(entryId: $entryId, task: $task), context: 'log entry action');
		if ($response->getStatus() === Http::STATUS_OK) {
			$response->setStatus(Http::STATUS_CREATED);
		}

		return $response;
	}//end addAction()

	/**
	 * Run an action and answer with its result or its refusal.
	 *
	 * @param callable $action  The action; it checks the caller's rights itself.
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
