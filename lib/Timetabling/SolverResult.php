<?php

/**
 * Planninq Solver Result
 *
 * What one solver run returns: the placements, the lessons it could not place with
 * the hard wish that blocked them, the wishes it broke and the scorer's measures.
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
 * The outcome of one solver run.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
 */
final class SolverResult {

	/**
	 * Constructor.
	 *
	 * @param array<int,array{lesson:string,period:string,room:string}>  $placements   Each placed lesson with its start period and room.
	 * @param array<int,array{lesson:string,wish:?string,reason:string}> $unplaced     Each lesson without a place, with the hard wish that blocked it.
	 * @param array<int,array<string,mixed>>                            $brokenWishes The wishes the placements break.
	 * @param array<string,mixed>                                       $metrics      The scorer's measures.
	 * @param int                                                       $cost         The one number the search minimised.
	 * @param int                                                       $iterations   How many moves the search tried.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly array $placements,
		public readonly array $unplaced,
		public readonly array $brokenWishes,
		public readonly array $metrics,
		public readonly int $cost,
		public readonly int $iterations,
	) {
	}//end __construct()

	/**
	 * The fields a timetableScenario keeps of a run.
	 *
	 * @return array{placements:array<int,array<string,string>>,unplaced:array<int,array<string,mixed>>,brokenWishes:array<int,array<string,mixed>>,metrics:array<string,mixed>}
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
	 */
	public function toScenario(): array {
		return [
			'placements'   => $this->placements,
			'unplaced'     => $this->unplaced,
			'brokenWishes' => $this->brokenWishes,
			'metrics'      => $this->metrics,
		];
	}//end toScenario()
}//end class
