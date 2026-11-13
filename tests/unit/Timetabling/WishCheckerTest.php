<?php

/**
 * Unit tests for WishChecker: which lessons break each kind of wish, and when a wish forbids a placement.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Timetabling
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Timetabling;

use OCA\Planninq\Timetabling\WeekGrid;
use OCA\Planninq\Timetabling\WishChecker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Timetabling\WishChecker
 * @uses   \OCA\Planninq\Timetabling\WeekGrid
 */
class WishCheckerTest extends TestCase {

	/**
	 * A placed lesson.
	 *
	 * @param string             $key     The lesson key.
	 * @param array<int, string> $periods Its periods.
	 * @param string             $room    Its room.
	 * @param int                $order   Its period order.
	 * @param string             $teacher Its teacher.
	 *
	 * @return array<string, mixed>
	 */
	private static function slot(string $key, array $periods, string $room, int $order, string $teacher = 'tina'): array {
		return [
			'lesson' => ['key' => $key, 'teacher' => $teacher, 'group' => '1a', 'activity' => 'math'],
			'day' => explode(separator: '-', string: $periods[0])[0],
			'periods' => $periods,
			'room' => $room,
			'order' => $order,
		];
	}//end slot()

	/**
	 * The week grid.
	 *
	 * @return WeekGrid
	 */
	private static function grid(): WeekGrid {
		return new WeekGrid(periods: ['mon-1', 'mon-2', 'mon-3', 'mon-4', 'tue-1', 'tue-2']);
	}//end grid()

	/**
	 * Tina's lessons, one of Ted's, in period order across rooms.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function slots(): array {
		return [
			self::slot(key: 'L3', periods: ['mon-4'], room: 'B', order: 4),
			self::slot(key: 'L1', periods: ['mon-1'], room: 'A', order: 1),
			self::slot(key: 'L2', periods: ['mon-2'], room: 'A', order: 2),
			self::slot(key: 'L4', periods: ['tue-1'], room: 'A', order: 5),
			self::slot(key: 'T1', periods: ['mon-3'], room: 'B', order: 3, teacher: 'ted'),
		];
	}//end slots()

	/**
	 * Each kind of wish names the lessons that break it, for its own teacher only.
	 *
	 * @return void
	 */
	public function testEachKindNamesTheLessonsThatBreakIt(): void {
		$checker = new WishChecker();
		$tina    = ['appliesTo' => 'teacher', 'reference' => 'tina'];
		$slots   = self::slots();
		$grid    = self::grid();

		$this->assertSame(
			expected: ['L2'],
			actual: $checker->breakingLessons(wish: $tina + ['kind' => 'unavailable', 'periods' => ['mon-2', 'mon-3']], slots: $slots, grid: $grid)
		);
		$this->assertSame(
			expected: ['L2', 'L3'],
			actual: $checker->breakingLessons(wish: $tina + ['kind' => 'maxPerDay', 'limit' => 1], slots: $slots, grid: $grid)
		);
		$this->assertSame(
			expected: ['L1', 'L2', 'L3'],
			actual: $checker->breakingLessons(wish: $tina + ['kind' => 'noGaps'], slots: $slots, grid: $grid)
		);
		$this->assertSame(
			expected: ['L3'],
			actual: $checker->breakingLessons(wish: $tina + ['kind' => 'sameRoom'], slots: $slots, grid: $grid)
		);
	}//end testEachKindNamesTheLessonsThatBreakIt()

	/**
	 * An unknown kind and a limit below one break nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownKindOrNoLimitBreaksNothing(): void {
		$checker = new WishChecker();
		$tina    = ['appliesTo' => 'teacher', 'reference' => 'tina'];
		$slots   = self::slots();
		$grid    = self::grid();

		$this->assertSame(expected: [], actual: $checker->breakingLessons(wish: $tina + ['kind' => 'preferMornings'], slots: $slots, grid: $grid));
		$this->assertSame(expected: [], actual: $checker->breakingLessons(wish: $tina + ['kind' => 'maxPerDay', 'limit' => 0], slots: $slots, grid: $grid));
		$this->assertSame(expected: [], actual: $checker->breakingLessons(wish: $tina, slots: $slots, grid: $grid));
	}//end testAnUnknownKindOrNoLimitBreaksNothing()

	/**
	 * A wish applies by teacher, group, activity or room; an unknown target applies to nothing.
	 *
	 * @return void
	 */
	public function testAWishAppliesByItsTarget(): void {
		$checker = new WishChecker();
		$lesson  = ['teacher' => 'tina', 'group' => '1a', 'activity' => 'math'];

		$this->assertTrue(condition: $checker->applies(wish: ['appliesTo' => 'group', 'reference' => '1a'], lesson: $lesson, room: 'A'));
		$this->assertTrue(condition: $checker->applies(wish: ['appliesTo' => 'activity', 'reference' => 'math'], lesson: $lesson, room: 'A'));
		$this->assertTrue(condition: $checker->applies(wish: ['appliesTo' => 'room', 'reference' => 'A'], lesson: $lesson, room: 'A'));
		$this->assertFalse(condition: $checker->applies(wish: ['appliesTo' => 'room', 'reference' => 'B'], lesson: $lesson, room: 'A'));
		$this->assertFalse(condition: $checker->applies(wish: ['appliesTo' => 'building', 'reference' => 'A'], lesson: $lesson, room: 'A'));
	}//end testAWishAppliesByItsTarget()

	/**
	 * Only an unavailable or avoid wish forbids a placement, and only on its periods, for its target.
	 *
	 * @return void
	 */
	public function testOnlyBlockingWishesForbidAPlacement(): void {
		$checker = new WishChecker();
		$lesson  = ['teacher' => 'tina'];
		$avoid   = ['kind' => 'avoid', 'appliesTo' => 'teacher', 'reference' => 'tina', 'periods' => ['mon-1']];

		$this->assertTrue(condition: $checker->forbids(wish: $avoid, lesson: $lesson, periods: ['mon-1', 'mon-2'], room: 'A'));
		$this->assertFalse(condition: $checker->forbids(wish: $avoid, lesson: $lesson, periods: ['mon-2'], room: 'A'));
		$this->assertFalse(condition: $checker->forbids(wish: $avoid, lesson: ['teacher' => 'ted'], periods: ['mon-1'], room: 'A'));
		$this->assertFalse(condition: $checker->forbids(wish: ['kind' => 'maxPerDay'] + $avoid, lesson: $lesson, periods: ['mon-1'], room: 'A'));
	}//end testOnlyBlockingWishesForbidAPlacement()
}//end class
