<?php

/**
 * Planninq Timetable Scenario Publisher
 *
 * Writes a scenario's week pattern out as draft lessons for every week of its
 * window through the existing upsert (design decision 7), so the draft review
 * and the publish endpoint apply unchanged.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Publish a scenario as draft lessons.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-8.1
 */
class TimetableScenarioPublisher {

	/**
	 * The source system the generator's lessons are stored under.
	 */
	public const SOURCE = 'planninq-generator';

	/**
	 * The days of the week in grid order, as offsets from Monday.
	 */
	private const DAY_OFFSETS = ['mon' => 0, 'tue' => 1, 'wed' => 2, 'thu' => 3, 'fri' => 4, 'sat' => 5, 'sun' => 6];

	/**
	 * Constructor.
	 *
	 * @param TimetableScenarioStore  $store    Reads the scenario and the generator's lessons.
	 * @param TimetableSessionService $sessions Upserts the draft lessons.
	 * @param TimetableGridService    $grid     The period times.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly TimetableScenarioStore $store,
		private readonly TimetableSessionService $sessions,
		private readonly TimetableGridService $grid,
	) {
	}//end __construct()

	/**
	 * Write the scenario's placements as draft lessons for every week of the window.
	 *
	 * Drafts from the generator in the window that belong to another scenario
	 * are removed first; a lesson of the window the generator already
	 * published refuses the whole publish.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array{created:int,updated:int,unchanged:int,removed:int,rejected:int}
	 *
	 * @throws InvalidArgumentException When the scenario cannot be published.
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-8.1
	 */
	public function publishDrafts(string $id): array {
		$scenario    = $this->publishable(id: $id);
		[$from, $to] = $this->window(scenario: $scenario);
		$existing    = $this->store->lessonsFrom(source: self::SOURCE, from: $from->format(DATE_ATOM), to: $to->format(DATE_ATOM));
		$this->assertNotPublished(lessons: $existing);
		$removed = $this->removeOtherDrafts(id: $id, lessons: $existing);

		$rows   = $this->rows(run: new ScenarioDraftRun(id: $id, scenario: $scenario), window: [$from, $to]);
		$result = $this->sessions->upsert(sourceSystem: self::SOURCE, sessions: $rows);
		$this->store->save(id: $id, data: array_merge($scenario, ['status' => 'published', 'publishedAt' => (new DateTimeImmutable())->format(DATE_ATOM)]));

		return [
			'created'   => (int)($result['created'] ?? 0),
			'updated'   => (int)($result['updated'] ?? 0),
			'unchanged' => (int)($result['unchanged'] ?? 0),
			'removed'   => $removed,
			'rejected'  => count((array)($result['rejected'] ?? [])),
		];
	}//end publishDrafts()

	/**
	 * The scenario, when it is a finished generated scenario with placements.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws InvalidArgumentException When the scenario cannot be published.
	 */
	private function publishable(string $id): array {
		$scenario = $this->store->load(id: $id);
		if ($scenario === null
			|| ($scenario['source'] ?? '') !== 'generated'
			|| in_array(($scenario['status'] ?? ''), ['done', 'published'], true) === false
			|| ($scenario['placements'] ?? []) === []
		) {
			throw new InvalidArgumentException('Only a finished generated scenario with placements can be published.');
		}

		return $scenario;
	}//end publishable()

	/**
	 * Refuse the publish when the generator already published a lesson of the window.
	 *
	 * @param array<int,array<string,mixed>> $lessons The generator's lessons of the window.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When one of them is scheduled.
	 */
	private function assertNotPublished(array $lessons): void {
		foreach ($lessons as $lesson) {
			if (($lesson['status'] ?? '') === 'scheduled') {
				throw new InvalidArgumentException('Lessons of this window are already published from the generator. Nothing was written.');
			}
		}
	}//end assertNotPublished()

	/**
	 * Delete the generator's drafts of the window that belong to another scenario.
	 *
	 * @param string                         $id      The scenario id.
	 * @param array<int,array<string,mixed>> $lessons The generator's lessons of the window.
	 *
	 * @return int How many were deleted.
	 */
	private function removeOtherDrafts(string $id, array $lessons): int {
		$removed = 0;
		foreach ($lessons as $lesson) {
			if (str_starts_with((string)($lesson['externalRef'] ?? ''), $id.':') === false) {
				$this->store->deleteLesson(id: (string)$lesson['id']);
				$removed++;
			}
		}

		return $removed;
	}//end removeOtherDrafts()

	/**
	 * The draft lesson rows of every week of the window, in the upsert contract's shape.
	 *
	 * @param ScenarioDraftRun                               $run    The scenario being written.
	 * @param array{0:DateTimeImmutable,1:DateTimeImmutable} $window The first day and the end of the last day.
	 *
	 * @return array<int,array<string,string>>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-8.1
	 */
	private function rows(ScenarioDraftRun $run, array $window): array {
		$run->lessons = array_column((array)($run->scenario['input']['lessons'] ?? []), null, 'key');
		$run->times   = array_column($this->grid->periodTimes(), null, 'key');
		$rows         = [];
		for ($monday = $this->monday(date: $window[0]); $monday <= $window[1]; $monday = $monday->modify('+7 days')) {
			$run->monday = $monday;
			foreach ((array)$run->scenario['placements'] as $placement) {
				$row = $this->row(run: $run, placement: $placement);
				if ($this->inWindow(row: $row, window: $window) === true) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}//end rows()

	/**
	 * Whether a draft lesson starts inside the window.
	 *
	 * @param array<string,string>|null                         $row    The lesson, or null when it could not be built.
	 * @param array{0:DateTimeImmutable,1:DateTimeImmutable} $window The first day and the end of the last day.
	 *
	 * @return bool
	 */
	private function inWindow(?array $row, array $window): bool {
		return $row !== null && $row['startsAt'] >= $window[0]->format(DATE_ATOM) && $row['startsAt'] <= $window[1]->format(DATE_ATOM);
	}//end inWindow()

	/**
	 * One draft lesson of the run's week, or null when the lesson or its period is unknown.
	 *
	 * @param ScenarioDraftRun     $run       The scenario being written.
	 * @param array<string,string> $placement The placement.
	 *
	 * @return array<string,string>|null
	 */
	private function row(ScenarioDraftRun $run, array $placement): ?array {
		$lesson = ($run->lessons[$placement['lesson']] ?? null);
		$first  = ($run->times[$placement['period']] ?? null);
		if ($lesson === null || $first === null || $run->monday === null) {
			return null;
		}

		[$day, $number] = explode('-', $placement['period'], 2);
		$last = ($run->times[$day.'-'.((int)$number + max(1, (int)($lesson['length'] ?? 1)) - 1)] ?? $first);
		$date = $run->monday->modify('+'.self::DAY_OFFSETS[$day].' days');
		$row  = [
			'externalRef'    => $run->id.':'.$lesson['key'].':'.$date->format('Y-m-d'),
			'subject'        => (string)$lesson['subject'],
			'title'          => (string)$lesson['subject'].' '.(string)$lesson['group'],
			'startsAt'       => $this->atTime(date: $date, time: $first['start']),
			'endsAt'         => $this->atTime(date: $date, time: $last['end']),
			'groupReference' => (string)$lesson['group'],
			'teacherUserId'  => (string)$lesson['teacher'],
			'roomReference'  => (string)$placement['room'],
			'status'         => 'draft',
		];

		return array_filter($row, static fn (string $value): bool => $value !== '');
	}//end row()

	/**
	 * A date at a time of day, as ISO 8601 in the server's time zone.
	 *
	 * @param DateTimeImmutable $date The date.
	 * @param string            $time The time, as in `08:30`.
	 *
	 * @return string
	 */
	private function atTime(DateTimeImmutable $date, string $time): string {
		[$hour, $minute] = array_map('intval', explode(':', $time, 2));
		return $date->setTime($hour, $minute)->format(DATE_ATOM);
	}//end atTime()

	/**
	 * The window of a scenario: its first day at midnight and its last day just before midnight.
	 *
	 * @param array<string,mixed> $scenario The scenario.
	 *
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
	 */
	private function window(array $scenario): array {
		$from = (string)($scenario['windowFrom'] ?? '');
		$to   = (string)($scenario['windowTo'] ?? '');
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1 || $to < $from) {
			throw new InvalidArgumentException('The scenario needs a window with a start before its end.');
		}

		return [new DateTimeImmutable($from.' 00:00:00'), new DateTimeImmutable($to.' 23:59:59')];
	}//end window()

	/**
	 * The Monday of a date's week, at midnight.
	 *
	 * @param DateTimeImmutable $date The date.
	 *
	 * @return DateTimeImmutable
	 */
	private function monday(DateTimeImmutable $date): DateTimeImmutable {
		return $date->modify('monday this week')->setTime(0, 0);
	}//end monday()
}//end class
