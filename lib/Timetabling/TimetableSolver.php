<?php

/**
 * Planninq Timetable Solver
 *
 * The one thing the rest of planninq calls to place lessons (design decision 1),
 * so a second engine can be added later without a data migration.
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
 * Places the lessons of a solver input on the week grid.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
 */
interface TimetableSolver {

	/**
	 * Place the lessons, for at most the given number of seconds.
	 *
	 * @param SolverInput                                                $input   What to place.
	 * @param int                                                        $seconds The most time this call may take.
	 * @param int                                                        $seed    The number the search starts from; the same seed gives the same result.
	 * @param array<int,array{lesson:string,period:string,room:string}> $start   Placements to continue from (the best of an earlier step).
	 *
	 * @return SolverResult
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.1
	 */
	public function solve(SolverInput $input, int $seconds, int $seed, array $start=[]): SolverResult;
}//end interface
