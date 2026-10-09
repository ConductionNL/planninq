<?php

/**
 * Planninq WorkflowSyncService
 *
 * Builds a project's own column objects from the workflow it follows and
 * keeps them in step when the workflow changes. Runs as the system, because
 * the admin who edits a workflow is not on every project that follows it.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Log\LoggerInterface;

/**
 * Keeps project columns in step with their workflow.
 */
class WorkflowSyncService {

	/**
	 * OpenRegister's system-operation scope, which lets the sync past the column owner guard.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects, columns and tasks.
	 * @param DependencyRepository     $repository Resolves OpenRegister's ObjectService.
	 * @param WorkflowColumnPlanner    $planner    Works out the changes.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private DependencyRepository $repository,
		private WorkflowColumnPlanner $planner,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The ids of the projects that follow a workflow.
	 *
	 * @param string $workflowId The workflow UUID.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
	 */
	public function projectsOn(string $workflowId): array {
		$ids = [];
		foreach ($this->membership->rows(schema: 'project', filters: ['workflow' => $workflowId]) as $row) {
			if (($row['data']['workflow'] ?? null) === $workflowId) {
				$ids[] = $row['id'];
			}
		}

		return $ids;
	}//end projectsOn()

	/**
	 * Bring every project on a workflow in step with it; one failing project does not stop the rest.
	 *
	 * @param string $workflowId The workflow UUID.
	 *
	 * @return array{synced:int,failed:array<string,string>} Projects done, and the reason per project that failed.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
	 */
	public function syncWorkflow(string $workflowId): array {
		$result = ['synced' => 0, 'failed' => []];
		foreach ($this->projectsOn(workflowId: $workflowId) as $projectId) {
			try {
				$this->syncProject(projectId: $projectId);
				$result['synced']++;
			} catch (\Throwable $e) {
				$result['failed'][$projectId] = $e->getMessage();
				$this->logger->error(
					'Planninq: a project did not follow its workflow change; run planninq:workflow:resync',
					['project' => $projectId, 'workflow' => $workflowId, 'exception' => $e->getMessage()]
				);
			}
		}

		return $result;
	}//end syncWorkflow()

	/**
	 * Build or update one project's columns from its workflow.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return array{created:int,updated:int,removed:int,moved:int} What was done; all zero when the project follows no workflow.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
	 */
	public function syncProject(string $projectId): array {
		$none    = ['created' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0];
		$project = $this->membership->objectData(schema: 'project', id: $projectId);
		$flowId  = (string)($project['workflow'] ?? '');
		if ($project === null || $flowId === '') {
			return $none;
		}

		$workflow = $this->membership->objectData(schema: 'workflow', id: $flowId);
		if ($workflow === null || (array)($workflow['columns'] ?? []) === []) {
			return $none;
		}

		$columns = $this->membership->rows(schema: 'column', filters: ['project' => $projectId]);
		$tasks   = $this->membership->rows(schema: 'task', filters: ['project' => $projectId]);
		$sitting = [];
		foreach ($tasks as $task) {
			$column = (string)($task['data']['column'] ?? '');
			if ($column !== '') {
				$sitting[$task['id']] = $column;
			}
		}

		$plan = $this->planner->plan(workflow: array_values((array)$workflow['columns']), existing: $columns, taskColumns: $sitting);
		$this->inSystemScope(
			work: function () use ($plan, $projectId, $columns, $tasks): void {
				$this->apply(plan: $plan, projectId: $projectId, columns: $columns, tasks: $tasks);
			}
		);

		return [
			'created' => count($plan['create']),
			'updated' => count($plan['update']),
			'removed' => count($plan['remove']),
			'moved'   => count($plan['moves']),
		];
	}//end syncProject()

	/**
	 * Write a plan: new columns first, then updates, then the tasks that move, then the removals.
	 *
	 * @param array<string,mixed>                                  $plan      The planner's plan.
	 * @param string                                               $projectId The project UUID.
	 * @param array<int,array{id:string,data:array<string,mixed>}> $columns   The project's current columns.
	 * @param array<int,array{id:string,data:array<string,mixed>}> $tasks     The project's tasks.
	 *
	 * @return void
	 */
	private function apply(array $plan, string $projectId, array $columns, array $tasks): void {
		$service = $this->repository->objectService();
		$ids     = $plan['matched'];
		foreach ($plan['create'] as $key => $data) {
			$saved = $service->saveObject(
				object: array_merge($data, ['project' => $projectId]),
				register: ProjectMembershipService::REGISTER,
				schema: 'column',
				_rbac: false,
				_multitenancy: false
			);
			$ids[$key] = (string)$saved->getUuid();
		}

		$current = [];
		foreach ($columns as $row) {
			$current[$row['id']] = $row['data'];
		}

		foreach ($plan['update'] as $id => $changes) {
			$this->write(service: $service, schema: 'column', id: (string)$id, data: array_merge($current[$id], $changes));
		}

		$target = (string)($ids[$plan['target']] ?? '');
		foreach ($tasks as $task) {
			if (in_array($task['id'], $plan['moves'], true) === true && $target !== '') {
				$this->write(service: $service, schema: 'task', id: $task['id'], data: array_merge($task['data'], ['column' => $target]));
			}
		}

		foreach ($plan['remove'] as $id) {
			$service->deleteObject(uuid: $id, register: ProjectMembershipService::REGISTER, schema: 'column', _rbac: false, _multitenancy: false);
		}
	}//end apply()

	/**
	 * Write a whole object back, silent and unvalidated like the other system writes.
	 *
	 * @param object              $service The OpenRegister ObjectService.
	 * @param string              $schema  The schema slug.
	 * @param string              $id      The object UUID.
	 * @param array<string,mixed> $data    The full object data.
	 *
	 * @return void
	 */
	private function write(object $service, string $schema, string $id, array $data): void {
		$service->saveObject(
			object: $data,
			register: ProjectMembershipService::REGISTER,
			schema: $schema,
			uuid: $id,
			_rbac: false,
			_multitenancy: false,
			silent: true,
			_validation: false
		);
	}//end write()

	/**
	 * Run a write inside OpenRegister's system-operation scope when it has one.
	 *
	 * @param callable $work The writes.
	 *
	 * @return void
	 */
	private function inSystemScope(callable $work): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			$work();
			return;
		}

		$class::run($work);
	}//end inSystemScope()
}//end class
