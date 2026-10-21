<?php

/**
 * Planninq BoardOrderService
 *
 * The board writes that touch several rows, done in one request: moving or
 * reordering cards, reordering the lanes, and removing a lane with its cards.
 * The browser sent one PATCH per row; a failure part-way left half the rows
 * renumbered or half the cards moved. Here every row is checked first (the
 * caller's rights, every task and lane in the project) and only then written.
 *
 * Cards are written with the caller's own rights, so the lane rules
 * (ColumnAutomationListener) and every task guard run on each card as before.
 * Lane order is written as the system once the project owner is confirmed.
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
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectActionException;

/**
 * Multi-row board writes.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
class BoardOrderService {

	/**
	 * The task fields a card move or reorder may write.
	 *
	 * @var array<int,string>
	 */
	private const CARD_FIELDS = ['column', 'columnOrder', 'status'];

	/**
	 * The gap between card positions, as the board uses.
	 *
	 * @var integer
	 */
	private const ORDER_STEP = 1000;

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads the project's tasks and lanes as the system.
	 * @param ProjectActionSupport     $support    Checks the caller and writes.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ProjectActionSupport $support,
	) {
	}//end __construct()

	/**
	 * Move or reorder cards: each patch names a task and its new lane, position and status.
	 *
	 * @param string                         $projectId The project UUID.
	 * @param array<int,array<string,mixed>> $patches   Each `{id, column?, columnOrder?, status?}`.
	 *
	 * @return array<int,array<string,mixed>> The cards as stored, lane rules applied.
	 *
	 * @throws ProjectActionException When the caller does not work on the project or a patch leaves it.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
	 */
	public function orderTasks(string $projectId, array $patches): array {
		$this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$this->support->assertWrites(projectId: $projectId);
		$tasks   = $this->byId(schema: 'task', projectId: $projectId);
		$columns = $this->byId(schema: 'column', projectId: $projectId);

		$writes = [];
		foreach ($patches as $patch) {
			$id     = (string)($patch['id'] ?? '');
			$fields = array_intersect_key($patch, array_flip(self::CARD_FIELDS));
			$column = ($fields['column'] ?? null);
			if (isset($tasks[$id]) === false || $fields === [] || ($column !== null && isset($columns[(string)$column]) === false)) {
				throw new ProjectActionException(
					reason: 'planninq-invalid-order',
					status: 400,
					message: 'Each card must be a task of this project, moved within it.'
				);
			}

			$writes[$id] = array_merge($tasks[$id], $fields);
		}

		$saved = [];
		foreach ($writes as $id => $data) {
			$saved[] = $this->support->saveAsCaller(schema: 'task', id: (string)$id, data: $data);
		}

		return $saved;
	}//end orderTasks()

	/**
	 * Put the project's lanes in the given order; lanes left out keep their place after them.
	 *
	 * @param string            $projectId The project UUID.
	 * @param array<int,string> $columnIds The lanes in their new order.
	 *
	 * @return array<int,array{id:string,order:int}>
	 *
	 * @throws ProjectActionException When the caller does not own the project or a lane is not in it.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
	 */
	public function orderColumns(string $projectId, array $columnIds): array {
		$project = $this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$this->assertManagesColumns(project: $project);
		$columns = $this->byId(schema: 'column', projectId: $projectId);
		if (array_diff($columnIds, array_keys($columns)) !== []) {
			throw new ProjectActionException(reason: 'planninq-invalid-order', status: 400, message: 'Each column must be a column of this project.');
		}

		$rest = array_diff(array_keys($columns), $columnIds);
		usort($rest, static fn ($one, $two): int => (int)($columns[$one]['order'] ?? 0) <=> (int)($columns[$two]['order'] ?? 0));
		$order = array_values(array_merge($columnIds, $rest));

		$this->support->asSystem(
			writes: function () use ($order, $columns): void {
				foreach ($order as $position => $id) {
					if ((int)($columns[$id]['order'] ?? -1) !== $position) {
						$this->support->save(schema: 'column', id: (string)$id, data: array_merge($columns[$id], ['order' => $position]));
					}
				}
			}
		);

		return array_map(static fn ($id, int $position): array => ['id' => (string)$id, 'order' => $position], $order, array_keys($order));
	}//end orderColumns()

	/**
	 * Remove a lane: its cards go to the bottom of another lane (or the backlog), then the lane is deleted.
	 *
	 * @param string      $columnId The lane to remove.
	 * @param string|null $targetId The lane its cards move to, null for the backlog.
	 *
	 * @return int The number of cards moved.
	 *
	 * @throws ProjectActionException When the caller does not own the project or the target is not a lane of it.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
	 */
	public function removeColumn(string $columnId, ?string $targetId): int {
		$column    = $this->support->readable(schema: 'column', id: $columnId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'column', data: $column);
		$this->assertManagesColumns(project: $this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId));
		$columns = $this->byId(schema: 'column', projectId: $projectId);
		$target  = null;
		if ($targetId !== null && $targetId !== '') {
			$target = ($columns[$targetId] ?? null);
			if ($target === null || $targetId === $columnId) {
				throw new ProjectActionException(reason: 'planninq-invalid-target', status: 400, message: 'Choose another column of this project.');
			}
		}

		$cards = $this->membership->rows(schema: 'task', filters: ['column' => $columnId]);
		$next  = $this->bottomOf(projectId: $projectId, columnId: $targetId);
		foreach ($cards as $card) {
			$next  += self::ORDER_STEP;
			$fields = ['column' => $targetId, 'columnOrder' => $next];
			$status = $this->statusOf(column: $target);
			if ($target === null) {
				$status = 'open';
			}

			if ($status !== null) {
				$fields['status'] = $status;
			}

			$this->support->saveAsCaller(schema: 'task', id: $card['id'], data: array_merge($card['data'], $fields));
		}

		$this->support->objectService()->deleteObject(uuid: $columnId, register: ProjectMembershipService::REGISTER, schema: 'column');

		return count($cards);
	}//end removeColumn()

	/**
	 * The status a lane gives its cards: its own, `done` for a done lane, or none.
	 *
	 * @param array<string,mixed>|null $column The lane.
	 *
	 * @return string|null
	 */
	private function statusOf(?array $column): ?string {
		if ($column === null) {
			return null;
		}

		if (is_string($column['status'] ?? null) === true && $column['status'] !== '') {
			return $column['status'];
		}

		if (($column['type'] ?? null) === 'done') {
			return 'done';
		}

		return null;
	}//end statusOf()

	/**
	 * The highest card position in a lane (or the backlog), 0 when it is empty.
	 *
	 * @param string      $projectId The project.
	 * @param string|null $columnId  The lane, null for the backlog.
	 *
	 * @return int
	 */
	private function bottomOf(string $projectId, ?string $columnId): int {
		$bottom = 0;
		foreach ($this->membership->rows(schema: 'task', filters: ['project' => $projectId]) as $task) {
			if (($task['data']['column'] ?? null) === $columnId) {
				$bottom = max($bottom, (int)($task['data']['columnOrder'] ?? 0));
			}
		}

		return $bottom;
	}//end bottomOf()

	/**
	 * Refuse unless the caller is the project owner or an admin, as for every lane change.
	 *
	 * @param array<string,mixed> $project The project.
	 *
	 * @return void
	 *
	 * @throws ProjectActionException When they are not.
	 */
	private function assertManagesColumns(array $project): void {
		$user = $this->support->caller();
		if ($this->support->isAdmin(user: $user) === false && ($project['owner'] ?? null) !== $user->getUID()) {
			throw new ProjectActionException(
				reason: 'planninq-not-column-owner',
				status: 403,
				message: 'Only the project owner can change the board columns.'
			);
		}
	}//end assertManagesColumns()

	/**
	 * A project's objects of one schema, keyed by id.
	 *
	 * @param string $schema    The schema slug.
	 * @param string $projectId The project.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function byId(string $schema, string $projectId): array {
		$rows = [];
		foreach ($this->membership->rows(schema: $schema, filters: ['project' => $projectId]) as $row) {
			$rows[(string)$row['id']] = $row['data'];
		}

		return $rows;
	}//end byId()
}//end class
