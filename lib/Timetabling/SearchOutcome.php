<?php

/**
 * Planninq Search Outcome
 *
 * Where the search ended: the final placements, the lessons the repair took off and the moves tried.
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
 * The end state of one search, before it becomes a SolverResult.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class SearchOutcome {

	/**
	 * Constructor.
	 *
	 * @param SearchState          $state      The final state.
	 * @param array<string,string> $repaired   The lessons taken off by the repair, with the wish.
	 * @param int                  $iterations The moves tried.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly SearchState $state,
		public readonly array $repaired,
		public readonly int $iterations,
	) {
	}//end __construct()
}//end class
