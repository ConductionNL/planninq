<?php

/**
 * Planninq Flow Replay
 *
 * Pure replay of a project's task history into the numbers the Flow tab
 * draws: for every day of a window the tasks per column at the end of that
 * day, and for every task finished in the window its lead and cycle time.
 * No I/O: FlowHistoryService reads the tasks, columns and audit trail rows
 * and hands them in.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Replays task histories into daily column counts and task timings.
 *
 * A history is the list OpenRegister's AuditTrailMapper::findChangesForObject()
 * returns: rows `{created: 'Y-m-d H:i:s', changed: {field: {old, new}}}`,
 * oldest first. Only `column`, `status` and `completedAt` are read.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */
class FlowReplay {

	/**
	 * The fields a flow reads from a history row.
	 *
	 * @var string[]
	 */
	public const FIELDS = ['column', 'status', 'completedAt'];

	/**
	 * How many of the slowest tasks a summary lists.
	 *
	 * @var int
	 */
	public const SLOWEST = 10;

	private const DAY = 86400;

	/**
	 * Replay a project's tasks over the window's days, both included.
	 *
	 * Each returned day holds the tasks per column at the end of that day (or
	 * at the window's `now` for today), and the timings of the tasks that finished on it.
	 *
	 * @param FlowBoard                                                                $board  The columns, tasks and their audit rows
	 * @param array{from: DateTimeImmutable, to: DateTimeImmutable, now: DateTimeImmutable} $window First and last day (UTC midnights), now
	 *
	 * @return array<string,array{counts: array<string,int>, finished: array<int,array<string,mixed>>, withoutHistory: int}> Keyed by Y-m-d
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function replay(FlowBoard $board, array $window): array {
		$columnIds = $this->orderedColumnIds(columns: $board->columns);
		$timelines = [];
		$timings = [];
		foreach ($board->tasks as $task) {
			$history = ($board->histories[(string)$task['id']] ?? []);
			$timelines[] = [
				'states' => $this->states(task: $task, history: $history),
				'noHistory' => ($history === []),
			];
			$timing = $this->timing(board: $board, task: $task);
			if ($timing !== null) {
				$timings[] = $timing;
			}
		}

		$replayed = ['columnIds' => $columnIds, 'timelines' => $timelines, 'timings' => $timings];
		$days     = [];
		for ($day = $window['from']; $day <= $window['to'] && $day <= $window['now']; $day = $day->modify('+1 day')) {
			$end = min($day->getTimestamp() + self::DAY - 1, $window['now']->getTimestamp());
			$days[$day->format('Y-m-d')] = $this->dayAt(
				replayed: $replayed,
				bounds: ['start' => $day->getTimestamp(), 'end' => $end]
			);
		}

		return $days;
	}//end replay()

	/**
	 * Average, 85th percentile and the slowest tasks of a set of timings.
	 *
	 * @param array<int,array<string,mixed>> $finished Timings from replay()
	 *
	 * @return array<string,mixed> finished, estimated, lead and cycle (average, p85), slowest
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function summarise(array $finished): array {
		$lead = array_map(static fn (array $row): float => (float)$row['leadDays'], $finished);
		$cycle = array_map(static fn (array $row): float => (float)$row['cycleDays'], $finished);
		$slowest = $finished;
		usort($slowest, static fn (array $a, array $b): int => $b['cycleDays'] <=> $a['cycleDays']);

		return [
			'finished' => count($finished),
			'estimated' => count(array_filter($finished, static fn (array $row): bool => $row['estimated'] === true)),
			'lead' => ['average' => $this->average(values: $lead), 'p85' => $this->percentile(values: $lead, rank: 0.85)],
			'cycle' => ['average' => $this->average(values: $cycle), 'p85' => $this->percentile(values: $cycle, rank: 0.85)],
			'slowest' => array_slice($slowest, 0, self::SLOWEST),
		];
	}//end summarise()

	/**
	 * Parse a timestamp from OpenRegister (`Y-m-d H:i:s` in UTC, or ISO 8601).
	 *
	 * @param mixed $value The raw value
	 *
	 * @return int|null Unix seconds, or null when empty or unreadable
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function seconds(mixed $value): ?int {
		if (is_string($value) === false || trim($value) === '') {
			return null;
		}

		try {
			return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
		} catch (\Exception $e) {
			return null;
		}
	}//end seconds()

	/**
	 * The project's column ids in board order.
	 *
	 * @param array<int,array<string,mixed>> $columns The columns
	 *
	 * @return string[]
	 */
	private function orderedColumnIds(array $columns): array {
		usort($columns, static fn (array $a, array $b): int => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));

		return array_values(array_map(static fn (array $column): string => (string)$column['id'], $columns));
	}//end orderedColumnIds()

	/**
	 * One day: tasks per column at its end, and the tasks finished in the day.
	 *
	 * @param array<string,array>         $replayed columnIds (board order), timelines (per task: states,
	 *                                              noHistory) and timings (finished tasks)
	 * @param array{start: int, end: int} $bounds   Day start, and day end or now (unix seconds)
	 *
	 * @return array{counts: array<string,int>, finished: array<int,array<string,mixed>>, withoutHistory: int}
	 */
	private function dayAt(array $replayed, array $bounds): array {
		$start  = $bounds['start'];
		$end    = $bounds['end'];
		$counts = array_fill_keys($replayed['columnIds'], 0);
		$withoutHistory = 0;
		foreach ($replayed['timelines'] as $timeline) {
			$column = $this->valueAt(states: $timeline['states'], field: 'column', moment: $end);
			if ($column === false || isset($counts[$column]) === false) {
				continue;
			}

			$counts[$column]++;
			if ($timeline['noHistory'] === true) {
				$withoutHistory++;
			}
		}

		$finished = array_values(
			array_filter(
				$replayed['timings'],
				static fn (array $row): bool => $row['finishedAt'] >= $start && $row['finishedAt'] <= $end
			)
		);

		return ['counts' => $counts, 'finished' => $finished, 'withoutHistory' => $withoutHistory];
	}//end dayAt()

	/**
	 * A field's value at a moment, or false when the task did not exist yet.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The task's states, oldest first
	 * @param string                                                $field  The field
	 * @param int                                                   $moment Unix seconds
	 *
	 * @return mixed
	 */
	private function valueAt(array $states, string $field, int $moment): mixed {
		$value = false;
		foreach ($states as $state) {
			if ($state['at'] > $moment) {
				break;
			}

			$value = ($state['values'][$field] ?? null);
		}

		return $value;
	}//end valueAt()

	/**
	 * A task's states over time: the first at its creation, then one per
	 * audit row that changed a flow field.
	 *
	 * The state before the first change of a field is that change's `old`
	 * value, so a history that starts after creation still replays. A task
	 * without any history keeps its current values from its creation on.
	 *
	 * @param array<string,mixed>            $task    The task
	 * @param array<int,array<string,mixed>> $history Its audit rows, oldest first
	 *
	 * @return array<int,array{at: int, values: array<string,mixed>}>
	 */
	private function states(array $task, array $history): array {
		$created = $this->createdAt(task: $task, history: $history);
		if ($history === []) {
			return [['at' => $created, 'values' => $this->flowValues(source: $task)]];
		}

		$states = [['at' => $created, 'values' => $this->initialValues(history: $history)]];
		foreach ($history as $row) {
			$next = $this->nextState(states: $states, row: $row);
			if ($next !== null) {
				$states[] = $next;
			}
		}

		return $states;
	}//end states()

	/**
	 * The state after one audit row, or null when the row changes no flow field.
	 *
	 * The row's moment is never before the task's creation, the moment of the first state.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The states so far
	 * @param array<string,mixed>                                    $row    The audit row
	 *
	 * @return array{at: int, values: array<string,mixed>}|null
	 */
	private function nextState(array $states, array $row): ?array {
		$changes = $this->flowChanges(changed: (array)($row['changed'] ?? []));
		if ($changes === []) {
			return null;
		}

		$values = array_merge(end($states)['values'], $changes);
		return ['at' => max($states[0]['at'], (int)$this->seconds(value: $row['created'] ?? null)), 'values' => $values];
	}//end nextState()

	/**
	 * The values a task had at its creation: per field, the old value of its first change, else null.
	 *
	 * @param array<int,array<string,mixed>> $history Its audit rows, oldest first
	 *
	 * @return array<string,mixed>
	 */
	private function initialValues(array $history): array {
		$values = array_fill_keys(self::FIELDS, null);
		$seen   = [];
		foreach ($history as $row) {
			foreach (self::FIELDS as $field) {
				if (isset($seen[$field]) === false && is_array($row['changed'][$field] ?? null) === true) {
					$values[$field] = ($row['changed'][$field]['old'] ?? null);
					$seen[$field]   = true;
				}
			}
		}

		return $values;
	}//end initialValues()

	/**
	 * The new values of the flow fields an audit row changed.
	 *
	 * @param array<string,mixed> $changed The row's `changed` map
	 *
	 * @return array<string,mixed>
	 */
	private function flowChanges(array $changed): array {
		$changes = [];
		foreach (self::FIELDS as $field) {
			if (is_array($changed[$field] ?? null) === true) {
				$changes[$field] = ($changed[$field]['new'] ?? null);
			}
		}

		return $changes;
	}//end flowChanges()

	/**
	 * The flow fields of a task as they are now.
	 *
	 * @param array<string,mixed> $source The task
	 *
	 * @return array<string,mixed>
	 */
	private function flowValues(array $source): array {
		$values = [];
		foreach (self::FIELDS as $field) {
			$values[$field] = ($source[$field] ?? null);
		}

		return $values;
	}//end flowValues()

	/**
	 * When the task was created: its own `created`, else its first audit row.
	 *
	 * @param array<string,mixed>            $task    The task
	 * @param array<int,array<string,mixed>> $history Its audit rows
	 *
	 * @return int Unix seconds
	 */
	private function createdAt(array $task, array $history): int {
		$created = $this->seconds(value: $task['created'] ?? null);
		if ($created === null) {
			$created = $this->seconds(value: $history[0]['created'] ?? null);
		}

		return (int)$created;
	}//end createdAt()

	/**
	 * Lead and cycle time of a finished task, or null when it is not finished.
	 *
	 * Finish is `completedAt`; on a done task without it, the last change to
	 * `done` in the history, and the task is "estimated". Cycle time starts
	 * when the task first sat in a column other than the project's first, or
	 * else first became `in_progress`; a task that went straight to done has
	 * a cycle time of zero.
	 *
	 * @param FlowBoard           $board The columns and the audit rows per task
	 * @param array<string,mixed> $task  The task
	 *
	 * @return array<string,mixed>|null
	 */
	private function timing(FlowBoard $board, array $task): ?array {
		$history = ($board->histories[(string)$task['id']] ?? []);
		$states  = $this->states(task: $task, history: $history);
		[$finished, $estimated] = $this->finish(task: $task, states: $states);
		if ($finished === null) {
			return null;
		}

		$created = $this->createdAt(task: $task, history: $history);
		$started = $this->start(states: $states, firstColumn: ($this->orderedColumnIds(columns: $board->columns)[0] ?? null));
		$moments = ['created' => $created, 'started' => min(($started ?? $finished), $finished), 'finished' => $finished, 'estimated' => $estimated];

		return $this->timingRow(task: $task, moments: $moments);
	}//end timing()

	/**
	 * When a task finished, and whether that moment is estimated from its history.
	 *
	 * @param array<string,mixed>                                    $task   The task
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states Its states
	 *
	 * @return array{0:int|null,1:bool} The moment, or null when it did not finish
	 */
	private function finish(array $task, array $states): array {
		$finished = $this->seconds(value: $task['completedAt'] ?? null);
		if ($finished === null && ($task['status'] ?? null) === 'done') {
			return [$this->lastMomentWhere(states: $states, test: static fn (array $values): bool => $values['status'] === 'done'), true];
		}

		return [$finished, false];
	}//end finish()

	/**
	 * When work on a task started: it first sat in a column other than the first, or else first became `in_progress`.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states      Its states
	 * @param string|null                                            $firstColumn The project's first column
	 *
	 * @return int|null
	 */
	private function start(array $states, ?string $firstColumn): ?int {
		$started = $this->firstMomentWhere(
			states: $states,
			test: static fn (array $values): bool => $values['column'] !== null && $values['column'] !== $firstColumn
		);
		if ($started === null) {
			$started = $this->firstMomentWhere(states: $states, test: static fn (array $values): bool => $values['status'] === 'in_progress');
		}

		return $started;
	}//end start()

	/**
	 * The timing row of a finished task.
	 *
	 * @param array<string,mixed>                                         $task    The task
	 * @param array{created:int,started:int,finished:int,estimated:bool} $moments Created, started and finished (Unix seconds), estimated
	 *
	 * @return array<string,mixed>
	 */
	private function timingRow(array $task, array $moments): array {
		return [
			'id' => (string)$task['id'],
			'projectId' => (string)($task['project'] ?? ''),
			'title' => (string)($task['title'] ?? ''),
			'key' => ($task['key'] ?? null),
			'createdAt' => gmdate('c', $moments['created']),
			'startedAt' => gmdate('c', $moments['started']),
			'finishedAt' => $moments['finished'],
			'leadDays' => round(max(0, $moments['finished'] - $moments['created']) / self::DAY, 2),
			'cycleDays' => round(max(0, $moments['finished'] - $moments['started']) / self::DAY, 2),
			'estimated' => $moments['estimated'],
		];
	}//end timingRow()

	/**
	 * The moment of the first state where the test turned true after being false.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The states
	 * @param callable                                              $test   Test on a state's values
	 *
	 * @return int|null
	 */
	private function firstMomentWhere(array $states, callable $test): ?int {
		return ($this->momentsWhere(states: $states, test: $test)[0] ?? null);
	}//end firstMomentWhere()

	/**
	 * The moment of the last state where the test turned true after being false.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The states
	 * @param callable                                              $test   Test on a state's values
	 *
	 * @return int|null
	 */
	private function lastMomentWhere(array $states, callable $test): ?int {
		$moments = $this->momentsWhere(states: $states, test: $test);
		if ($moments === []) {
			return null;
		}

		return $moments[count($moments) - 1];
	}//end lastMomentWhere()

	/**
	 * Every moment at which the test turned true after being false.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The states
	 * @param callable                                              $test   Test on a state's values
	 *
	 * @return int[]
	 */
	private function momentsWhere(array $states, callable $test): array {
		$moments = [];
		$before = false;
		foreach ($states as $state) {
			$now = (bool)$test($state['values']);
			if ($now === true && $before === false) {
				$moments[] = $state['at'];
			}

			$before = $now;
		}

		return $moments;
	}//end momentsWhere()

	/**
	 * The mean of a list, two decimals; 0 for an empty list.
	 *
	 * @param float[] $values The values
	 *
	 * @return float
	 */
	private function average(array $values): float {
		if ($values === []) {
			return 0.0;
		}

		return round(array_sum($values) / count($values), 2);
	}//end average()

	/**
	 * The nearest-rank percentile of a list; 0 for an empty list.
	 *
	 * @param float[] $values The values
	 * @param float   $rank   The rank, 0 to 1
	 *
	 * @return float
	 */
	private function percentile(array $values, float $rank): float {
		if ($values === []) {
			return 0.0;
		}

		sort($values);
		$index = max(0, (int)ceil($rank * count($values)) - 1);

		return round($values[$index], 2);
	}//end percentile()
}//end class
