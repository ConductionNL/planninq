<?php

/**
 * Planninq Placement Candidate
 *
 * One lesson the solver is about to place while constructing: its key, the lesson, the soft wishes that price its options and the search state.
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
 * A lesson to place, with what prices its options.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class PlacementCandidate {

	/**
	 * Hold the lesson to place.
	 *
	 * @param string                         $key    The lesson key.
	 * @param array<string,mixed>            $lesson The lesson.
	 * @param array<int,array<string,mixed>> $soft   The soft wishes.
	 * @param SearchState                    $state  The search state.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly string $key,
		public readonly array $lesson,
		public readonly array $soft,
		public readonly SearchState $state,
	) {
	}//end __construct()
}//end class
