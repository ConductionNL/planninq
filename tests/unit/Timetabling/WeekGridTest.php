<?php

/**
 * Unit tests for WeekGrid: period keys, spans within a day, and gaps.
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
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Timetabling\WeekGrid
 */
class WeekGridTest extends TestCase {

	/**
	 * A grid of Monday 1-4 and Tuesday 1-2.
	 *
	 * @return WeekGrid
	 */
	private function grid(): WeekGrid {
		return new WeekGrid(periods: ['mon-1', 'mon-2', 'mon-3', 'mon-4', 'tue-1', 'tue-2']);
	}//end grid()

	/**
	 * The keys are the periods as given, and a key splits into its day and number.
	 *
	 * @return void
	 */
	public function testKeysDaysAndNumbers(): void {
		$grid = $this->grid();

		$this->assertSame(expected: ['mon-1', 'mon-2', 'mon-3', 'mon-4', 'tue-1', 'tue-2'], actual: $grid->keys());
		$this->assertSame(expected: 'tue', actual: $grid->day(key: 'tue-2'));
		$this->assertSame(expected: 2, actual: $grid->number(key: 'tue-2'));
		$this->assertSame(expected: 0, actual: $grid->number(key: 'mon'));
	}//end testKeysDaysAndNumbers()

	/**
	 * A span runs over consecutive periods of one day, at least one long, and none that leaves the grid.
	 *
	 * @return void
	 */
	public function testSpansStayWithinTheDay(): void {
		$grid = $this->grid();

		$this->assertSame(expected: ['mon-2', 'mon-3', 'mon-4'], actual: $grid->span(start: 'mon-2', length: 3));
		$this->assertSame(expected: ['tue-1'], actual: $grid->span(start: 'tue-1', length: 0));
		$this->assertNull(actual: $grid->span(start: 'tue-2', length: 2));
		$this->assertNull(actual: $grid->span(start: 'wed-1', length: 1));
	}//end testSpansStayWithinTheDay()

	/**
	 * Gaps count the free periods between the first and last taken ones, ignoring repeats.
	 *
	 * @return void
	 */
	public function testGapsCountTheHolesBetweenTakenPeriods(): void {
		$grid = $this->grid();

		$this->assertSame(expected: 0, actual: $grid->gaps(numbers: []));
		$this->assertSame(expected: 0, actual: $grid->gaps(numbers: [2, 3, 3, 4]));
		$this->assertSame(expected: 2, actual: $grid->gaps(numbers: [1, 4]));
		$this->assertSame(expected: 2, actual: $grid->gaps(numbers: [5, 3, 1, 1]));
		$this->assertSame(expected: 1, actual: $grid->gaps(numbers: [2, 4, 4]));
	}//end testGapsCountTheHolesBetweenTakenPeriods()
}//end class
