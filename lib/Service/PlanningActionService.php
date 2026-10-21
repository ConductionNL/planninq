<?php

/**
 * Planninq PlanningActionService
 *
 * The planning actions that write several objects at once, done on the
 * server: move a phase (every phase renumbered in one go), write a run of
 * task dates (all or none, blocking links checked), and add an action task
 * from a log entry (created and linked together).
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
 * @spec openspec/specs/projects.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectActionException;

/**
 * Server-side multi-object planning actions.
 *
 * @spec openspec/specs/projects.md
 */
class PlanningActionService {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads planninq objects as the system.
	 * @param ProjectActionSupport     $support    Checks the caller and writes as the system.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ProjectActionSupport $support,
	) {
	}//end __construct()

	/**
	 * Move a phase one place up (-1) or down (+1); every phase gets a distinct order in one go.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $phaseId   The phase to move.
	 * @param int    $step      -1 or +1.
	 *
	 * @return array<int,array{id:string,order:int}> The phases in their new order.
	 *
	 * @throws ProjectActionException When the caller does not work on the project or the phase is not in it.
	 *
	 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
	 */
	public function movePhase(string $projectId, string $phaseId, int $step): array {
		$this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$this->support->assertWrites(projectId: $projectId);
		$phases = $this->membership->rows(schema: 'projectPhase', filters: ['project' => $projectId]);
		usort(
			$phases,
			static fn (array $one, array $two): int => [(int)($one['data']['order'] ?? 0), $one['id']] <=> [(int)($two['data']['order'] ?? 0), $two['id']]
		);

		$index = array_search($phaseId, array_column($phases, 'id'), true);
		if ($index === false || in_array($step, [-1, 1], true) === false) {
			throw new ProjectActionException(reason: 'planninq-invalid-choice', status: 400, message: 'Choose a phase of this project and a direction.');
		}

		if (isset($phases[$index + $step]) === true) {
			[$phases[$index], $phases[$index + $step]] = [$phases[$index + $step], $phases[$index]];
		}

		$this->support->asSystem(
			writes: function () use ($phases): void {
				foreach ($phases as $order => $phase) {
					if ((int)($phase['data']['order'] ?? -1) !== $order) {
						$this->support->save(schema: 'projectPhase', id: $phase['id'], data: array_merge($phase['data'], ['order' => $order]));
					}
				}
			}
		);

		return array_map(static fn (array $phase, int $order): array => ['id' => $phase['id'], 'order' => $order], $phases, array_keys($phases));
	}//end movePhase()

	/**
	 * Write the dates of a run of tasks, as one action: the task a person moved and the tasks it pushed.
	 *
	 * Every task must be in the project and every blocking link the run
	 * touches must hold afterwards (the blocked task starts after its blocker
	 * is due); otherwise nothing is written.
	 *
	 * @param string                       $projectId The project UUID.
	 * @param array<int,array<string,mixed>> $moves   Each `{id, startDate, dueDate}`.
	 *
	 * @return int The number of tasks written.
	 *
	 * @throws ProjectActionException When the caller may not, or the run is invalid or breaks a link.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
	 */
	public function schedule(string $projectId, array $moves): int {
		$this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$this->support->assertWrites(projectId: $projectId);

		$tasks = [];
		foreach ($this->membership->rows(schema: 'task', filters: ['project' => $projectId]) as $row) {
			$tasks[$row['id']] = $row['data'];
		}

		$dates = $this->scheduleDates(moves: $moves, taskIds: array_keys($tasks));
		foreach ($dates as $id => $span) {
			$tasks[$id] = array_merge($tasks[$id], $span);
		}

		$this->assertLinksHold(tasks: $tasks, moved: array_keys($dates));
		$this->support->asSystem(
			writes: function () use ($dates, $tasks): void {
				foreach (array_keys($dates) as $id) {
					$this->support->save(schema: 'task', id: (string)$id, data: $tasks[$id]);
				}
			}
		);

		return count($dates);
	}//end schedule()

	/**
	 * The new dates per task, checked: a task of the project, two dates, start not after due.
	 *
	 * @param array<int,array<string,mixed>> $moves   The moves.
	 * @param array<int,string|int>          $taskIds The project's task ids.
	 *
	 * @return array<string,array{startDate:string,dueDate:string}>
	 *
	 * @throws ProjectActionException When a move is not valid.
	 */
	private function scheduleDates(array $moves, array $taskIds): array {
		$dates = [];
		foreach ($moves as $move) {
			$id    = (string)($move['id'] ?? '');
			$start = (string)($move['startDate'] ?? '');
			$due   = (string)($move['dueDate'] ?? '');
			$valid = in_array($id, array_map('strval', $taskIds), true) === true
				&& preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1
				&& preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) === 1
				&& $start <= $due;
			if ($valid === false) {
				throw new ProjectActionException(
					reason: 'planninq-invalid-schedule',
					status: 400,
					message: 'Each task needs a start and a due date in this project.'
				);
			}

			$dates[$id] = ['startDate' => $start, 'dueDate' => $due];
		}

		return $dates;
	}//end scheduleDates()

	/**
	 * Refuse a run after which a task it moves no longer finishes before the tasks it blocks start.
	 *
	 * @param array<string,array<string,mixed>> $tasks The project's tasks with the run applied.
	 * @param array<int,string|int>             $moved The ids the run moves.
	 *
	 * @return void
	 *
	 * @throws ProjectActionException When a blocked task would start before its blocker is due.
	 */
	private function assertLinksHold(array $tasks, array $moved): void {
		foreach ($this->membership->rows(schema: 'dependency', filters: ['blocker' => array_map('strval', $moved)]) as $edge) {
			$blocker = (string)($edge['data']['blocker'] ?? '');
			$blocked = (string)($edge['data']['blocked'] ?? '');
			if (($edge['data']['type'] ?? 'blocks') !== 'blocks' || isset($tasks[$blocker], $tasks[$blocked]) === false) {
				continue;
			}

			$due   = substr((string)($tasks[$blocker]['dueDate'] ?? ''), 0, 10);
			$start = substr((string)($tasks[$blocked]['startDate'] ?? ''), 0, 10);
			if ($due !== '' && $start !== '' && $start <= $due) {
				throw new ProjectActionException(
					reason: 'planninq-schedule-conflict',
					status: 409,
					message: 'A task would start before a task that blocks it is done.'
				);
			}
		}
	}//end assertLinksHold()

	/**
	 * Create an action task from a log entry and link it from the entry, as one action.
	 *
	 * The task is created with the caller's rights, so every task rule
	 * applies; the link is then written as the system. When the link cannot
	 * be written the task is removed again, so no unlinked action stays.
	 *
	 * @param string              $entryId The log entry UUID.
	 * @param array<string,mixed> $task    The task fields.
	 *
	 * @return array{task: array<string,mixed>, entry: array<string,mixed>}
	 *
	 * @throws ProjectActionException When the caller does not work on the entry's project.
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
	 */
	public function addAction(string $entryId, array $task): array {
		$entry     = $this->support->readable(schema: 'projectLogEntry', id: $entryId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'projectLogEntry', data: $entry);
		$this->support->assertWrites(projectId: $projectId);

		unset($task['@self'], $task['id']);
		$saved  = $this->support->objectService()->saveObject(
			object: array_merge($task, ['project' => $projectId, 'issueType' => 'action']),
			register: ProjectMembershipService::REGISTER,
			schema: 'task'
		);
		$taskId = (string)$saved->getUuid();

		try {
			$linked = $this->support->asSystem(
				writes: fn (): array => $this->support->save(
					schema: 'projectLogEntry',
					id: $entryId,
					data: array_merge($entry, ['actions' => array_values(array_merge((array)($entry['actions'] ?? []), [$taskId]))])
				)
			);
		} catch (\Throwable $e) {
			$this->support->asSystem(writes: fn () => $this->support->deleteAsSystem(schema: 'task', id: $taskId));
			throw $e;
		}

		return ['task' => ['id' => $taskId] + (array)$saved->getObject(), 'entry' => $linked];
	}//end addAction()
}//end class
