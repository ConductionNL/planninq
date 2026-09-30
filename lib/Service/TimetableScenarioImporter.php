<?php

/**
 * Planninq Timetable Scenario Importer
 *
 * Fills an imported scenario from the current timetable: the scheduled lessons
 * of the scenario's first week become the lessons and placements of a week
 * pattern, scored with the same scorer as a generated run, so compare reads
 * both the same way and a broken hard wish is counted, not hidden.
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
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableScorer;
use Throwable;

/**
 * Snapshot the current timetable into a scenario.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
 */
class TimetableScenarioImporter {

	/**
	 * Constructor.
	 *
	 * @param TimetableScenarioStore $store        Reads the lessons and wishes, writes the scenario.
	 * @param TimetableGridService   $grid         The week grid.
	 * @param TimetableInputBuilder  $inputBuilder Shapes the wishes as the solver reads them.
	 * @param TimetableScorer        $scorer       Scores the week.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly TimetableScenarioStore $store,
		private readonly TimetableGridService $grid,
		private readonly TimetableInputBuilder $inputBuilder,
		private readonly TimetableScorer $scorer=new TimetableScorer(),
	) {
	}//end __construct()

	/**
	 * Fill an imported scenario from the scheduled lessons of its first week, and store it as done.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array<string,mixed> The stored scenario.
	 *
	 * @throws InvalidArgumentException When the scenario does not exist, is not imported, or its week has no lessons.
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
	 */
	public function importInto(string $id): array {
		$scenario = $this->store->load(id: $id);
		if ($scenario === null || ($scenario['source'] ?? '') !== 'imported' || ($scenario['status'] ?? '') === 'published') {
			throw new InvalidArgumentException('Only an imported scenario that is not published can take the current timetable.');
		}

		$monday   = $this->monday(date: (string)($scenario['weekOf'] ?? ''));
		$sessions = $this->store->scheduledLessons(
			from: $monday->format(DATE_ATOM),
			to: $monday->modify('+7 days')->modify('-1 second')->format(DATE_ATOM)
		);
		if ($sessions === []) {
			throw new InvalidArgumentException('The week of this scenario has no scheduled lessons.');
		}

		$week   = $this->weekOf(sessions: $sessions);
		$wishes = array_map(fn (array $wish): array => $this->inputBuilder->solverWish(wish: $wish), $this->store->wishes());
		$input  = new SolverInput(
			periods: $this->grid->periodKeys(),
			rooms: $week['rooms'],
			lessons: $week['lessons'],
			wishes: $wishes,
			source: 'imported'
		);
		$score  = $this->scorer->score(input: $input, placements: $week['placements']);

		$scenario = array_merge(
			$scenario,
			[
				'status'       => 'done',
				'input'        => $input->toArray(),
				'placements'   => $week['placements'],
				'unplaced'     => $week['unplaced'],
				'brokenWishes' => $score['brokenWishes'],
				'metrics'      => array_merge($score['metrics'], ['cost' => $this->scorer->costOf(metrics: $score['metrics'])]),
				'reason'       => '',
			]
		);
		$this->store->save(id: $id, data: $scenario);

		return $scenario;
	}//end importInto()

	/**
	 * The lessons, rooms and placements of one week of stored lessons; a lesson whose times
	 * are not periods of the grid is kept as unplaced with the reason `offGrid`.
	 *
	 * @param array<int,array<string,mixed>> $sessions The stored lessons.
	 *
	 * @return array{lessons:array<int,array<string,mixed>>,rooms:array<int,array<string,mixed>>,placements:array<int,array<string,string>>,unplaced:array<int,array<string,mixed>>}
	 */
	private function weekOf(array $sessions): array {
		$periods = $this->grid->periodTimes();
		$counts  = [];
		$week    = ['lessons' => [], 'rooms' => [], 'placements' => [], 'unplaced' => []];
		foreach ($sessions as $session) {
			$group    = (string)($session['groupReference'] ?? ($session['cohortId'] ?? ''));
			$subject  = (string)($session['subject'] ?? '');
			$activity = $group.':'.$subject;
			$counts[$activity] = (($counts[$activity] ?? 0) + 1);
			$slot = $this->slotOf(session: $session, periods: $periods);
			$room = (string)($session['roomReference'] ?? '');
			$week['lessons'][] = [
				'key'      => $activity.':'.$counts[$activity],
				'activity' => $activity,
				'group'    => $group,
				'subject'  => $subject,
				'teacher'  => (string)($session['teacherUserId'] ?? ($session['teacherReference'] ?? '')),
				'roomType' => '',
				'length'   => ($slot['length'] ?? 1),
			];
			$week['rooms'][$room] = ['reference' => $room, 'capacity' => 0, 'type' => ''];
			if ($slot === null) {
				$week['unplaced'][] = ['lesson' => $activity.':'.$counts[$activity], 'wish' => null, 'reason' => 'offGrid'];
				continue;
			}

			$week['placements'][] = ['lesson' => $activity.':'.$counts[$activity], 'period' => $slot['period'], 'room' => $room];
		}//end foreach

		$week['rooms'] = array_values($week['rooms']);
		return $week;
	}//end weekOf()

	/**
	 * The period key and length of a stored lesson, or null when it does not start and end on period times.
	 *
	 * @param array<string,mixed>                                   $session The stored lesson.
	 * @param array<int,array{day:string,number:int,start:string,end:string}> $periods The grid's periods.
	 *
	 * @return array{period:string,length:int}|null
	 */
	private function slotOf(array $session, array $periods): ?array {
		try {
			$starts = new DateTimeImmutable((string)($session['startsAt'] ?? ''));
			$ends   = new DateTimeImmutable((string)($session['endsAt'] ?? ''));
		} catch (Throwable) {
			return null;
		}

		$day    = strtolower($starts->format('D'));
		$first  = null;
		$length = 0;
		foreach ($periods as $period) {
			if ($period['day'] !== substr($day, 0, 3) || $period['start'] < $starts->format('H:i') || $period['end'] > $ends->format('H:i')) {
				continue;
			}

			$first = ($first ?? $period);
			$length++;
		}

		if ($first === null || $first['start'] !== $starts->format('H:i')) {
			return null;
		}

		return ['period' => $first['day'].'-'.$first['number'], 'length' => $length];
	}//end slotOf()

	/**
	 * The Monday of a date's week, at midnight.
	 *
	 * @param string $date A date.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws InvalidArgumentException When it is not a date.
	 */
	private function monday(string $date): DateTimeImmutable {
		try {
			$day = new DateTimeImmutable($date);
		} catch (Throwable) {
			throw new InvalidArgumentException('The scenario has no week to take the lessons from.');
		}

		return $day->modify('monday this week')->setTime(0, 0);
	}//end monday()
}//end class
