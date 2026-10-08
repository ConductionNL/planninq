<?php

/**
 * Planninq Placement Options
 *
 * Every start period and room a lesson may take: the lesson fits the day, the room
 * has the type the lesson needs, and no hard period wish forbids it. The hard wishes that took options
 * away are kept per lesson, so an unplaced lesson can name the wish that blocked it.
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
 * The options of every lesson, and the hard wishes that narrowed them.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class PlacementOptions {

	/**
	 * Constructor.
	 *
	 * @param WishChecker $wishes Evaluates the wishes.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly WishChecker $wishes=new WishChecker(),
	) {
	}//end __construct()

	/**
	 * The options per lesson key, and per lesson key the ids of the hard wishes that removed options.
	 *
	 * @param SolverInput $input The input.
	 *
	 * @return array{options:array<string,array<int,array{period:string,room:string,periods:array<int,string>}>>,blockers:array<string,array<int,string>>}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function build(SolverInput $input): array {
		$grid     = new WeekGrid(periods: $input->periods);
		$hard     = array_values(array_filter($input->wishes, static fn (array $wish): bool => ($wish['strength'] ?? '') === 'hard'));
		$shared   = [];
		$options  = [];
		$blockers = [];
		foreach ($input->lessons as $lesson) {
			$key       = (string)$lesson['key'];
			$signature = (string)($lesson['roomType'] ?? '')."\n".(int)($lesson['length'] ?? 1);
			$shared[$signature] ??= $this->allOptions(input: $input, grid: $grid, lesson: $lesson);
			$options[$key]  = [];
			$blockers[$key] = [];
			foreach ($shared[$signature] as $option) {
				$blocking = $this->blocking(wishes: $hard, lesson: $lesson, periods: $option['periods'], room: $option['room']);
				if ($blocking !== null) {
					$blockers[$key][$blocking] = $blocking;
					continue;
				}

				$options[$key][] = $option;
			}

			$blockers[$key] = array_values($blockers[$key]);
		}

		return ['options' => $options, 'blockers' => $blockers];
	}//end build()

	/**
	 * Every start period and room of the right type a lesson of this length fits in; lessons
	 * with the same room type and length share this list, so a large school stays small in memory.
	 *
	 * @param SolverInput         $input  The input.
	 * @param WeekGrid            $grid   The week grid.
	 * @param array<string,mixed> $lesson A lesson of the signature.
	 *
	 * @return array<int,array{period:string,room:string,periods:array<int,string>}>
	 */
	private function allOptions(SolverInput $input, WeekGrid $grid, array $lesson): array {
		$options = [];
		foreach ($this->rooms(input: $input, lesson: $lesson) as $room) {
			foreach ($grid->keys() as $start) {
				$periods = $grid->span(start: $start, length: (int)($lesson['length'] ?? 1));
				if ($periods !== null) {
					$options[] = ['period' => $start, 'room' => $room, 'periods' => $periods];
				}
			}
		}

		return $options;
	}//end allOptions()

	/**
	 * The rooms of the type the lesson needs; every room when it needs none.
	 *
	 * @param SolverInput         $input  The input.
	 * @param array<string,mixed> $lesson The lesson.
	 *
	 * @return array<int,string>
	 */
	private function rooms(SolverInput $input, array $lesson): array {
		$needed = (string)($lesson['roomType'] ?? '');
		$rooms  = [];
		foreach ($input->rooms as $room) {
			if ($needed === '' || (string)($room['type'] ?? '') === $needed) {
				$rooms[] = (string)$room['reference'];
			}
		}

		return $rooms;
	}//end rooms()

	/**
	 * The id of the first hard wish that forbids this option, or null.
	 *
	 * @param array<int,array<string,mixed>> $wishes  The hard wishes.
	 * @param array<string,mixed>            $lesson  The lesson.
	 * @param array<int,string>              $periods The periods it would take.
	 * @param string                         $room    The room.
	 *
	 * @return string|null
	 */
	private function blocking(array $wishes, array $lesson, array $periods, string $room): ?string {
		foreach ($wishes as $wish) {
			if ($this->wishes->forbids(wish: $wish, lesson: $lesson, periods: $periods, room: $room) === true) {
				return (string)($wish['id'] ?? '');
			}
		}

		return null;
	}//end blocking()
}//end class
