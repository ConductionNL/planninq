<?php

/**
 * Planninq Solve Run
 *
 * What stays the same during one solver call: the input, its placement options, the random numbers and the deadline.
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
 * The fixed parts of one solver call.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class SolveRun {

	/**
	 * Constructor.
	 *
	 * @param SolverInput                                                                                   $input    What to place.
	 * @param array{options:array<string,array<int,array<string,mixed>>>,blockers:array<string,array<int,string>>} $built    The options per lesson and the hard wishes that narrowed them.
	 * @param SeededRandom                                                                                  $random   The random numbers.
	 * @param float                                                                                         $deadline When to stop, as microtime.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly SolverInput $input,
		public readonly array $built,
		public readonly SeededRandom $random,
		public readonly float $deadline,
	) {
	}//end __construct()
}//end class
