<?php

/**
 * Tests for the PHP local search solver: a feasible week has no clash and no
 * broken hard wish, an impossible hard wish leaves its lesson unplaced and
 * names it, a free soft wish is kept, a seed repeats, and a school-size week
 * finishes inside a minute.
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

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCA\Planninq\Timetabling\LocalSearchSolver;
use OCA\Planninq\Timetabling\PlacementOptions;
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableScorer;
use OCA\Planninq\Timetabling\WishChecker;
use PHPUnit\Framework\TestCase;

/**
 * The solver on small hand-made weeks and one generated school week.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
 */
class LocalSearchSolverTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The period keys of a week of the given days and periods a day.
	 *
	 * @param array<int,string> $days    The days.
	 * @param int               $perDay  Periods a day.
	 *
	 * @return array<int,string>
	 */
	private function periods(array $days, int $perDay): array {
		$keys = [];
		foreach ($days as $day) {
			for ($number = 1; $number <= $perDay; $number++) {
				$keys[] = $day.'-'.$number;
			}
		}

		return $keys;
	}//end periods()

	/**
	 * A lesson row.
	 *
	 * @param string $key     The lesson key.
	 * @param string $teacher The teacher.
	 * @param int    $length  Periods it takes.
	 *
	 * @return array<string,mixed>
	 */
	private function lesson(string $key, string $teacher, int $length=1): array {
		[$group, $subject] = explode(':', $key);
		return [
			'key'      => $key,
			'activity' => $group.':'.$subject,
			'group'    => $group,
			'subject'  => $subject,
			'teacher'  => $teacher,
			'roomType' => 'classroom',
			'length'   => $length,
		];
	}//end lesson()

	/**
	 * A small week: 3A and 3B, klaas and noor, two classrooms, Monday and Wednesday, four periods each.
	 *
	 * @param array<int,array<string,mixed>> $wishes The wishes.
	 *
	 * @return SolverInput
	 */
	private function smallWeek(array $wishes=[]): SolverInput {
		return new SolverInput(
			periods: $this->periods(days: ['mon', 'wed'], perDay: 4),
			rooms: [
				['reference' => 'r-1', 'capacity' => 30, 'type' => 'classroom'],
				['reference' => 'r-2', 'capacity' => 30, 'type' => 'classroom'],
			],
			lessons: [
				$this->lesson('3A:English:1', 'klaas'),
				$this->lesson('3A:English:2', 'klaas'),
				$this->lesson('3A:English:3', 'klaas'),
				$this->lesson('3B:English:1', 'klaas'),
				$this->lesson('3A:Maths:1', 'noor', 2),
				$this->lesson('3B:Maths:1', 'noor'),
				$this->lesson('3B:Maths:2', 'noor'),
			],
			wishes: $wishes,
			source: 'csv',
		);
	}//end smallWeek()

	/**
	 * A solver that tries a fixed number of moves.
	 *
	 * @param int $iterations Moves per call.
	 *
	 * @return LocalSearchSolver
	 */
	private function solver(int $iterations=300): LocalSearchSolver {
		return new LocalSearchSolver(
			scorer: new TimetableScorer(),
			options: new PlacementOptions(),
			wishes: new WishChecker(),
			iterations: $iterations
		);
	}//end solver()

	/**
	 * Scenario "Generate a scenario that respects a hard wish": klaas never on Wednesday
	 * periods 3 and 4, everything placed, no clash, no hard wish broken.
	 *
	 * @return void
	 */
	public function testAFeasibleWeekPlacesEverythingAndKeepsTheHardWish(): void {
		$wish   = ['id' => 'w-klaas', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => ['wed-3', 'wed-4'], 'strength' => 'hard'];
		$input  = $this->smallWeek(wishes: [$wish]);
		$result = $this->solver()->solve(input: $input, seconds: 20, seed: 7);

		self::assertSame(expected: [], actual: $result->unplaced);
		self::assertCount(expectedCount: 7, haystack: $result->placements);
		self::assertSame(expected: 0, actual: $result->metrics['clashes']);
		self::assertSame(expected: 0, actual: $result->metrics['hardWishesBroken']);
		$klaas = array_filter($result->placements, static fn (array $row): bool => str_contains($row['lesson'], 'English'));
		foreach ($klaas as $row) {
			self::assertNotContains(needle: $row['period'], haystack: ['wed-3', 'wed-4']);
		}
	}//end testAFeasibleWeekPlacesEverythingAndKeepsTheHardWish()

	/**
	 * Scenario "A lesson that cannot be placed is listed with its reason": klaas is unavailable
	 * all week but for one period, so one of his four lessons fits and three are unplaced
	 * naming the wish. With three free periods, exactly one lesson is left.
	 *
	 * @return void
	 */
	public function testAnImpossibleHardWishLeavesALessonUnplacedNamingTheWish(): void {
		$free   = ['mon-1', 'mon-2', 'mon-3'];
		$closed = array_values(array_diff($this->periods(days: ['mon', 'wed'], perDay: 4), $free));
		$wish   = ['id' => 'w-klaas', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => $closed, 'strength' => 'hard'];
		$result = $this->solver()->solve(input: $this->smallWeek(wishes: [$wish]), seconds: 20, seed: 7);

		self::assertCount(expectedCount: 1, haystack: $result->unplaced);
		self::assertStringContainsString(needle: 'English', haystack: $result->unplaced[0]['lesson']);
		self::assertSame(expected: 'w-klaas', actual: $result->unplaced[0]['wish']);
		self::assertSame(expected: 0, actual: $result->metrics['hardWishesBroken']);
		self::assertSame(expected: 0, actual: $result->metrics['clashes']);
	}//end testAnImpossibleHardWishLeavesALessonUnplacedNamingTheWish()

	/**
	 * A hard wish of a kind the options cannot express (3A at most one lesson a day, four lessons, two days) is kept
	 * by the search, or its breaking lessons are taken off and name it: never broken.
	 *
	 * @return void
	 */
	public function testAHardLimitIsNeverBroken(): void {
		$wish   = ['id' => 'w-max', 'appliesTo' => 'group', 'reference' => '3A', 'kind' => 'maxPerDay', 'limit' => 1, 'strength' => 'hard'];
		$result = $this->solver()->solve(input: $this->smallWeek(wishes: [$wish]), seconds: 20, seed: 3);

		self::assertSame(expected: 0, actual: $result->metrics['hardWishesBroken']);
		foreach ($result->unplaced as $row) {
			self::assertSame(expected: 'w-max', actual: $row['wish']);
			self::assertSame(expected: 'hardWish', actual: $row['reason']);
		}

		self::assertCount(expectedCount: 5, haystack: $result->placements);
		self::assertCount(expectedCount: 2, haystack: $result->unplaced);
	}//end testAHardLimitIsNeverBroken()

	/**
	 * A soft wish that costs nothing to keep is kept: noor would rather not teach on Monday,
	 * and Wednesday has room for all her lessons.
	 *
	 * @return void
	 */
	public function testASoftWishIsKeptWhenItCostsNothing(): void {
		$wish   = [
			'id'        => 'w-noor',
			'appliesTo' => 'teacher',
			'reference' => 'noor',
			'kind'      => 'avoid',
			'periods'   => $this->periods(days: ['mon'], perDay: 4),
			'strength'  => 'soft',
			'weight'    => 2,
		];
		$result = $this->solver()->solve(input: $this->smallWeek(wishes: [$wish]), seconds: 20, seed: 11);

		self::assertSame(expected: 0, actual: $result->metrics['softWishesBroken']);
		self::assertSame(expected: [], actual: $result->brokenWishes);
		self::assertSame(expected: [], actual: $result->unplaced);
	}//end testASoftWishIsKeptWhenItCostsNothing()

	/**
	 * The same seed gives the same placements; a different seed may differ but is as valid.
	 *
	 * @return void
	 */
	public function testTheSameSeedGivesTheSameResult(): void {
		$input = $this->smallWeek();
		$one   = $this->solver()->solve(input: $input, seconds: 20, seed: 42);
		$two   = $this->solver()->solve(input: $input, seconds: 20, seed: 42);
		$other = $this->solver()->solve(input: $input, seconds: 20, seed: 43);

		self::assertSame(expected: $one->placements, actual: $two->placements);
		self::assertSame(expected: $one->iterations, actual: $two->iterations);
		self::assertSame(expected: 0, actual: $other->metrics['clashes']);
	}//end testTheSameSeedGivesTheSameResult()

	/**
	 * A step continues from the placements of an earlier step and never ends worse than them.
	 *
	 * @return void
	 */
	public function testAStepContinuesFromTheBestSoFar(): void {
		$input = $this->smallWeek();
		$first = $this->solver(iterations: 50)->solve(input: $input, seconds: 20, seed: 5);
		$next  = $this->solver(iterations: 50)->solve(input: $input, seconds: 20, seed: 6, start: $first->placements);

		self::assertLessThanOrEqual(expected: $first->cost, actual: $next->cost);
	}//end testAStepContinuesFromTheBestSoFar()

	/**
	 * A school week of 600 lessons (30 groups of 20 lessons, 60 teachers, 30 classrooms, 5 days of 8 periods)
	 * is placed without a clash inside 60 seconds, and the result fits the real timetableScenario schema.
	 *
	 * @return void
	 */
	public function testSixHundredLessonsFinishInsideAMinute(): void {
		$lessons = [];
		for ($group = 1; $group <= 30; $group++) {
			foreach (['Dutch', 'English', 'Maths', 'Science'] as $index => $subject) {
				for ($number = 1; $number <= 5; $number++) {
					$lessons[] = $this->lesson('G'.$group.':'.$subject.':'.$number, 't'.((($group - 1) % 15) + 1).'-'.$index);
				}
			}
		}

		$rooms = [];
		for ($room = 1; $room <= 30; $room++) {
			$rooms[] = ['reference' => 'r-'.$room, 'capacity' => 30, 'type' => 'classroom'];
		}

		$input  = new SolverInput(
			periods: $this->periods(days: ['mon', 'tue', 'wed', 'thu', 'fri'], perDay: 8),
			rooms: $rooms,
			lessons: $lessons,
			wishes: [['id' => 'w-t1', 'appliesTo' => 'teacher', 'reference' => 't1-0', 'kind' => 'unavailable', 'periods' => ['fri-7', 'fri-8'], 'strength' => 'hard']],
			source: 'csv',
		);
		$began  = microtime(true);
		$result = $this->solver(iterations: 200)->solve(input: $input, seconds: 50, seed: 1);

		self::assertLessThan(expected: 60.0, actual: (microtime(true) - $began));
		self::assertCount(expectedCount: 600, haystack: $result->placements);
		self::assertSame(expected: 0, actual: $result->metrics['clashes']);
		self::assertSame(expected: 0, actual: $result->metrics['hardWishesBroken']);

		$scenario = array_merge(
			[
				'title'      => 'Generated week',
				'source'     => 'generated',
				'weekOf'     => '2026-10-05',
				'windowFrom' => '2026-10-05',
				'windowTo'   => '2026-10-30',
				'status'     => 'done',
				'seed'       => 1,
				'input'      => $input->toArray(),
			],
			$result->toScenario()
		);
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: $scenario));
	}//end testSixHundredLessonsFinishInsideAMinute()
}//end class
