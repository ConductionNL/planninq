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
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-6.1
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
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-6.1
	 */
	public function importInto(string $id): array {
		$scenario = $this->importable(id: $id);
		$week     = $this->weekOf(sessions: $this->weekLessons(scenario: $scenario));
		$scenario = $this->imported(scenario: $scenario, week: $week);
		$this->store->save(id: $id, data: $scenario);

		return $scenario;
	}//end importInto()

	/**
	 * The stored scenario, when it is an imported one that is not published.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws InvalidArgumentException When it does not exist, is not imported, or is published.
	 */
	private function importable(string $id): array {
		$scenario = $this->store->load(id: $id);
		if ($scenario === null || ($scenario['source'] ?? '') !== 'imported' || ($scenario['status'] ?? '') === 'published') {
			throw new InvalidArgumentException('Only an imported scenario that is not published can take the current timetable.');
		}

		return $scenario;
	}//end importable()

	/**
	 * The scheduled lessons of the scenario's week, Monday to the end of Sunday.
	 *
	 * @param array<string,mixed> $scenario The scenario.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @throws InvalidArgumentException When the week has no scheduled lessons.
	 */
	private function weekLessons(array $scenario): array {
		$monday   = $this->monday(date: (string)($scenario['weekOf'] ?? ''));
		$sessions = $this->store->scheduledLessons(
			from: $monday->format(DATE_ATOM),
			to: $monday->modify('+7 days')->modify('-1 second')->format(DATE_ATOM)
		);
		if ($sessions === []) {
			throw new InvalidArgumentException('The week of this scenario has no scheduled lessons.');
		}

		return $sessions;
	}//end weekLessons()

	/**
	 * The scenario filled from its week: the solver input with the stored
	 * wishes, the placements, what could not be placed, and the score, done.
	 *
	 * @param array<string,mixed> $scenario The scenario.
	 * @param array<string,mixed> $week     The week, as weekOf() reads it.
	 *
	 * @return array<string,mixed>
	 */
	private function imported(array $scenario, array $week): array {
		$input = $this->weekInput(week: $week);
		$score = $this->scorer->score(input: $input, placements: $week['placements']);

		return array_merge(
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
	}//end imported()

	/**
	 * The solver input of the week's lessons and rooms, with the stored wishes.
	 *
	 * @param array<string,mixed> $week The week, as weekOf() reads it.
	 *
	 * @return SolverInput
	 */
	private function weekInput(array $week): SolverInput {
		$wishes = array_map(fn (array $wish): array => $this->inputBuilder->solverWish(wish: $wish), $this->store->wishes());

		return new SolverInput(
			periods: $this->grid->periodKeys(),
			rooms: $week['rooms'],
			lessons: $week['lessons'],
			wishes: $wishes,
			source: 'imported'
		);
	}//end weekInput()

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
		$span = $this->lessonSpan(session: $session);
		if ($span === null) {
			return null;
		}

		$covered = array_values(array_filter($periods, fn (array $period): bool => $this->coversPeriod(period: $period, span: $span)));
		if ($covered === [] || $covered[0]['start'] !== $span[0]->format('H:i')) {
			return null;
		}

		return ['period' => $covered[0]['day'].'-'.$covered[0]['number'], 'length' => count($covered)];
	}//end slotOf()

	/**
	 * The start and end of a stored lesson, or null when either cannot be read.
	 *
	 * @param array<string,mixed> $session The stored lesson.
	 *
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable}|null
	 */
	private function lessonSpan(array $session): ?array {
		try {
			return [new DateTimeImmutable((string)($session['startsAt'] ?? '')), new DateTimeImmutable((string)($session['endsAt'] ?? ''))];
		} catch (Throwable) {
			return null;
		}
	}//end lessonSpan()

	/**
	 * Whether a period lies on the lesson's day, inside its start and end.
	 *
	 * @param array{day:string,number:int,start:string,end:string} $period The period.
	 * @param array{0:DateTimeImmutable,1:DateTimeImmutable}      $span   The lesson's start and end.
	 *
	 * @return bool
	 */
	private function coversPeriod(array $period, array $span): bool {
		return $period['day'] === substr(strtolower($span[0]->format('D')), 0, 3)
			&& $period['start'] >= $span[0]->format('H:i')
			&& $period['end'] <= $span[1]->format('H:i');
	}//end coversPeriod()

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
