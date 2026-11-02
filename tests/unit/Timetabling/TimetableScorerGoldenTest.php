<?php

/**
 * Golden test for the timetable scorer.
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

use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableScorer;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Scores seeded weeks with random placements, including unknown lessons and
 * rooms, rooms of the wrong type, lessons that run off the grid and double
 * bookings, and compares the metrics, broken wishes and clashes with the
 * fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Timetabling\TimetableScorer
 * @uses   \OCA\Planninq\Timetabling\SolverInput
 * @uses   \OCA\Planninq\Timetabling\WeekGrid
 * @uses   \OCA\Planninq\Timetabling\ScoringRun
 * @uses   \OCA\Planninq\Timetabling\WishChecker
 */
class TimetableScorerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/timetable-scorer.golden.json';

	private const KINDS = ['unavailable', 'avoid', 'maxPerDay', 'noGaps', 'sameRoom'];

	/**
	 * The recorded scores equal the fixture.
	 *
	 * @return void
	 */
	public function testScoreMatchesTheGoldenRecording(): void {
		$scorer = new TimetableScorer();
		$cases  = [];
		for ($seed = 1; $seed <= 60; $seed++) {
			[$input, $placements] = $this->week(random: new Randomizer(new Mt19937($seed)));
			$score   = $scorer->score(input: $input, placements: $placements);
			$cases[] = ['seed' => $seed, 'placements' => $placements, 'score' => $score, 'cost' => $scorer->costOf(metrics: $score['metrics'])];
		}

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($cases as $index => $case) {
			self::assertSame(expected: json_encode($expected[$index] ?? null), actual: json_encode($case), message: 'seed ' . $case['seed']);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testScoreMatchesTheGoldenRecording()

	/**
	 * A seeded week and random placements over it.
	 *
	 * @param Randomizer $random The seeded randomizer.
	 *
	 * @return array{0:SolverInput,1:list<array{lesson:string,period:string,room:string}>}
	 */
	private function week(Randomizer $random): array {
		$periods = [];
		foreach (array_slice(['mon', 'tue', 'wed'], 0, $random->getInt(1, 3)) as $day) {
			for ($number = 1; $number <= $random->getInt(2, 5); $number++) {
				$periods[] = $day . '-' . $number;
			}
		}

		$rooms = [];
		for ($room = 1; $room <= $random->getInt(1, 3); $room++) {
			$rooms[] = ['reference' => 'r-' . $room, 'capacity' => 30, 'type' => ['classroom', 'lab'][$random->getInt(0, 1)]];
		}

		$lessons = [];
		for ($index = 1; $index <= $random->getInt(1, 9); $index++) {
			$group     = ['3A', '3B'][$random->getInt(0, 1)];
			$lessons[] = [
				'key'      => $group . ':' . $index,
				'activity' => $group . ':' . ['Maths', 'Physics'][$random->getInt(0, 1)],
				'group'    => $group,
				'subject'  => 'Subject',
				'teacher'  => ['klaas', 'noor', ''][$random->getInt(0, 2)],
				'roomType' => ['classroom', 'lab', ''][$random->getInt(0, 2)],
				'length'   => [1, 1, 2, 3][$random->getInt(0, 3)],
			];
		}

		$wishes = [];
		for ($index = 1; $index <= $random->getInt(0, 5); $index++) {
			$appliesTo = ['teacher', 'group', 'activity'][$random->getInt(0, 2)];
			$wishes[]  = [
				'id'        => 'w-' . $index,
				'appliesTo' => $appliesTo,
				'reference' => ['teacher' => 'klaas', 'group' => '3A', 'activity' => $lessons[0]['activity']][$appliesTo],
				'kind'      => self::KINDS[$random->getInt(0, 4)],
				'periods'   => array_values(array_filter($periods, static fn (): bool => $random->getInt(0, 2) === 0)),
				'limit'     => $random->getInt(1, 2),
				'strength'  => ['hard', 'soft'][$random->getInt(0, 1)],
				'weight'    => $random->getInt(0, 5),
			];
		}

		$placements = [];
		$keys       = array_merge(array_column($lessons, 'key'), ['ghost']);
		$references = array_merge(array_column($rooms, 'reference'), ['r-none']);
		for ($index = $random->getInt(0, 10); $index > 0; $index--) {
			$placements[] = [
				'lesson' => $keys[$random->getInt(0, (count($keys) - 1))],
				'period' => array_merge($periods, ['sun-9'])[$random->getInt(0, count($periods))],
				'room'   => $references[$random->getInt(0, (count($references) - 1))],
			];
		}

		return [new SolverInput(periods: $periods, rooms: $rooms, lessons: $lessons, wishes: $wishes, source: 'csv'), $placements];
	}//end week()
}//end class
