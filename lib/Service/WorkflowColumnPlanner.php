<?php

/**
 * Planninq WorkflowColumnPlanner
 *
 * Works out what must change on a project's columns and tasks to follow a
 * workflow: which columns to add, update or remove, and which tasks move to
 * the first column. Pure: it reads nothing and writes nothing.
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

/**
 * Plans the column sync of one project.
 */
class WorkflowColumnPlanner {

	/**
	 * Plan the sync.
	 *
	 * A column follows a workflow column when it carries its `workflowKey`;
	 * a column without a key is adopted by the workflow column with the same
	 * title. Columns left over are removed, and their tasks move to the
	 * workflow's first column.
	 *
	 * @param array<int,array<string,mixed>>                       $workflow The workflow's columns, in order.
	 * @param array<int,array{id:string,data:array<string,mixed>}> $existing The project's column rows.
	 * @param array<string,string>                                 $taskColumns Task id => column id, for tasks that sit in a column.
	 *
	 * @return array{
	 *     matched: array<string,string>,
	 *     create: array<string,array<string,mixed>>,
	 *     update: array<string,array<string,mixed>>,
	 *     remove: array<int,string>,
	 *     moves: array<int,string>,
	 *     target: string
	 * } `matched`: workflow key => existing column id; `create`: workflow key => column data; `update`: column id => data
	 *   changes; `remove`: column ids; `moves`: ids of the tasks that move; `target`: key of the column they move to.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
	 */
	public function plan(array $workflow, array $existing, array $taskColumns): array {
		$matched = $this->match(workflow: $workflow, existing: $existing);
		$byId    = [];
		foreach ($existing as $row) {
			$byId[$row['id']] = $row['data'];
		}

		$create = [];
		$update = [];
		foreach (array_values($workflow) as $order => $column) {
			$key  = (string)$column['key'];
			$data = $this->columnData(column: $column, order: $order);
			if (isset($matched[$key]) === false) {
				$create[$key] = $data;
				continue;
			}

			$changes = [];
			foreach ($data as $field => $value) {
				if (($byId[$matched[$key]][$field] ?? null) !== $value) {
					$changes[$field] = $value;
				}
			}

			if ($changes !== []) {
				$update[$matched[$key]] = $changes;
			}
		}

		$remove = array_values(array_diff(array_keys($byId), array_values($matched)));
		$moves  = [];
		foreach ($taskColumns as $taskId => $columnId) {
			if (in_array($columnId, $remove, true) === true) {
				$moves[] = (string)$taskId;
			}
		}

		return [
			'matched' => $matched,
			'create'  => $create,
			'update'  => $update,
			'remove'  => $remove,
			'moves'   => $moves,
			'target'  => (string)($workflow[0]['key'] ?? ''),
		];
	}//end plan()

	/**
	 * Workflow key => the existing column that follows it.
	 *
	 * @param array<int,array<string,mixed>>                       $workflow The workflow's columns.
	 * @param array<int,array{id:string,data:array<string,mixed>}> $existing The project's column rows.
	 *
	 * @return array<string,string>
	 */
	private function match(array $workflow, array $existing): array {
		$byKey = [];
		foreach ($existing as $row) {
			$key = (string)($row['data']['workflowKey'] ?? '');
			if ($key !== '' && isset($byKey[$key]) === false) {
				$byKey[$key] = $row['id'];
			}
		}

		$matched = [];
		foreach ($workflow as $column) {
			$key = (string)$column['key'];
			if (isset($byKey[$key]) === true) {
				$matched[$key] = $byKey[$key];
			}
		}

		// A column that follows no workflow column yet is adopted by the one with its title.
		foreach ($workflow as $column) {
			$key = (string)$column['key'];
			if (isset($matched[$key]) === false) {
				$found = $this->unkeyedWithTitle(existing: $existing, title: (string)$column['title'], taken: array_values($matched));
				if ($found !== null) {
					$matched[$key] = $found;
				}
			}
		}

		return $matched;
	}//end match()

	/**
	 * The id of a column that follows no workflow column and has this title, or null.
	 *
	 * @param array<int,array{id:string,data:array<string,mixed>}> $existing The project's column rows.
	 * @param string                                               $title    The workflow column's title.
	 * @param array<int,string>                                    $taken    Column ids already matched.
	 *
	 * @return string|null
	 */
	private function unkeyedWithTitle(array $existing, string $title, array $taken): ?string {
		foreach ($existing as $row) {
			$unkeyed = ((string)($row['data']['workflowKey'] ?? '') === '');
			$same    = (mb_strtolower(trim((string)($row['data']['title'] ?? ''))) === mb_strtolower(trim($title)));
			if ($unkeyed === true && $same === true && in_array($row['id'], $taken, true) === false) {
				return $row['id'];
			}
		}

		return null;
	}//end unkeyedWithTitle()

	/**
	 * The column object's fields for a workflow column.
	 *
	 * @param array<string,mixed> $column The workflow column.
	 * @param int                 $order  Its position, 0-based.
	 *
	 * @return array<string,mixed>
	 */
	private function columnData(array $column, int $order): array {
		$type = 'active';
		if (($column['type'] ?? 'active') === 'done') {
			$type = 'done';
		}

		return [
			'title'       => (string)$column['title'],
			'order'       => $order,
			'type'        => $type,
			'wipLimit'    => ($column['wipLimit'] ?? null),
			'color'       => ($column['color'] ?? null),
			'workflowKey' => (string)$column['key'],
		];
	}//end columnData()
}//end class
