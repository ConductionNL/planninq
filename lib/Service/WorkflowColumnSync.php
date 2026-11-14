<?php

/**
 * Planninq WorkflowColumnSync
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Keeps the columns of every project on a shared workflow in step with it
 * (projects-templates-shared-workflow, design decision 3). A project on a
 * workflow still owns its `column` objects, `project` required as before;
 * each carries the `workflowKey` of the workflow column it was made from.
 *
 * For one project and its workflow:
 * - a workflow column the project has (by key) is renamed, reordered,
 *   retyped and recoloured to match;
 * - a workflow column the project lacks is adopted from a project column
 *   with the same title and no key, or else created;
 * - a project column whose key left the workflow, or a project's own column
 *   with no match, is removed, its tasks moved to the first column first.
 *
 * Writes are made as the system (`_rbac: false`): the admin who edits a
 * workflow is usually not on every project. A new column gets the project's
 * access lists, because a silent save does not run the listener that writes them.
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
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds and updates the columns of projects that follow a workflow.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
class WorkflowColumnSync {

	/**
	 * The workflow schema.
	 */
	public const WORKFLOW_SCHEMA = 'workflow';


	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads planninq objects and the project's access lists.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService for the writes.
	 * @param LoggerInterface          $logger     Records what a sync moved or could not do.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Bring every project that follows a workflow in step with it.
	 *
	 * A project that cannot be updated is logged with the reason and the others still are.
	 *
	 * @param string $workflowId The workflow.
	 *
	 * @return array{projects: int, failed: int, moved: int} How many projects were updated, failed, and tasks moved.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function syncWorkflow(string $workflowId): array {
		$counts   = ['projects' => 0, 'failed' => 0, 'moved' => 0];
		$workflow = $this->membership->objectData(schema: self::WORKFLOW_SCHEMA, id: $workflowId);
		if ($workflow === null) {
			return $counts;
		}

		foreach ($this->projectsOn(workflowId: $workflowId) as $projectId) {
			try {
				$counts['moved'] += $this->syncProject(projectId: $projectId, workflow: $workflow);
				$counts['projects']++;
			} catch (Throwable $e) {
				$counts['failed']++;
				$this->logger->error(
					'Planninq: could not bring a project in step with its workflow',
					['workflow' => $workflowId, 'project' => $projectId, 'exception' => $e->getMessage()]
				);
			}
		}

		if ($counts['moved'] > 0) {
			$this->logger->info('Planninq: a workflow sync moved tasks out of removed columns', ['workflow' => $workflowId] + $counts);
		}

		return $counts;
	}//end syncWorkflow()

	/**
	 * Bring one project in step with the workflow it now follows.
	 *
	 * @param string $projectId  The project.
	 * @param string $workflowId The workflow.
	 *
	 * @return int How many tasks moved; 0 when the workflow does not exist.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-a-project-manager-moves-a-project-onto-a-workflow
	 */
	public function syncProjectOnto(string $projectId, string $workflowId): int {
		$workflow = $this->membership->objectData(schema: self::WORKFLOW_SCHEMA, id: $workflowId);
		if ($workflow === null) {
			return 0;
		}

		return $this->syncProject(projectId: $projectId, workflow: $workflow);
	}//end syncProjectOnto()

	/**
	 * The uuids of the projects that follow a workflow.
	 *
	 * @param string $workflowId The workflow.
	 *
	 * @return array<int, string>
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function projectsOn(string $workflowId): array {
		if ($workflowId === '') {
			return [];
		}

		$ids = [];
		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['workflow' => $workflowId]) as $row) {
			if ((string)($row['data']['workflow'] ?? '') === $workflowId) {
				$ids[] = $row['id'];
			}
		}

		return $ids;
	}//end projectsOn()

	/**
	 * Bring one project's columns in step with a workflow.
	 *
	 * @param string              $projectId The project.
	 * @param array<string,mixed> $workflow  The workflow's data.
	 *
	 * @return int How many tasks moved out of a removed column.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-a-project-manager-moves-a-project-onto-a-workflow
	 */
	public function syncProject(string $projectId, array $workflow): int {
		$wanted = self::workflowColumns(workflow: $workflow);
		if ($wanted === []) {
			return 0;
		}

		$columns  = $this->membership->rows(schema: 'column', filters: ['project' => $projectId]);
		$byKey    = [];
		$byTitle  = [];
		foreach ($columns as $column) {
			$key = (string)($column['data']['workflowKey'] ?? '');
			if ($key !== '') {
				$byKey[$key] = $column;
				continue;
			}

			$byTitle[mb_strtolower(trim((string)($column['data']['title'] ?? '')))] = $column;
		}

		$kept = [];
		foreach ($wanted as $order => $definition) {
			$column = ($byKey[$definition['key']] ?? $byTitle[mb_strtolower($definition['title'])] ?? null);
			$kept[] = $this->place(projectId: $projectId, column: $column, definition: $definition, order: $order);
		}

		$first = $kept[0];
		$moved = 0;
		foreach ($columns as $column) {
			if (in_array($column['id'], $kept, true) === false) {
				$moved += $this->retire(projectId: $projectId, columnId: $column['id'], firstColumnId: $first);
			}
		}

		return $moved;
	}//end syncProject()

	/**
	 * How a project's tasks would move onto a workflow: per target column, how many tasks land there because their column has no match.
	 *
	 * @param string              $projectId The project.
	 * @param array<string,mixed> $workflow  The workflow's data.
	 *
	 * @return array{moving: int, target: string} The tasks whose column has no match, and the column they move to.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-a-project-manager-moves-a-project-onto-a-workflow
	 */
	public function preview(string $projectId, array $workflow): array {
		$wanted = self::workflowColumns(workflow: $workflow);
		if ($wanted === []) {
			return ['moving' => 0, 'target' => ''];
		}

		$keys   = array_column($wanted, 'key');
		$titles = array_map(static fn (array $c): string => mb_strtolower($c['title']), $wanted);
		$moving = 0;
		foreach ($this->membership->rows(schema: 'column', filters: ['project' => $projectId]) as $column) {
			$matches = in_array((string)($column['data']['workflowKey'] ?? ''), $keys, true)
				|| in_array(mb_strtolower(trim((string)($column['data']['title'] ?? ''))), $titles, true);
			if ($matches === false) {
				$moving += count($this->membership->rows(schema: 'task', filters: ['column' => $column['id']]));
			}
		}

		return ['moving' => $moving, 'target' => $wanted[0]['title']];
	}//end preview()

	/**
	 * A workflow's columns as clean definitions, in order.
	 *
	 * @param array<string,mixed> $workflow The workflow's data.
	 *
	 * @return array<int, array{key: string, title: string, type: string, wipLimit: int|null, color: string|null}>
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public static function workflowColumns(array $workflow): array {
		$columns = [];
		$seen    = [];
		foreach ((array)($workflow['columns'] ?? []) as $column) {
			$key   = trim((string)($column['key'] ?? ''));
			$title = trim((string)($column['title'] ?? ''));
			if ($key === '' || $title === '' || isset($seen[$key]) === true) {
				continue;
			}

			$seen[$key] = true;
			$wipLimit   = null;
			if (is_int($column['wipLimit'] ?? null) === true) {
				$wipLimit = $column['wipLimit'];
			}

			$color = null;
			if (is_string($column['color'] ?? null) === true && $column['color'] !== '') {
				$color = $column['color'];
			}

			$type = 'active';
			if (($column['type'] ?? null) === 'done') {
				$type = 'done';
			}

			$columns[] = ['key' => $key, 'title' => $title, 'type' => $type, 'wipLimit' => $wipLimit, 'color' => $color];
		}

		return $columns;
	}//end workflowColumns()

	/**
	 * Make one project column match a workflow column, creating it when the project has none.
	 *
	 * @param string                    $projectId  The project.
	 * @param array<string, mixed>|null $column     The project's column (id and data), or null.
	 * @param array<string, mixed>      $definition The workflow column (key, title, type, wipLimit, color).
	 * @param int                       $order      Its position.
	 *
	 * @return string The column's uuid.
	 */
	private function place(string $projectId, ?array $column, array $definition, int $order): string {
		$wanted = [
			'title'       => $definition['title'],
			'type'        => $definition['type'],
			'wipLimit'    => $definition['wipLimit'],
			'color'       => $definition['color'],
			'order'       => $order,
			'workflowKey' => $definition['key'],
		];

		if ($column === null) {
			$project = ($this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId) ?? []);
			$data    = $wanted + ['project' => $projectId] + $this->membership->childLists(project: $project);
			return $this->save(data: $data, uuid: null);
		}

		$data    = $column['data'];
		$changed = false;
		foreach ($wanted as $field => $value) {
			if (($data[$field] ?? null) !== $value) {
				$data[$field] = $value;
				$changed      = true;
			}
		}

		if ($changed === true) {
			$this->save(data: $data, uuid: $column['id']);
		}

		return $column['id'];
	}//end place()

	/**
	 * Remove a column the workflow no longer has, after moving its tasks to the first column.
	 *
	 * @param string $projectId     The project.
	 * @param string $columnId      The column to remove.
	 * @param string $firstColumnId Where its tasks go.
	 *
	 * @return int How many tasks moved.
	 */
	private function retire(string $projectId, string $columnId, string $firstColumnId): int {
		$moved = 0;
		foreach ($this->membership->rows(schema: 'task', filters: ['column' => $columnId]) as $task) {
			if ((string)($task['data']['project'] ?? $projectId) !== $projectId) {
				continue;
			}

			$data           = $task['data'];
			$data['column'] = $firstColumnId;
			$this->objectService()->saveObject(
				object: $data,
				register: ProjectMembershipService::REGISTER,
				schema: 'task',
				uuid: $task['id'],
				_rbac: false,
				_multitenancy: false,
				silent: true,
				_validation: false
			);
			$moved++;
		}

		$this->objectService()->deleteObject(
			uuid: $columnId,
			register: ProjectMembershipService::REGISTER,
			schema: 'column',
			_rbac: false,
			_multitenancy: false
		);

		return $moved;
	}//end retire()

	/**
	 * Save a column as the system, answering its uuid.
	 *
	 * @param array<string,mixed> $data The column.
	 * @param string|null         $uuid The column to update, or null to create.
	 *
	 * @return string
	 */
	private function save(array $data, ?string $uuid): string {
		$saved = $this->objectService()->saveObject(
			object: $data,
			register: ProjectMembershipService::REGISTER,
			schema: 'column',
			uuid: $uuid,
			_rbac: false,
			_multitenancy: false,
			silent: true,
			_validation: false
		);

		$id = '';
		if (is_object($saved) === true && is_callable([$saved, 'getUuid']) === true) {
			$id = (string)($saved->getUuid() ?? '');
		}

		if ($id === '' && $uuid !== null) {
			$id = $uuid;
		}

		return $id;
	}//end save()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object
	 */
	private function objectService(): object {
		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()
}//end class
