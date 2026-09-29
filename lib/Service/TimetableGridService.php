<?php

/**
 * Planninq Timetable Grid Service
 *
 * The school week the timetable generator places lessons in: the days and the
 * periods with their start and end times, and how long one generator run may take.
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

use OCA\Planninq\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Reads, validates and stores the week grid and the generator time budget.
 * The settings page reads and writes them through SettingsController, next to
 * the other admin settings.
 *
 * A grid is JSON: {"days": ["mon", ...], "periods": [{"start": "08:30", "end": "09:20"}, ...]}.
 * A period key is the day and the period number from 1, as in `mon-3`.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
 */
class TimetableGridService {

	/**
	 * Admin setting holding the week grid.
	 *
	 * @var string
	 */
	public const GRID_KEY = 'timetable_period_grid';

	/**
	 * Admin setting holding the generator time budget in minutes.
	 *
	 * @var string
	 */
	public const BUDGET_KEY = 'timetable_generator_budget_minutes';

	/**
	 * The default budget: ten minutes.
	 *
	 * @var string
	 */
	public const DEFAULT_BUDGET = '10';

	/**
	 * The longest budget an admin may set, in minutes.
	 *
	 * @var integer
	 */
	public const BUDGET_MAX = 120;

	/**
	 * The days a grid may use, in week order.
	 *
	 * @var array<int,string>
	 */
	public const WEEK_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

	/**
	 * Monday to Friday, eight periods of 50 minutes from 08:30.
	 *
	 * @var string
	 */
	public const DEFAULT_GRID = '{"days":["mon","tue","wed","thu","fri"],"periods":['
		.'{"start":"08:30","end":"09:20"},{"start":"09:20","end":"10:10"},'
		.'{"start":"10:10","end":"11:00"},{"start":"11:00","end":"11:50"},'
		.'{"start":"11:50","end":"12:40"},{"start":"12:40","end":"13:30"},'
		.'{"start":"13:30","end":"14:20"},{"start":"14:20","end":"15:10"}]}';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the two settings.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Both settings, the defaults where nothing is stored.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function settings(): array {
		return [
			self::GRID_KEY   => $this->appConfig->getValueString(Application::APP_ID, self::GRID_KEY, self::DEFAULT_GRID),
			self::BUDGET_KEY => $this->appConfig->getValueString(Application::APP_ID, self::BUDGET_KEY, self::DEFAULT_BUDGET),
		];
	}//end settings()

	/**
	 * Store the grid and budget found in submitted settings; a refused value is not stored.
	 *
	 * @param array<string,mixed> $data The submitted settings.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function save(array $data): void {
		$normalised = [
			self::GRID_KEY   => $this->normaliseGrid(raw: (string)($data[self::GRID_KEY] ?? '')),
			self::BUDGET_KEY => $this->normaliseBudget(raw: (string)($data[self::BUDGET_KEY] ?? '')),
		];
		foreach ($normalised as $key => $value) {
			if (array_key_exists($key, $data) === true && $value !== null) {
				$this->appConfig->setValueString(Application::APP_ID, $key, $value);
			}
		}
	}//end save()

	/**
	 * The period keys of the stored grid, day by day, as in `mon-1`.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function periodKeys(): array {
		$grid = (array)json_decode(($this->normaliseGrid(raw: $this->settings()[self::GRID_KEY]) ?? self::DEFAULT_GRID), true);
		$keys = [];
		foreach ((array)$grid['days'] as $day) {
			$count = count((array)$grid['periods']);
			for ($number = 1; $number <= $count; $number++) {
				$keys[] = $day.'-'.$number;
			}
		}

		return $keys;
	}//end periodKeys()

	/**
	 * A submitted grid as it is stored, or null to refuse it.
	 *
	 * Refused: not JSON, no days, a day that is not a week day or is listed
	 * twice, no periods, a time that is not HH:MM, a period that ends before
	 * it starts, and a period that starts before the previous one ends.
	 *
	 * @param string $raw The submitted value.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function normaliseGrid(string $raw): ?string {
		$grid = json_decode($raw, true);
		if (is_array($grid) === false) {
			return null;
		}

		$days    = $this->days(value: ($grid['days'] ?? null));
		$periods = $this->periods(value: ($grid['periods'] ?? null));
		if ($days === null || $periods === null) {
			return null;
		}

		return (string)json_encode(['days' => $days, 'periods' => $periods]);
	}//end normaliseGrid()

	/**
	 * A submitted budget in whole minutes from 1 to BUDGET_MAX, or null to refuse it.
	 *
	 * @param string $raw The submitted value.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	public function normaliseBudget(string $raw): ?string {
		$trimmed = trim($raw);
		if (preg_match('/^\d+$/', $trimmed) !== 1) {
			return null;
		}

		$minutes = (int)$trimmed;
		if ($minutes < 1 || $minutes > self::BUDGET_MAX) {
			return null;
		}

		return (string)$minutes;
	}//end normaliseBudget()

	/**
	 * The listed days in week order, or null when the list is refused.
	 *
	 * @param mixed $value The submitted days.
	 *
	 * @return array<int,string>|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	private function days(mixed $value): ?array {
		if (is_array($value) === false || $value === []) {
			return null;
		}

		foreach ($value as $day) {
			if (in_array($day, self::WEEK_DAYS, true) === false) {
				return null;
			}
		}

		if (count(array_unique($value)) !== count($value)) {
			return null;
		}

		return array_values(array_intersect(self::WEEK_DAYS, $value));
	}//end days()

	/**
	 * The periods with trimmed times, or null when one is malformed or overlaps the one before.
	 *
	 * @param mixed $value The submitted periods.
	 *
	 * @return array<int,array{start:string,end:string}>|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	private function periods(mixed $value): ?array {
		if (is_array($value) === false || $value === []) {
			return null;
		}

		$periods  = [];
		$previous = '00:00';
		foreach (array_values($value) as $period) {
			if (is_array($period) === false) {
				return null;
			}

			$start = $this->time(value: ($period['start'] ?? null));
			$end   = $this->time(value: ($period['end'] ?? null));
			if ($start === null || $end === null || $end <= $start || $start < $previous) {
				return null;
			}

			$periods[] = ['start' => $start, 'end' => $end];
			$previous  = $end;
		}

		return $periods;
	}//end periods()

	/**
	 * A time of day as HH:MM, or null.
	 *
	 * @param mixed $value The submitted time.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-1.2
	 */
	private function time(mixed $value): ?string {
		if (is_string($value) === false || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim($value)) !== 1) {
			return null;
		}

		return trim($value);
	}//end time()
}//end class
