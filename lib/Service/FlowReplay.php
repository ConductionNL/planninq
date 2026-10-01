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
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
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
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
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
	 * Replay a project's tasks over the days `$from` to `$to` (inclusive).
	 *
	 * Each returned day holds the tasks per column at the end of that day (or
	 * at `$now` for today), and the timings of the tasks that finished on it.
	 *
	 * @param array<int,array<string,mixed>> $columns   The project's columns (id, order)
	 * @param array<int,array<string,mixed>> $tasks     Tasks: id, title, key, created, column, status, completedAt
	 * @param array<string,array<int,array>> $histories Task id to its audit rows, oldest first
	 * @param DateTimeImmutable              $from      First day (UTC midnight)
	 * @param DateTimeImmutable              $to        Last day (UTC midnight)
	 * @param DateTimeImmutable              $now       The current moment; later days are not replayed
	 *
	 * @return array<string,array{counts: array<string,int>, finished: array<int,array<string,mixed>>, withoutHistory: int}> Keyed by Y-m-d
	 *
	 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function replay(
		array $columns,
		array $tasks,
		array $histories,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		DateTimeImmutable $now
	): array {
		$columnIds = $this->orderedColumnIds(columns: $columns);
		$firstColumn = ($columnIds[0] ?? null);
		$timelines = [];
		$timings = [];
		foreach ($tasks as $task) {
			$history = ($histories[(string)$task['id']] ?? []);
			$timelines[] = [
				'states' => $this->states(task: $task, history: $history),
				'noHistory' => ($history === []),
			];
			$timing = $this->timing(task: $task, history: $history, firstColumn: $firstColumn);
			if ($timing !== null) {
				$timings[] = $timing;
			}
		}

		$days = [];
		for ($day = $from; $day <= $to && $day <= $now; $day = $day->modify('+1 day')) {
			$end = min($day->getTimestamp() + self::DAY - 1, $now->getTimestamp());
			$days[$day->format('Y-m-d')] = $this->dayAt(
				columnIds: $columnIds,
				timelines: $timelines,
				timings: $timings,
				start: $day->getTimestamp(),
				end: $end
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
	 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
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
	 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
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
	 * One day: tasks per column at `$end`, and the tasks finished in the day.
	 *
	 * @param string[]                       $columnIds The columns in board order
	 * @param array<int,array<string,mixed>> $timelines Per task: states and noHistory
	 * @param array<int,array<string,mixed>> $timings   Finished tasks' timings
	 * @param int                            $start     Day start (unix seconds)
	 * @param int                            $end       Day end or now (unix seconds)
	 *
	 * @return array{counts: array<string,int>, finished: array<int,array<string,mixed>>, withoutHistory: int}
	 */
	private function dayAt(array $columnIds, array $timelines, array $timings, int $start, int $end): array {
		$counts = array_fill_keys($columnIds, 0);
		$withoutHistory = 0;
		foreach ($timelines as $timeline) {
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
				$timings,
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

		$values = array_fill_keys(self::FIELDS, null);
		$seen = [];
		foreach ($history as $row) {
			foreach (self::FIELDS as $field) {
				if (isset($seen[$field]) === false && is_array($row['changed'][$field] ?? null) === true) {
					$values[$field] = ($row['changed'][$field]['old'] ?? null);
					$seen[$field] = true;
				}
			}
		}

		$states = [['at' => $created, 'values' => $values]];
		foreach ($history as $row) {
			$changes = $this->flowChanges(changed: (array)($row['changed'] ?? []));
			if ($changes === []) {
				continue;
			}

			$values = array_merge($values, $changes);
			$states[] = ['at' => max($created, (int)$this->seconds(value: $row['created'] ?? null)), 'values' => $values];
		}

		return $states;
	}//end states()

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
	 * @param array<string,mixed>            $task        The task
	 * @param array<int,array<string,mixed>> $history     Its audit rows
	 * @param string|null                    $firstColumn The project's first column
	 *
	 * @return array<string,mixed>|null
	 */
	private function timing(array $task, array $history, ?string $firstColumn): ?array {
		$states = $this->states(task: $task, history: $history);
		$finished = $this->seconds(value: $task['completedAt'] ?? null);
		$estimated = false;
		if ($finished === null && ($task['status'] ?? null) === 'done') {
			$finished = $this->firstMomentWhere(states: $states, test: static fn (array $values): bool => $values['status'] === 'done', last: true);
			$estimated = true;
		}

		if ($finished === null) {
			return null;
		}

		$created = $this->createdAt(task: $task, history: $history);
		$started = $this->firstMomentWhere(
			states: $states,
			test: static fn (array $values): bool => $values['column'] !== null && $values['column'] !== $firstColumn
		);
		if ($started === null) {
			$started = $this->firstMomentWhere(states: $states, test: static fn (array $values): bool => $values['status'] === 'in_progress');
		}

		$started = min(($started ?? $finished), $finished);

		return [
			'id' => (string)$task['id'],
			'projectId' => (string)($task['project'] ?? ''),
			'title' => (string)($task['title'] ?? ''),
			'key' => ($task['key'] ?? null),
			'createdAt' => gmdate('c', $created),
			'startedAt' => gmdate('c', $started),
			'finishedAt' => $finished,
			'leadDays' => round(max(0, $finished - $created) / self::DAY, 2),
			'cycleDays' => round(max(0, $finished - $started) / self::DAY, 2),
			'estimated' => $estimated,
		];
	}//end timing()

	/**
	 * The moment of the first (or, with `$last`, the last) state where the
	 * test turned true after being false.
	 *
	 * @param array<int,array{at: int, values: array<string,mixed>}> $states The states
	 * @param callable                                              $test   Test on a state's values
	 * @param bool                                                  $last   Return the last such moment
	 *
	 * @return int|null
	 */
	private function firstMomentWhere(array $states, callable $test, bool $last = false): ?int {
		$found = null;
		$before = false;
		foreach ($states as $state) {
			$now = (bool)$test($state['values']);
			if ($now === true && $before === false) {
				$found = $state['at'];
				if ($last === false) {
					return $found;
				}
			}

			$before = $now;
		}

		return $found;
	}//end firstMomentWhere()

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
