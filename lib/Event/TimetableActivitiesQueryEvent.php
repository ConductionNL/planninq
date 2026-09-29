<?php

/**
 * Planninq Timetable Activities Query Event
 *
 * The typed door (ADR-041) through which planninq asks the app that owns the
 * hour plan (learniq) for the activities and rooms of an academic year, so the
 * timetable generator can place them. Planninq dispatches it; a listener in
 * the owning app answers it in this event's result slot before dispatch
 * returns. An event nobody answers means "no activities from another app": the
 * generator then uses an uploaded CSV, or reports that it has no input.
 *
 * The owning app looks this class up by name, guards it with
 * `class_exists()`, and never needs planninq installed to load.
 *
 * @category Event
 * @package  OCA\Planninq\Event
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

namespace OCA\Planninq\Event;

use OCP\EventDispatcher\Event;

/**
 * A request for the activities and rooms of one academic year.
 *
 * An activity row: group, subject, teacher (a Nextcloud user id), lessonsPerWeek,
 * lessonLength (in periods) and roomType. A room row: reference, capacity, type.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
 */
class TimetableActivitiesQueryEvent extends Event {

	/**
	 * Contract version of this event and its answer.
	 *
	 * @var integer
	 */
	public const CONTRACT_VERSION = 1;

	/**
	 * The activities, once an app answered.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $activities = null;

	/**
	 * The rooms, once an app answered.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $rooms = [];

	/**
	 * The app that answered.
	 *
	 * @var string|null
	 */
	private ?string $answeredBy = null;

	/**
	 * Constructor.
	 *
	 * @param string $academicYear The academic year asked for, such as `2026-2027`.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly string $academicYear,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * The academic year asked for.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function getAcademicYear(): string {
		return $this->academicYear;
	}//end getAcademicYear()

	/**
	 * Answer the event with the year's activities and rooms.
	 *
	 * @param string                         $app        The answering app, such as `learniq`.
	 * @param array<int,array<string,mixed>> $activities Activity rows.
	 * @param array<int,array<string,mixed>> $rooms      Room rows.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function answer(string $app, array $activities, array $rooms): void {
		$this->answeredBy = $app;
		$this->activities = array_values($activities);
		$this->rooms      = array_values($rooms);
	}//end answer()

	/**
	 * The activities, or null when no app answered.
	 *
	 * @return array<int,array<string,mixed>>|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function getActivities(): ?array {
		return $this->activities;
	}//end getActivities()

	/**
	 * The rooms the answering app sent.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function getRooms(): array {
		return $this->rooms;
	}//end getRooms()

	/**
	 * The app that answered, or null.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-2.1
	 */
	public function getAnsweredBy(): ?string {
		return $this->answeredBy;
	}//end getAnsweredBy()
}//end class
