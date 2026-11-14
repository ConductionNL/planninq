<?php

/**
 * Planninq Week Grid
 *
 * The period keys of one generator input read as days with numbered periods,
 * so a lesson longer than one period can be laid over the periods it takes.
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
 * Days and period numbers of a list of period keys such as `mon-3`.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
 */
final class WeekGrid {

	/**
	 * The period keys, as a set.
	 *
	 * @var array<string,true>
	 */
	private array $known = [];

	/**
	 * Constructor.
	 *
	 * @param array<int,string> $periods The period keys of the input.
	 *
	 * @return void
	 */
	public function __construct(private readonly array $periods) {
		foreach ($periods as $key) {
			$this->known[$key] = true;
		}
	}//end __construct()

	/**
	 * The period keys, in grid order.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function keys(): array {
		return $this->periods;
	}//end keys()

	/**
	 * The periods a lesson of $length periods starting at $start takes, or null when it runs off the day or starts outside the grid.
	 *
	 * @param string $start  The first period key.
	 * @param int    $length The length in periods.
	 *
	 * @return array<int,string>|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function span(string $start, int $length): ?array {
		$day    = $this->day(key: $start);
		$number = $this->number(key: $start);
		$keys   = [];
		for ($offset = 0; $offset < max(1, $length); $offset++) {
			$key = $day.'-'.($number + $offset);
			if (isset($this->known[$key]) === false) {
				return null;
			}

			$keys[] = $key;
		}

		return $keys;
	}//end span()

	/**
	 * The day of a period key, as in `mon`.
	 *
	 * @param string $key The period key.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function day(string $key): string {
		return explode('-', $key, 2)[0];
	}//end day()

	/**
	 * The period number of a period key, from 1.
	 *
	 * @param string $key The period key.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function number(string $key): int {
		return (int)(explode('-', $key, 2)[1] ?? 0);
	}//end number()

	/**
	 * The free periods between the first and the last of a day's taken period numbers.
	 *
	 * @param array<int,int> $numbers Taken period numbers of one day.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function gaps(array $numbers): int {
		if ($numbers === []) {
			return 0;
		}

		$unique = array_unique($numbers);
		return (max($unique) - min($unique) + 1 - count($unique));
	}//end gaps()
}//end class
