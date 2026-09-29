<?php

/**
 * Tests for the timetable scorer: every wish kind broken once and kept once,
 * the clash kinds and the measures compare reads.
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
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableScorer;
use PHPUnit\Framework\TestCase;

/**
 * Hand-made placements on a two-day, four-period week.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-4.1
 */
class TimetableScorerTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * Lessons of the fixture: klaas teaches 3A English three times, noor 3A Maths once (two periods), piet 3B Art once in a studio.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function lessons(): array {
		$lesson = static fn (string $key, string $group, string $subject, string $teacher, string $roomType='classroom', int $length=1): array => [
			'key'      => $key,
			'activity' => $group.':'.$subject,
			'group'    => $group,
			'subject'  => $subject,
			'teacher'  => $teacher,
			'roomType' => $roomType,
			'length'   => $length,
		];

		return [
			$lesson('3A:English:1', '3A', 'English', 'klaas'),
			$lesson('3A:English:2', '3A', 'English', 'klaas'),
			$lesson('3A:English:3', '3A', 'English', 'klaas'),
			$lesson('3A:Maths:1', '3A', 'Maths', 'noor', 'classroom', 2),
			$lesson('3B:Art:1', '3B', 'Art', 'piet', 'studio'),
		];
	}//end lessons()

	/**
	 * The input with the given wishes.
	 *
	 * @param array<int,array<string,mixed>> $wishes The wishes.
	 *
	 * @return SolverInput
	 */
	private function input(array $wishes=[]): SolverInput {
		return new SolverInput(
			periods: ['mon-1', 'mon-2', 'mon-3', 'mon-4', 'tue-1', 'tue-2', 'tue-3', 'tue-4'],
			rooms: [
				['reference' => 'r-1', 'capacity' => 30, 'type' => 'classroom'],
				['reference' => 'r-2', 'capacity' => 30, 'type' => 'classroom'],
				['reference' => 's-1', 'capacity' => 20, 'type' => 'studio'],
			],
			lessons: $this->lessons(),
			wishes: $wishes,
			source: 'csv',
		);
	}//end input()

	/**
	 * A placement row.
	 *
	 * @param string $lesson The lesson key.
	 * @param string $period The first period.
	 * @param string $room   The room.
	 *
	 * @return array{lesson:string,period:string,room:string}
	 */
	private function at(string $lesson, string $period, string $room='r-1'): array {
		return ['lesson' => $lesson, 'period' => $period, 'room' => $room];
	}//end at()

	/**
	 * A clean week: English mon-1, mon-2, tue-1; Maths mon-3/4; Art tue-2.
	 *
	 * @return array<int,array{lesson:string,period:string,room:string}>
	 */
	private function cleanWeek(): array {
		return [
			$this->at('3A:English:1', 'mon-1'),
			$this->at('3A:English:2', 'mon-2'),
			$this->at('3A:English:3', 'tue-1'),
			$this->at('3A:Maths:1', 'mon-3', 'r-2'),
			$this->at('3B:Art:1', 'tue-2', 's-1'),
		];
	}//end cleanWeek()

	/**
	 * The broken wish ids of a score.
	 *
	 * @param array<string,mixed> $score The score.
	 *
	 * @return array<int,string>
	 */
	private function brokenIds(array $score): array {
		return array_column($score['brokenWishes'], 'wish');
	}//end brokenIds()

	/**
	 * A clean week has no clashes, nothing unplaced and the measures compare shows.
	 *
	 * @return void
	 */
	public function testACleanWeekHasTheMeasures(): void {
		$score   = (new TimetableScorer())->score(input: $this->input(), placements: $this->cleanWeek());
		$metrics = $score['metrics'];

		self::assertSame(expected: [], actual: $score['clashes']);
		self::assertSame(expected: 5, actual: $metrics['lessons']);
		self::assertSame(expected: 5, actual: $metrics['placed']);
		self::assertSame(expected: 0, actual: $metrics['unplaced']);
		self::assertSame(expected: 0, actual: $metrics['clashes']);
		self::assertSame(expected: 0, actual: $metrics['hardWishesBroken']);
		self::assertSame(expected: 0, actual: $metrics['softWishesBroken']);
		self::assertSame(expected: 0, actual: $metrics['softPenalty']);
		// Noor: mon-3 and mon-4: no gap. Klaas: mon-1, mon-2 and tue-1: no gap.
		self::assertSame(expected: 0, actual: $metrics['teacherGaps']);
		self::assertSame(expected: 0, actual: $metrics['teacherGapsWorst']);
		// 3A on Monday: English twice and Maths once.
		self::assertSame(expected: 3, actual: $metrics['lessonsPerDayWorst']);
		// Six room-periods used of 3 rooms x 8 periods.
		self::assertSame(expected: 0.25, actual: $metrics['roomUse']);
		self::assertSame(expected: ['classroom' => 0.31, 'studio' => 0.13], actual: $metrics['roomUseByType']);
		self::assertSame(expected: 0, actual: TimetableScorer::cost(metrics: $metrics));
	}//end testACleanWeekHasTheMeasures()

	/**
	 * Teacher, group and room clashes, a wrong room type, a lesson running off the day and an unknown room are counted.
	 *
	 * @return void
	 */
	public function testClashesAreCounted(): void {
		$week    = $this->cleanWeek();
		$week[1] = $this->at('3A:English:2', 'mon-1', 'r-2');
		// Teacher klaas and group 3A twice in mon-1.
		$week[4] = $this->at('3B:Art:1', 'mon-4', 'r-2');
		// Room r-2 twice in mon-4 (Maths runs mon-3 and mon-4), and Art in a classroom.
		$score = (new TimetableScorer())->score(input: $this->input(), placements: $week);

		$kinds = array_column($score['clashes'], 'kind');
		sort($kinds);
		self::assertSame(expected: ['group', 'room', 'roomType', 'teacher'], actual: $kinds);
		self::assertSame(expected: 4, actual: $score['metrics']['clashes']);
		self::assertGreaterThanOrEqual(expected: 4 * TimetableScorer::CLASH_COST, actual: TimetableScorer::cost(metrics: $score['metrics']));

		$off = $this->cleanWeek();
		$off[3] = $this->at('3A:Maths:1', 'mon-4', 'r-2');
		$off[4] = $this->at('3B:Art:1', 'tue-2', 'nowhere');
		$kinds  = array_column((new TimetableScorer())->score(input: $this->input(), placements: $off)['clashes'], 'kind');
		sort($kinds);
		self::assertSame(expected: ['outOfGrid', 'unknownRoom'], actual: $kinds);
	}//end testClashesAreCounted()

	/**
	 * A lesson with no placement is unplaced.
	 *
	 * @return void
	 */
	public function testALessonWithoutAPlacementIsUnplaced(): void {
		$week = array_slice($this->cleanWeek(), 0, 4);
		$metrics = (new TimetableScorer())->score(input: $this->input(), placements: $week)['metrics'];

		self::assertSame(expected: 4, actual: $metrics['placed']);
		self::assertSame(expected: 1, actual: $metrics['unplaced']);
		self::assertSame(expected: TimetableScorer::UNPLACED_COST, actual: TimetableScorer::cost(metrics: $metrics));
	}//end testALessonWithoutAPlacementIsUnplaced()

	/**
	 * Not on these periods (hard): broken when a lesson touches one, kept otherwise.
	 *
	 * @return void
	 */
	public function testUnavailableHard(): void {
		$wish = ['id' => 'w-un', 'appliesTo' => 'teacher', 'reference' => 'noor', 'kind' => 'unavailable', 'periods' => ['mon-4'], 'strength' => 'hard'];
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek());

		self::assertSame(expected: ['w-un'], actual: $this->brokenIds(score: $score));
		self::assertSame(expected: ['3A:Maths:1'], actual: $score['brokenWishes'][0]['lessons']);
		self::assertSame(expected: 'hard', actual: $score['brokenWishes'][0]['strength']);
		self::assertSame(expected: 1, actual: $score['metrics']['hardWishesBroken']);
		self::assertSame(expected: TimetableScorer::HARD_COST, actual: TimetableScorer::cost(metrics: $score['metrics']));

		$wish['periods'] = ['mon-5', 'tue-3'];
		self::assertSame(expected: [], actual: (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek())['brokenWishes']);
	}//end testUnavailableHard()

	/**
	 * Preferably not on these periods (soft, weight 2) on a group: broken, then kept.
	 *
	 * @return void
	 */
	public function testAvoidSoftWithWeight(): void {
		$wish  = ['id' => 'w-av', 'appliesTo' => 'group', 'reference' => '3A', 'kind' => 'avoid', 'periods' => ['mon-1', 'mon-2'], 'strength' => 'soft', 'weight' => 2];
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek());

		self::assertSame(expected: ['3A:English:1', '3A:English:2'], actual: $score['brokenWishes'][0]['lessons']);
		self::assertSame(expected: 2, actual: $score['brokenWishes'][0]['weight']);
		self::assertSame(expected: 1, actual: $score['metrics']['softWishesBroken']);
		// Weight 2 for each of the two lessons.
		self::assertSame(expected: 4, actual: $score['metrics']['softPenalty']);

		$wish['periods'] = ['tue-4'];
		self::assertSame(expected: 0, actual: (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek())['metrics']['softPenalty']);
	}//end testAvoidSoftWithWeight()

	/**
	 * At most N lessons a day on a teacher: the lessons over the limit are named.
	 *
	 * @return void
	 */
	public function testMaxPerDay(): void {
		$wish  = ['id' => 'w-max', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'maxPerDay', 'limit' => 1, 'strength' => 'soft', 'weight' => 1];
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek());

		self::assertSame(expected: ['3A:English:2'], actual: $score['brokenWishes'][0]['lessons']);

		$wish['limit'] = 2;
		self::assertSame(expected: [], actual: (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek())['brokenWishes']);
	}//end testMaxPerDay()

	/**
	 * No free periods between lessons on a teacher: a gap breaks it; the gap also counts in the teacher gaps.
	 *
	 * @return void
	 */
	public function testNoGaps(): void {
		$wish = ['id' => 'w-gap', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'noGaps', 'strength' => 'soft', 'weight' => 3];
		$week = $this->cleanWeek();
		$week[1] = $this->at('3A:English:2', 'tue-4');
		// Klaas on Tuesday: periods 1 and 4, two free periods between them.
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $week);

		self::assertSame(expected: ['3A:English:3', '3A:English:2'], actual: $score['brokenWishes'][0]['lessons']);
		self::assertSame(expected: 2, actual: $score['metrics']['teacherGaps']);
		self::assertSame(expected: 2, actual: $score['metrics']['teacherGapsWorst']);

		self::assertSame(expected: [], actual: (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek())['brokenWishes']);
	}//end testNoGaps()

	/**
	 * Always the same room on an activity: two rooms break it; the lessons outside the most used room are named.
	 *
	 * @return void
	 */
	public function testSameRoom(): void {
		$wish = ['id' => 'w-room', 'appliesTo' => 'activity', 'reference' => '3A:English', 'kind' => 'sameRoom', 'strength' => 'hard'];
		$week = $this->cleanWeek();
		$week[2] = $this->at('3A:English:3', 'tue-1', 'r-2');
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $week);

		self::assertSame(expected: ['3A:English:3'], actual: $score['brokenWishes'][0]['lessons']);

		self::assertSame(expected: [], actual: (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek())['brokenWishes']);
	}//end testSameRoom()

	/**
	 * A wish on a room applies to the lessons placed in it.
	 *
	 * @return void
	 */
	public function testAWishOnARoom(): void {
		$wish  = ['id' => 'w-r', 'appliesTo' => 'room', 'reference' => 's-1', 'kind' => 'unavailable', 'periods' => ['tue-2'], 'strength' => 'hard'];
		$score = (new TimetableScorer())->score(input: $this->input(wishes: [$wish]), placements: $this->cleanWeek());

		self::assertSame(expected: ['3B:Art:1'], actual: $score['brokenWishes'][0]['lessons']);
	}//end testAWishOnARoom()

	/**
	 * A finished scenario carrying this score passes the real timetableScenario schema.
	 *
	 * @return void
	 */
	public function testTheScoreFitsTheScenarioSchema(): void {
		$wishes = [
			['id' => 'w-un', 'appliesTo' => 'teacher', 'reference' => 'noor', 'kind' => 'unavailable', 'periods' => ['mon-4'], 'strength' => 'hard'],
			['id' => 'w-av', 'appliesTo' => 'group', 'reference' => '3A', 'kind' => 'avoid', 'periods' => ['mon-1'], 'strength' => 'soft', 'weight' => 2],
		];
		$input = $this->input(wishes: $wishes);
		$score = (new TimetableScorer())->score(input: $input, placements: $this->cleanWeek());

		$scenario = [
			'title'        => 'Imported week',
			'source'       => 'imported',
			'weekOf'       => '2026-10-05',
			'windowFrom'   => '2026-10-05',
			'windowTo'     => '2026-10-30',
			'status'       => 'done',
			'input'        => $input->toArray(),
			'placements'   => $this->cleanWeek(),
			'brokenWishes' => $score['brokenWishes'],
			'metrics'      => $score['metrics'],
		];
		self::assertCount(expectedCount: 2, haystack: $score['brokenWishes']);
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: $scenario));
	}//end testTheScoreFitsTheScenarioSchema()
}//end class
