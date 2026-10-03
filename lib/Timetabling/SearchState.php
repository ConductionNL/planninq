<?php

/**
 * Planninq Search State
 *
 * Where each lesson is during a search, and which teacher, group and room is busy in which period, so a move is only made to a free place.
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
 * The placements of a search and the periods they keep busy.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class SearchState {

	/**
	 * The option each placed lesson has, by lesson key.
	 *
	 * @var array<string,array{period:string,room:string,periods:array<int,string>}>
	 */
	private array $placed = [];

	/**
	 * Busy teacher, group and room periods, by "kind, reference, period".
	 *
	 * @var array<string,string>
	 */
	private array $busy = [];

	/**
	 * Lessons per teacher or group and day, by "field, reference, day".
	 *
	 * @var array<string,int>
	 */
	private array $days = [];

	/**
	 * Constructor.
	 *
	 * @param array<string,array<string,mixed>> $lessons The lessons by key.
	 *
	 * @return void
	 */
	public function __construct(private readonly array $lessons) {
	}//end __construct()

	/**
	 * Place a lesson when the option is free for its teacher, group and room.
	 *
	 * @param string                                                            $key    The lesson key.
	 * @param array{period:string,room:string,periods:array<int,string>} $option The option.
	 *
	 * @return bool Whether it was placed.
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function place(string $key, array $option): bool {
		if ($this->fits(key: $key, option: $option) === false) {
			return false;
		}

		foreach ($this->marks(key: $key, option: $option) as $mark) {
			$this->busy[$mark] = $key;
		}

		$this->placed[$key] = $option;
		foreach ($this->dayMarks(key: $key, option: $option) as $mark) {
			$this->days[$mark] = (($this->days[$mark] ?? 0) + 1);
		}

		return true;
	}//end place()

	/**
	 * Whether the option is free for the lesson's teacher, group and room.
	 *
	 * @param string                                                            $key    The lesson key.
	 * @param array{period:string,room:string,periods:array<int,string>} $option The option.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function fits(string $key, array $option): bool {
		foreach ($this->marks(key: $key, option: $option) as $mark) {
			if (isset($this->busy[$mark]) === true) {
				return false;
			}
		}

		return true;
	}//end fits()

	/**
	 * Take a lesson off the grid.
	 *
	 * @param string $key The lesson key.
	 *
	 * @return array{period:string,room:string,periods:array<int,string>}|null The option it had.
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function remove(string $key): ?array {
		$option = ($this->placed[$key] ?? null);
		if ($option === null) {
			return null;
		}

		foreach ($this->marks(key: $key, option: $option) as $mark) {
			unset($this->busy[$mark]);
		}

		foreach ($this->dayMarks(key: $key, option: $option) as $mark) {
			$this->days[$mark]--;
		}

		unset($this->placed[$key]);
		return $option;
	}//end remove()

	/**
	 * The option a lesson has, or null.
	 *
	 * @param string $key The lesson key.
	 *
	 * @return array{period:string,room:string,periods:array<int,string>}|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function option(string $key): ?array {
		return ($this->placed[$key] ?? null);
	}//end option()

	/**
	 * How many lessons a teacher or group already has on the day of a period.
	 *
	 * @param string $field     `teacher` or `group`.
	 * @param string $reference The teacher or group.
	 * @param string $day       The day, as in `mon`.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function onDay(string $field, string $reference, string $day): int {
		return ($this->days[$field."\n".$reference."\n".$day] ?? 0);
	}//end onDay()

	/**
	 * The placements as the scorer and the scenario read them, in lesson order.
	 *
	 * @return array<int,array{lesson:string,period:string,room:string}>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function placements(): array {
		$list = [];
		foreach (array_keys($this->lessons) as $key) {
			$option = ($this->placed[$key] ?? null);
			if ($option !== null) {
				$list[] = ['lesson' => (string)$key, 'period' => $option['period'], 'room' => $option['room']];
			}
		}

		return $list;
	}//end placements()

	/**
	 * The teacher and group day counters a lesson on an option adds to.
	 *
	 * @param string                                                            $key    The lesson key.
	 * @param array{period:string,room:string,periods:array<int,string>} $option The option.
	 *
	 * @return array<int,string>
	 */
	private function dayMarks(string $key, array $option): array {
		$lesson = ($this->lessons[$key] ?? []);
		$day    = explode('-', $option['period'], 2)[0];
		return [
			'teacher'."\n".(string)($lesson['teacher'] ?? '')."\n".$day,
			'group'."\n".(string)($lesson['group'] ?? '')."\n".$day,
		];
	}//end dayMarks()

	/**
	 * The busy marks a lesson on an option makes.
	 *
	 * @param string                                                            $key    The lesson key.
	 * @param array{period:string,room:string,periods:array<int,string>} $option The option.
	 *
	 * @return array<int,string>
	 */
	private function marks(string $key, array $option): array {
		$lesson = ($this->lessons[$key] ?? []);
		$owners = ['teacher' => (string)($lesson['teacher'] ?? ''), 'group' => (string)($lesson['group'] ?? ''), 'room' => $option['room']];
		$marks  = [];
		foreach ($option['periods'] as $period) {
			foreach ($owners as $kind => $reference) {
				if ($reference !== '') {
					$marks[] = $kind."\n".$reference."\n".$period;
				}
			}
		}

		return $marks;
	}//end marks()
}//end class
