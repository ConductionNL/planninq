<?php

/**
 * Tests for the timetable week grid and generator budget (timetabling-generator task 1.2).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\TimetableGridService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Default grid, refusals, stored order and period keys.
 */
class TimetableGridServiceTest extends TestCase {

	/**
	 * The app config values, in memory.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * The service on an in-memory app config.
	 *
	 * @return TimetableGridService
	 */
	private function service(): TimetableGridService {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->stored[$key] = $value;
				return true;
			}
		);

		return new TimetableGridService(appConfig: $config);
	}//end service()

	/**
	 * Monday to Friday, eight periods of 50 minutes from 08:30, and ten minutes of budget.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function testTheDefaults(): void {
		$settings = $this->service()->settings();
		$grid     = json_decode($settings['timetable_period_grid'], true);

		self::assertSame(expected: ['mon', 'tue', 'wed', 'thu', 'fri'], actual: $grid['days']);
		self::assertCount(expectedCount: 8, haystack: $grid['periods']);
		self::assertSame(expected: ['start' => '08:30', 'end' => '09:20'], actual: $grid['periods'][0]);
		self::assertSame(expected: ['start' => '14:20', 'end' => '15:10'], actual: $grid['periods'][7]);
		self::assertSame(expected: '10', actual: $settings['timetable_generator_budget_minutes']);
		self::assertCount(expectedCount: 40, haystack: $this->service()->periodKeys());
	}//end testTheDefaults()

	/**
	 * Refused grids and budgets leave the stored value alone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function testRefusedValuesAreNotStored(): void {
		$refused = [
			'empty day list' => '{"days":[],"periods":[{"start":"08:30","end":"09:20"}]}',
			'unknown day' => '{"days":["mon","someday"],"periods":[{"start":"08:30","end":"09:20"}]}',
			'day twice' => '{"days":["mon","mon"],"periods":[{"start":"08:30","end":"09:20"}]}',
			'overlapping periods' => '{"days":["mon"],"periods":[{"start":"08:30","end":"09:20"},{"start":"09:00","end":"09:50"}]}',
			'ends before it starts' => '{"days":["mon"],"periods":[{"start":"09:20","end":"08:30"}]}',
			'no periods' => '{"days":["mon"],"periods":[]}',
			'bad time' => '{"days":["mon"],"periods":[{"start":"8.30","end":"09:20"}]}',
			'period not an object' => '{"days":["mon"],"periods":["08:30"]}',
			'not json' => 'mon to fri',
		];
		foreach ($refused as $why => $raw) {
			$this->service()->save(data: ['timetable_period_grid' => $raw]);
			self::assertSame(expected: [], actual: $this->stored, message: $why);
		}

		foreach (['0', '121', 'ten', '2.5'] as $budget) {
			$this->service()->save(data: ['timetable_generator_budget_minutes' => $budget]);
			self::assertSame(expected: [], actual: $this->stored, message: 'budget '.$budget);
		}

		$this->service()->save(data: ['default_columns' => '["x"]']);
		self::assertSame(expected: [], actual: $this->stored, message: 'other settings are not this service\'s');
	}//end testRefusedValuesAreNotStored()

	/**
	 * A good grid is stored in week order with trimmed times, and the period keys follow it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function testAGoodGridIsStoredInWeekOrder(): void {
		$this->service()->save(
			data: [
				'timetable_period_grid' => '{"days":["fri","mon"],"periods":[{"start":" 08:00","end":"08:45"},{"start":"08:45","end":"09:30"}]}',
				'timetable_generator_budget_minutes' => ' 30 ',
			]
		);

		self::assertSame(
			expected: '{"days":["mon","fri"],"periods":[{"start":"08:00","end":"08:45"},{"start":"08:45","end":"09:30"}]}',
			actual: $this->stored['timetable_period_grid']
		);
		self::assertSame(expected: '30', actual: $this->stored['timetable_generator_budget_minutes']);
		self::assertSame(expected: ['mon-1', 'mon-2', 'fri-1', 'fri-2'], actual: $this->service()->periodKeys());
	}//end testAGoodGridIsStoredInWeekOrder()
}//end class
