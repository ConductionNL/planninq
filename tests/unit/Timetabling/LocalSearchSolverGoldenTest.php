<?php

/**
 * Golden test for the PHP local search solver.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Timetabling
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

namespace OCA\Planninq\Tests\Unit\Timetabling;

use OCA\Planninq\Timetabling\LocalSearchSolver;
use OCA\Planninq\Timetabling\PlacementOptions;
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableScorer;
use OCA\Planninq\Timetabling\WishChecker;
use PHPUnit\Framework\TestCase;

/**
 * Solves seeded weeks (hard and soft wishes of every kind, impossible wishes,
 * a continued start) with a fixed move count and compares every result field
 * with the fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Timetabling\LocalSearchSolver
 * @uses   \OCA\Planninq\Timetabling\PlacementOptions
 * @uses   \OCA\Planninq\Timetabling\PlacementCandidate
 * @uses   \OCA\Planninq\Timetabling\SearchOutcome
 * @uses   \OCA\Planninq\Timetabling\SearchState
 * @uses   \OCA\Planninq\Timetabling\SeededRandom
 * @uses   \OCA\Planninq\Timetabling\SolveRun
 * @uses   \OCA\Planninq\Timetabling\SolverInput
 * @uses   \OCA\Planninq\Timetabling\SolverResult
 * @uses   \OCA\Planninq\Timetabling\TimetableScorer
 * @uses   \OCA\Planninq\Timetabling\WeekGrid
 * @uses   \OCA\Planninq\Timetabling\ScoringRun
 * @uses   \OCA\Planninq\Timetabling\WishChecker
 */
class LocalSearchSolverGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/local-search-solver.golden.json';

	private const KINDS = ['unavailable', 'avoid', 'maxPerDay', 'noGaps', 'sameRoom'];

	/**
	 * The recorded results equal the fixture.
	 *
	 * @return void
	 */
	public function testSolveMatchesTheGoldenRecording(): void {
		$cases = [];
		for ($seed = 1; $seed <= 40; $seed++) {
			$cases[] = $this->solveCase(seed: $seed);
		}

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			if (is_dir(dirname(self::FIXTURE)) === false) {
				mkdir(dirname(self::FIXTURE));
			}

			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testSolveMatchesTheGoldenRecording()

	/**
	 * Build and solve one seeded week, once from scratch and once continuing
	 * from the first result's first half.
	 *
	 * @param int $seed The case seed.
	 *
	 * @return array<string,mixed>
	 */
	private function solveCase(int $seed): array {
		mt_srand($seed);
		$days    = array_slice(['mon', 'tue', 'wed', 'thu', 'fri'], 0, mt_rand(1, 3));
		$periods = [];
		foreach ($days as $day) {
			for ($number = 1; $number <= mt_rand(2, 4); $number++) {
				$periods[] = $day . '-' . $number;
			}
		}

		$rooms = [];
		for ($room = 1; $room <= mt_rand(1, 3); $room++) {
			$rooms[] = ['reference' => 'r-' . $room, 'capacity' => 30, 'type' => (mt_rand(0, 4) === 0 ? 'lab' : 'classroom')];
		}

		$lessons  = [];
		$teachers = ['klaas', 'noor', 'sam'];
		for ($i = 1; $i <= mt_rand(2, 8); $i++) {
			$group     = (mt_rand(0, 1) === 0 ? '3A' : '3B');
			$subject   = ['English', 'Maths', 'Physics'][mt_rand(0, 2)];
			$lessons[] = [
				'key'      => $group . ':' . $subject . ':' . $i,
				'activity' => $group . ':' . $subject,
				'group'    => $group,
				'subject'  => $subject,
				'teacher'  => $teachers[mt_rand(0, 2)],
				'roomType' => (mt_rand(0, 5) === 0 ? 'lab' : 'classroom'),
				'length'   => (mt_rand(0, 4) === 0 ? 2 : 1),
			];
		}

		$wishes = [];
		for ($w = 1; $w <= mt_rand(0, 4); $w++) {
			$appliesTo = ['teacher', 'group', 'activity'][mt_rand(0, 2)];
			$reference = match ($appliesTo) {
				'teacher' => $teachers[mt_rand(0, 2)],
				'group'   => (mt_rand(0, 1) === 0 ? '3A' : '3B'),
				default   => $lessons[0]['activity'],
			};
			$wishes[] = [
				'id'        => 'w-' . $w,
				'appliesTo' => $appliesTo,
				'reference' => $reference,
				'kind'      => self::KINDS[mt_rand(0, 4)],
				'periods'   => array_values(array_filter($periods, static fn (): bool => mt_rand(0, 2) === 0)),
				'limit'     => mt_rand(1, 2),
				'strength'  => (mt_rand(0, 1) === 0 ? 'hard' : 'soft'),
				'weight'    => mt_rand(1, 5),
			];
		}

		$input  = new SolverInput(periods: $periods, rooms: $rooms, lessons: $lessons, wishes: $wishes, source: 'csv');
		$solver = new LocalSearchSolver(scorer: new TimetableScorer(), options: new PlacementOptions(), wishes: new WishChecker(), iterations: 150);
		$first  = $solver->solve(input: $input, seconds: 30, seed: $seed);
		$start  = array_slice($first->placements, 0, intdiv(count($first->placements), 2));
		$second = $solver->solve(input: $input, seconds: 30, seed: ($seed + 100), start: $start);

		return ['seed' => $seed, 'first' => $this->describe(result: $first), 'continued' => $this->describe(result: $second)];
	}//end solveCase()

	/**
	 * Every field of a result.
	 *
	 * @param \OCA\Planninq\Timetabling\SolverResult $result The result.
	 *
	 * @return array<string,mixed>
	 */
	private function describe(\OCA\Planninq\Timetabling\SolverResult $result): array {
		return [
			'placements'   => $result->placements,
			'unplaced'     => $result->unplaced,
			'brokenWishes' => $result->brokenWishes,
			'metrics'      => $result->metrics,
			'cost'         => $result->cost,
			'iterations'   => $result->iterations,
		];
	}//end describe()
}//end class
