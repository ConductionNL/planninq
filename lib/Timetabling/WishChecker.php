<?php

/**
 * Planninq Wish Checker
 *
 * Tells which placed lessons break a timetable wish. The scorer uses it for
 * every wish; the solver uses forbids() to keep a hard wish while placing.
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
 * The five wish kinds, evaluated on placed lessons.
 *
 * A placed lesson (a slot) is: `lesson` (the input lesson), `room`, `periods`
 * (the period keys it takes), `day` and `order` (the grid position of its first period).
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
 */
final class WishChecker {

	/**
	 * The lesson field a wish on each subject compares with its reference.
	 */
	private const LESSON_FIELDS = [
		'teacher'  => 'teacher',
		'group'    => 'group',
		'activity' => 'activity',
	];

	/**
	 * The keys of the lessons that break the wish, in period order; empty when it is kept.
	 *
	 * @param array<string,mixed>            $wish  The wish.
	 * @param array<int,array<string,mixed>> $slots All placed lessons.
	 * @param WeekGrid                       $grid  The week grid.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function breakingLessons(array $wish, array $slots, WeekGrid $grid): array {
		$mine = array_values(
			array_filter($slots, fn (array $slot): bool => $this->applies(wish: $wish, lesson: $slot['lesson'], room: (string)$slot['room']))
		);
		usort($mine, static fn (array $one, array $two): int => $one['order'] <=> $two['order']);

		$breaking = match ((string)($wish['kind'] ?? '')) {
			'unavailable', 'avoid' => array_filter($mine, fn (array $slot): bool => $this->touches(wish: $wish, periods: $slot['periods'])),
			'maxPerDay'            => $this->overTheLimit(wish: $wish, slots: $mine),
			'noGaps'               => $this->daysWithGaps(slots: $mine, grid: $grid),
			'sameRoom'             => $this->outsideTheMainRoom(slots: $mine),
			default                => [],
		};

		return array_values(array_map(static fn (array $slot): string => (string)$slot['lesson']['key'], $breaking));
	}//end breakingLessons()

	/**
	 * Whether placing the lesson on these periods in this room breaks the wish by itself (the period kinds only).
	 *
	 * @param array<string,mixed> $wish    The wish.
	 * @param array<string,mixed> $lesson  The lesson.
	 * @param array<int,string>   $periods The periods it would take.
	 * @param string              $room    The room.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function forbids(array $wish, array $lesson, array $periods, string $room): bool {
		if (in_array(($wish['kind'] ?? ''), ['unavailable', 'avoid'], true) === false) {
			return false;
		}

		return $this->applies(wish: $wish, lesson: $lesson, room: $room) === true && $this->touches(wish: $wish, periods: $periods) === true;
	}//end forbids()

	/**
	 * Whether a wish is about this lesson.
	 *
	 * @param array<string,mixed> $wish   The wish.
	 * @param array<string,mixed> $lesson The lesson.
	 * @param string              $room   The room it is in.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-4.1
	 */
	public function applies(array $wish, array $lesson, string $room): bool {
		$reference = (string)($wish['reference'] ?? '');
		$appliesTo = (string)($wish['appliesTo'] ?? '');
		if ($appliesTo === 'room') {
			return $room === $reference;
		}

		$field = (self::LESSON_FIELDS[$appliesTo] ?? null);
		return $field !== null && (string)($lesson[$field] ?? '') === $reference;
	}//end applies()

	/**
	 * Whether the periods include one of the wish's periods.
	 *
	 * @param array<string,mixed> $wish    The wish.
	 * @param array<int,string>   $periods The periods.
	 *
	 * @return bool
	 */
	private function touches(array $wish, array $periods): bool {
		return array_intersect($periods, (array)($wish['periods'] ?? [])) !== [];
	}//end touches()

	/**
	 * The lessons past the wish's limit on each day.
	 *
	 * @param array<string,mixed>            $wish  The wish.
	 * @param array<int,array<string,mixed>> $slots Its lessons, in period order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function overTheLimit(array $wish, array $slots): array {
		$limit = (int)($wish['limit'] ?? 0);
		if ($limit < 1) {
			return [];
		}

		$over = [];
		foreach ($this->byDay(slots: $slots) as $day) {
			$over = array_merge($over, array_slice($day, $limit));
		}

		return $over;
	}//end overTheLimit()

	/**
	 * The lessons of every day that has a free period between two of them.
	 *
	 * @param array<int,array<string,mixed>> $slots Its lessons, in period order.
	 * @param WeekGrid                       $grid  The week grid.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function daysWithGaps(array $slots, WeekGrid $grid): array {
		$broken = [];
		foreach ($this->byDay(slots: $slots) as $day) {
			$numbers = [];
			foreach ($day as $slot) {
				$numbers = array_merge($numbers, array_map([$grid, 'number'], $slot['periods']));
			}

			if ($grid->gaps(numbers: $numbers) > 0) {
				$broken = array_merge($broken, $day);
			}
		}

		return $broken;
	}//end daysWithGaps()

	/**
	 * The lessons that are not in the room most of them use.
	 *
	 * @param array<int,array<string,mixed>> $slots Its lessons, in period order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function outsideTheMainRoom(array $slots): array {
		$counts = array_count_values(array_map(static fn (array $slot): string => (string)$slot['room'], $slots));
		if (count($counts) < 2) {
			return [];
		}

		arsort($counts);
		$main = (string)array_key_first($counts);
		return array_filter($slots, static fn (array $slot): bool => (string)$slot['room'] !== $main);
	}//end outsideTheMainRoom()

	/**
	 * Lessons grouped by day, order kept.
	 *
	 * @param array<int,array<string,mixed>> $slots Lessons in period order.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function byDay(array $slots): array {
		$days = [];
		foreach ($slots as $slot) {
			$days[(string)$slot['day']][] = $slot;
		}

		return $days;
	}//end byDay()
}//end class
