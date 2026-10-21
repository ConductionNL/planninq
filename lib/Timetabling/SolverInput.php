<?php

/**
 * Planninq Solver Input
 *
 * What the timetable generator is asked to place: the period keys of the week
 * grid, the rooms, the lessons and the wishes. Plain data, JSON-serialisable,
 * so a scenario can keep it and a second solver engine can read the same shape.
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
 * The input of one generator run.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
 */
final class SolverInput {

	/**
	 * Constructor.
	 *
	 * @param array<int,string>              $periods Period keys, such as `mon-1`.
	 * @param array<int,array<string,mixed>> $rooms   Rooms: reference, capacity, type.
	 * @param array<int,array<string,mixed>> $lessons Lessons: key, activity, group, subject, teacher, roomType, length.
	 * @param array<int,array<string,mixed>> $wishes  Wishes: id, appliesTo, reference, kind, periods, limit, strength, weight.
	 * @param string                         $source  Where the activities came from: an app id, `csv` or `none`.
	 * @param string|null                    $reason  Why the input is empty, or null.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly array $periods,
		public readonly array $rooms,
		public readonly array $lessons,
		public readonly array $wishes,
		public readonly string $source,
		public readonly ?string $reason=null,
	) {
	}//end __construct()

	/**
	 * Whether there is nothing to place.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
	 */
	public function isEmpty(): bool {
		return $this->lessons === [];
	}//end isEmpty()

	/**
	 * The shape a timetableScenario keeps under `input`.
	 *
	 * @return array{periods:array<int,string>,rooms:array<int,array<string,mixed>>,lessons:array<int,array<string,mixed>>,wishes:array<int,array<string,mixed>>,source:string}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
	 */
	public function toArray(): array {
		return [
			'periods' => $this->periods,
			'rooms'   => $this->rooms,
			'lessons' => $this->lessons,
			'wishes'  => $this->wishes,
			'source'  => $this->source,
		];
	}//end toArray()
}//end class
