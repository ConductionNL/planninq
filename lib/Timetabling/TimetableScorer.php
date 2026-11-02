<?php

/**
 * Planninq Timetable Scorer
 *
 * Measures one week of placed lessons: clashes, hard and soft wishes broken,
 * teacher gaps, lessons per day and room use. The solver optimises on cost()
 * and compare shows the same metrics, so the numbers match.
 *
 * @category Timetabling
 * @package  OCA\Planninq\Timetabling
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

namespace OCA\Planninq\Timetabling;

/**
 * Scores placements (lesson key, first period key, room reference) against a SolverInput.
 *
 * Clash kinds: `teacher`, `group` and `room` (two lessons in one period),
 * `roomType` (a room of another type than the lesson needs), `unknownRoom`
 * and `outOfGrid` (a lesson that starts outside the grid or runs off the day).
 * A broken soft wish costs its weight (1 when unset) for every lesson that breaks it.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
 */
final class TimetableScorer {

	/**
	 * Cost of one clash; above everything else, so no clash is traded for a wish.
	 */
	public const CLASH_COST = 1000000;

	/**
	 * Cost of one broken hard wish.
	 */
	public const HARD_COST = 100000;

	/**
	 * Cost of one lesson that is not placed.
	 */
	public const UNPLACED_COST = 10000;

	/**
	 * Cost of one point of soft penalty.
	 */
	public const SOFT_COST = 10;

	/**
	 * Cost of one free period between a teacher's lessons.
	 */
	public const GAP_COST = 1;

	/**
	 * Constructor.
	 *
	 * @param WishChecker $wishes Evaluates the wishes.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly WishChecker $wishes=new WishChecker(),
	) {
	}//end __construct()

	/**
	 * The score of a week.
	 *
	 * @param SolverInput                                     $input      What was to be placed.
	 * @param array<int,array{lesson:string,period:string,room:string}> $placements Where the lessons are.
	 *
	 * @return array{metrics:array<string,mixed>,brokenWishes:array<int,array<string,mixed>>,clashes:array<int,array<string,mixed>>}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function score(SolverInput $input, array $placements): array {
		$run     = new ScoringRun(input: $input);
		$laid    = $this->slots(run: $run, placements: $placements);
		$slots   = $laid['slots'];
		$clashes = array_merge($laid['clashes'], $this->doubleBookings(slots: $slots));
		$broken  = $this->brokenWishes(run: $run, slots: $slots);

		$metrics = array_merge(
			[
				'lessons'  => count($input->lessons),
				'placed'   => count($slots),
				'unplaced' => (count($input->lessons) - count($slots)),
				'clashes'  => count($clashes),
			],
			$this->wishMetrics(broken: $broken),
			$this->teacherGaps(slots: $slots, grid: $run->grid),
			['lessonsPerDayWorst' => $this->lessonsPerDayWorst(slots: $slots)],
			$this->roomUse(input: $input, slots: $slots)
		);

		return ['metrics' => $metrics, 'brokenWishes' => $broken, 'clashes' => $clashes];
	}//end score()

	/**
	 * One number to minimise: clashes, then hard wishes, then unplaced lessons, then soft wishes, then gaps.
	 *
	 * @param array<string,mixed> $metrics The metrics of score().
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public static function cost(array $metrics): int {
		return ((int)$metrics['clashes'] * self::CLASH_COST)
			+ ((int)$metrics['hardWishesBroken'] * self::HARD_COST)
			+ ((int)$metrics['unplaced'] * self::UNPLACED_COST)
			+ ((int)$metrics['softPenalty'] * self::SOFT_COST)
			+ ((int)$metrics['teacherGaps'] * self::GAP_COST);
	}//end cost()

	/**
	 * The same number as cost(), for callers that hold a scorer.
	 *
	 * @param array<string,mixed> $metrics The metrics of score().
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function costOf(array $metrics): int {
		return self::cost(metrics: $metrics);
	}//end costOf()

	/**
	 * The placed lessons with the periods they take; a placement that cannot be laid out is a clash.
	 *
	 * @param ScoringRun                      $run        The input and its lookups.
	 * @param array<int,array<string,string>> $placements The placements.
	 *
	 * @return array{slots:list<array<string,mixed>>,clashes:list<array<string,mixed>>}
	 */
	private function slots(ScoringRun $run, array $placements): array {
		$laid = ['slots' => [], 'clashes' => []];
		foreach ($placements as $placement) {
			$one = $this->layOut(run: $run, placement: $placement);
			if (isset($one['clash']) === true) {
				$laid['clashes'][] = $one['clash'];
			}

			if (isset($one['slot']) === true) {
				$laid['slots'][] = $one['slot'];
			}
		}

		return $laid;
	}//end slots()

	/**
	 * One placement laid out: its slot when it fits the grid, and its clash when something is wrong with it.
	 *
	 * A placement of an unknown lesson is skipped.
	 *
	 * @param ScoringRun            $run       The input and its lookups.
	 * @param array<string,string> $placement The placement.
	 *
	 * @return array{slot?:array<string,mixed>,clash?:array<string,mixed>}
	 */
	private function layOut(ScoringRun $run, array $placement): array {
		$lesson = $run->lesson(key: (string)$placement['lesson']);
		if ($lesson === null) {
			return [];
		}

		$placed = ['lesson' => $lesson, 'room' => (string)$placement['room'], 'period' => (string)$placement['period']];
		$placed['periods'] = $run->grid->span(start: $placed['period'], length: (int)($lesson['length'] ?? 1));
		$laid = array_filter(['clash' => $this->clash(run: $run, placed: $placed)]);
		if ($placed['periods'] !== null) {
			$laid['slot'] = $this->slot(run: $run, placed: $placed);
		}

		return $laid;
	}//end layOut()

	/**
	 * The clash of a placement that is wrong by itself, or null.
	 *
	 * @param ScoringRun          $run    The input and its lookups.
	 * @param array<string,mixed> $placed The lesson, the room reference, the start period and the periods.
	 *
	 * @return array<string,mixed>|null
	 */
	private function clash(ScoringRun $run, array $placed): ?array {
		$problem = $this->placementProblem(run: $run, placed: $placed);
		if ($problem === null) {
			return null;
		}

		return ['kind' => $problem, 'reference' => $placed['room'], 'period' => $placed['period'], 'lessons' => [(string)$placed['lesson']['key']]];
	}//end clash()

	/**
	 * The slot of a placement that fits the grid.
	 *
	 * @param ScoringRun          $run    The input and its lookups.
	 * @param array<string,mixed> $placed The lesson, room and periods.
	 *
	 * @return array<string,mixed>
	 */
	private function slot(ScoringRun $run, array $placed): array {
		return [
			'lesson'  => $placed['lesson'],
			'room'    => $placed['room'],
			'periods' => $placed['periods'],
			'day'     => $run->grid->day(key: $placed['periods'][0]),
			'order'   => $run->order(period: $placed['periods'][0]),
		];
	}//end slot()

	/**
	 * What is wrong with one placement by itself, or null.
	 *
	 * @param ScoringRun          $run    The input and its lookups.
	 * @param array<string,mixed> $placed The lesson, the room reference and the periods (null when off the grid).
	 *
	 * @return string|null
	 */
	private function placementProblem(ScoringRun $run, array $placed): ?string {
		if ($placed['periods'] === null) {
			return 'outOfGrid';
		}

		$room = $run->room(reference: $placed['room']);
		if ($room === null) {
			return 'unknownRoom';
		}

		if ($this->wrongRoomType(lesson: $placed['lesson'], room: $room) === true) {
			return 'roomType';
		}

		return null;
	}//end placementProblem()

	/**
	 * Whether a lesson that needs a room type sits in a room of another type.
	 *
	 * @param array<string,mixed> $lesson The lesson.
	 * @param array<string,mixed> $room   The room.
	 *
	 * @return bool
	 */
	private function wrongRoomType(array $lesson, array $room): bool {
		$needed = (string)($lesson['roomType'] ?? '');
		return $needed !== '' && (string)($room['type'] ?? '') !== $needed;
	}//end wrongRoomType()

	/**
	 * Two lessons of one teacher, group or room in the same period: one clash per resource and period.
	 *
	 * @param array<int,array<string,mixed>> $slots The placed lessons.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function doubleBookings(array $slots): array {
		$taken = [];
		foreach ($slots as $slot) {
			$owners = ['teacher' => (string)$slot['lesson']['teacher'], 'group' => (string)$slot['lesson']['group'], 'room' => (string)$slot['room']];
			foreach ($slot['periods'] as $period) {
				foreach ($owners as $kind => $reference) {
					$taken[$kind."\n".$reference."\n".$period][] = (string)$slot['lesson']['key'];
				}
			}
		}

		$clashes = [];
		foreach ($taken as $at => $lessons) {
			if (count($lessons) > 1 && explode("\n", $at)[1] !== '') {
				[$kind, $reference, $period] = explode("\n", $at);
				$clashes[] = ['kind' => $kind, 'reference' => $reference, 'period' => $period, 'lessons' => $lessons];
			}
		}

		return $clashes;
	}//end doubleBookings()

	/**
	 * Every broken wish with the lessons that break it.
	 *
	 * @param ScoringRun                     $run   The input, with its wishes, and the week grid.
	 * @param array<int,array<string,mixed>> $slots The placed lessons.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function brokenWishes(ScoringRun $run, array $slots): array {
		$broken = [];
		foreach ($run->input->wishes as $wish) {
			$lessons = $this->wishes->breakingLessons(wish: $wish, slots: $slots, grid: $run->grid);
			if ($lessons === []) {
				continue;
			}

			$hard     = ($wish['strength'] ?? 'soft') === 'hard';
			$broken[] = [
				'wish'     => (string)($wish['id'] ?? ''),
				'strength' => (string)($wish['strength'] ?? 'soft'),
				'weight'   => $this->weight(wish: $wish, hard: $hard),
				'lessons'  => $lessons,
			];
		}

		return $broken;
	}//end brokenWishes()

	/**
	 * The weight of a broken wish: null for a hard wish, else 1 to 3 (1 when unset).
	 *
	 * @param array<string,mixed> $wish The wish.
	 * @param bool                $hard Whether it is hard.
	 *
	 * @return int|null
	 */
	private function weight(array $wish, bool $hard): ?int {
		if ($hard === true) {
			return null;
		}

		return max(1, (int)($wish['weight'] ?? 1));
	}//end weight()

	/**
	 * Hard and soft wishes broken, and the weighted soft penalty.
	 *
	 * @param array<int,array<string,mixed>> $broken The broken wishes.
	 *
	 * @return array{hardWishesBroken:int,softWishesBroken:int,softPenalty:int}
	 */
	private function wishMetrics(array $broken): array {
		$metrics = ['hardWishesBroken' => 0, 'softWishesBroken' => 0, 'softPenalty' => 0];
		foreach ($broken as $wish) {
			if ($wish['strength'] === 'hard') {
				$metrics['hardWishesBroken']++;
				continue;
			}

			$metrics['softWishesBroken']++;
			$metrics['softPenalty'] += ((int)$wish['weight'] * count($wish['lessons']));
		}

		return $metrics;
	}//end wishMetrics()

	/**
	 * Free periods between a teacher's lessons on a day: in total and for the teacher with the most.
	 *
	 * @param array<int,array<string,mixed>> $slots The placed lessons.
	 * @param WeekGrid                       $grid  The week grid.
	 *
	 * @return array{teacherGaps:int,teacherGapsWorst:int}
	 */
	private function teacherGaps(array $slots, WeekGrid $grid): array {
		$numbers = [];
		foreach ($slots as $slot) {
			foreach ($slot['periods'] as $period) {
				$numbers[(string)$slot['lesson']['teacher']][$grid->day(key: $period)][] = $grid->number(key: $period);
			}
		}

		$total = 0;
		$worst = 0;
		foreach ($numbers as $days) {
			$gaps   = array_sum(array_map([$grid, 'gaps'], $days));
			$total += $gaps;
			$worst  = max($worst, $gaps);
		}

		return ['teacherGaps' => $total, 'teacherGapsWorst' => $worst];
	}//end teacherGaps()

	/**
	 * The most lessons one group has on one day.
	 *
	 * @param array<int,array<string,mixed>> $slots The placed lessons.
	 *
	 * @return int
	 */
	private function lessonsPerDayWorst(array $slots): int {
		$counts = [];
		foreach ($slots as $slot) {
			$key = (string)$slot['lesson']['group']."\n".(string)$slot['day'];
			$counts[$key] = (($counts[$key] ?? 0) + 1);
		}

		if ($counts === []) {
			return 0;
		}

		return (int)max($counts);
	}//end lessonsPerDayWorst()

	/**
	 * The share of room periods in use, overall and per room type, rounded to two decimals.
	 *
	 * @param SolverInput                    $input The input.
	 * @param array<int,array<string,mixed>> $slots The placed lessons.
	 *
	 * @return array{roomUse:float,roomUseByType:array<string,float>}
	 */
	private function roomUse(SolverInput $input, array $slots): array {
		$types = array_column($input->rooms, 'type', 'reference');
		$used  = [];
		foreach ($slots as $slot) {
			$type = ($types[(string)$slot['room']] ?? null);
			if ($type !== null) {
				$used[(string)$type] = (($used[(string)$type] ?? 0) + count($slot['periods']));
			}
		}

		$periods = count($input->periods);
		$byType  = [];
		foreach (array_count_values(array_map('strval', array_values($types))) as $type => $rooms) {
			$byType[(string)$type] = $this->share(used: ($used[$type] ?? 0), available: ($rooms * $periods));
		}

		return ['roomUse' => $this->share(used: array_sum($used), available: (count($types) * $periods)), 'roomUseByType' => $byType];
	}//end roomUse()

	/**
	 * A share rounded to two decimals, 0.0 when nothing is available.
	 *
	 * @param int $used      Used.
	 * @param int $available Available.
	 *
	 * @return float
	 */
	private function share(int $used, int $available): float {
		if ($available === 0) {
			return 0.0;
		}

		return round($used / $available, 2);
	}//end share()
}//end class
