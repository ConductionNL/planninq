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

		$result = $this->sessions->upsert(sourceSystem: self::SOURCE, sessions: $this->rows(id: $id, scenario: $scenario, from: $from, to: $to));
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
	 * @param string              $id       The scenario id.
	 * @param array<string,mixed> $scenario The scenario.
	 * @param DateTimeImmutable   $from     The first day of the window.
	 * @param DateTimeImmutable   $to       The end of the last day of the window.
	 *
	 * @return array<int,array<string,string>>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-8.1
	 */
	public function rows(string $id, array $scenario, DateTimeImmutable $from, DateTimeImmutable $to): array {
		$lessons = array_column((array)($scenario['input']['lessons'] ?? []), null, 'key');
		$times   = array_column($this->grid->periodTimes(), null, 'key');
		$rows    = [];
		for ($monday = $this->monday(date: $from); $monday <= $to; $monday = $monday->modify('+7 days')) {
			foreach ((array)$scenario['placements'] as $placement) {
				$row = $this->row(id: $id, lesson: ($lessons[$placement['lesson']] ?? null), placement: $placement, monday: $monday, times: $times);
				if ($this->inWindow(row: $row, window: [$from, $to]) === true) {
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
	 * One draft lesson, or null when the lesson or its period is unknown.
	 *
	 * @param string                                  $id        The scenario id.
	 * @param array<string,mixed>|null                $lesson    The lesson.
	 * @param array<string,string>                    $placement The placement.
	 * @param DateTimeImmutable                       $monday    The Monday of the week.
	 * @param array<string,array{start:string,end:string}> $times     Period times by key.
	 *
	 * @return array<string,string>|null
	 */
	private function row(string $id, ?array $lesson, array $placement, DateTimeImmutable $monday, array $times): ?array {
		$first = ($times[$placement['period']] ?? null);
		if ($lesson === null || $first === null) {
			return null;
		}

		[$day, $number] = explode('-', $placement['period'], 2);
		$last = ($times[$day.'-'.((int)$number + max(1, (int)($lesson['length'] ?? 1)) - 1)] ?? $first);
		$date = $monday->modify('+'.self::DAY_OFFSETS[$day].' days');
		$row  = [
			'externalRef'    => $id.':'.$lesson['key'].':'.$date->format('Y-m-d'),
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
