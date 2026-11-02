<?php

/**
 * Planninq Scoring Run
 *
 * What one score() call looks things up in: the input, its week grid, and the lessons, rooms and period order keyed for lookup.
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
 * The lookups of one score() call.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
 */
final class ScoringRun {

	/**
	 * The week grid of the input's periods.
	 *
	 * @var WeekGrid
	 */
	public readonly WeekGrid $grid;

	/**
	 * The lessons by key.
	 *
	 * @var array<array-key,array<string,mixed>>
	 */
	private readonly array $lessons;

	/**
	 * The rooms by reference.
	 *
	 * @var array<array-key,array<string,mixed>>
	 */
	private readonly array $rooms;

	/**
	 * The position of each period in the grid.
	 *
	 * @var array<array-key,int>
	 */
	private readonly array $order;

	/**
	 * Key the input for lookup.
	 *
	 * @param SolverInput $input What was to be placed.
	 *
	 * @return void
	 */
	public function __construct(public readonly SolverInput $input) {
		$this->grid    = new WeekGrid(periods: $input->periods);
		$this->lessons = array_column($input->lessons, null, 'key');
		$this->rooms   = array_column($input->rooms, null, 'reference');
		$this->order   = array_flip($this->grid->keys());
	}//end __construct()

	/**
	 * The lesson with a key, or null.
	 *
	 * @param string $key The lesson key.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function lesson(string $key): ?array {
		return ($this->lessons[$key] ?? null);
	}//end lesson()

	/**
	 * The room with a reference, or null.
	 *
	 * @param string $reference The room reference.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function room(string $reference): ?array {
		return ($this->rooms[$reference] ?? null);
	}//end room()

	/**
	 * The position of a period in the grid.
	 *
	 * @param string $period The period key.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function order(string $period): int {
		return (int)$this->order[$period];
	}//end order()
}//end class
