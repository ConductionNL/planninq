<?php

/**
 * Planninq Timetable Input Builder
 *
 * Gathers what one generator run places: the period keys of the week grid,
 * the activities and rooms (from the app that owns the hour plan, answering a
 * typed event, or else from the sheets an admin uploaded) and the wishes, and
 * turns them into a SolverInput with one lesson per weekly lesson.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Event\TimetableActivitiesQueryEvent;
use OCA\Planninq\Timetabling\SolverInput;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;

/**
 * Builds the SolverInput of a generator run.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
 */
class TimetableInputBuilder {

	/**
	 * App config key holding the uploaded rooms sheet, parsed.
	 *
	 * @var string
	 */
	public const CSV_ROOMS_KEY = 'timetable_csv_rooms';

	/**
	 * App config key holding the uploaded activities sheet, parsed.
	 *
	 * @var string
	 */
	public const CSV_ACTIVITIES_KEY = 'timetable_csv_activities';

	/**
	 * Why an input is empty when nothing supplied activities.
	 *
	 * @var string
	 */
	public const NO_ACTIVITIES = 'No activities: learniq did not answer and no CSV was uploaded';

	/**
	 * The wish fields the solver reads.
	 *
	 * @var array<int,string>
	 */
	private const WISH_FIELDS = ['appliesTo', 'reference', 'kind', 'periods', 'limit', 'strength', 'weight'];

	/**
	 * Constructor.
	 *
	 * @param IEventDispatcher $dispatcher Dispatches the activities query.
	 * @param IAppConfig           $appConfig  Holds the uploaded sheets.
	 * @param TimetableGridService $grid       The week grid.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IEventDispatcher $dispatcher,
		private readonly IAppConfig $appConfig,
		private readonly TimetableGridService $grid,
	) {
	}//end __construct()

	/**
	 * The input of a run for one academic year and the given wishes.
	 *
	 * @param string                         $academicYear The academic year, such as `2026-2027`.
	 * @param array<int,array<string,mixed>> $wishes       timetableWish objects.
	 *
	 * @return SolverInput
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
	 */
	public function build(string $academicYear, array $wishes): SolverInput {
		$periods = $this->grid->periodKeys();
		$wishes  = array_map(fn (array $wish): array => $this->solverWish(wish: $wish), array_values($wishes));

		$event = new TimetableActivitiesQueryEvent(academicYear: $academicYear);
		$this->dispatcher->dispatchTyped($event);

		$activities = ($event->getActivities() ?? []);
		$rooms      = $event->getRooms();
		$source     = (string)$event->getAnsweredBy();
		if ($activities === []) {
			$activities = $this->stored(key: self::CSV_ACTIVITIES_KEY);
			$rooms      = $this->stored(key: self::CSV_ROOMS_KEY);
			$source     = 'csv';
		}

		if ($activities === []) {
			return new SolverInput(periods: $periods, rooms: [], lessons: [], wishes: $wishes, source: 'none', reason: self::NO_ACTIVITIES);
		}

		$lessons = $this->lessons(activities: $activities);
		return new SolverInput(periods: $periods, rooms: array_values($rooms), lessons: $lessons, wishes: $wishes, source: $source);
	}//end build()

	/**
	 * Keep an uploaded rooms and activities sheet as the input for runs no app answers.
	 *
	 * @param array<int,array<string,mixed>> $rooms      Parsed room rows.
	 * @param array<int,array<string,mixed>> $activities Parsed activity rows.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function storeUpload(array $rooms, array $activities): void {
		$this->appConfig->setValueString(Application::APP_ID, self::CSV_ROOMS_KEY, (string)json_encode(array_values($rooms)), lazy: true);
		$this->appConfig->setValueString(Application::APP_ID, self::CSV_ACTIVITIES_KEY, (string)json_encode(array_values($activities)), lazy: true);
	}//end storeUpload()

	/**
	 * One lesson per weekly lesson of each activity, keyed `{group}:{subject}:{n}`.
	 *
	 * @param array<int,array<string,mixed>> $activities Activity rows.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
	 */
	private function lessons(array $activities): array {
		$lessons = [];
		foreach ($activities as $activity) {
			$key   = ((string)($activity['group'] ?? '')).':'.((string)($activity['subject'] ?? ''));
			$count = max(0, (int)($activity['lessonsPerWeek'] ?? 0));
			for ($number = 1; $number <= $count; $number++) {
				$lessons[] = [
					'key'      => $key.':'.$number,
					'activity' => $key,
					'group'    => (string)($activity['group'] ?? ''),
					'subject'  => (string)($activity['subject'] ?? ''),
					'teacher'  => (string)($activity['teacher'] ?? ''),
					'roomType' => (string)($activity['roomType'] ?? ''),
					'length'   => max(1, (int)($activity['lessonLength'] ?? 1)),
				];
			}
		}

		return $lessons;
	}//end lessons()

	/**
	 * A wish as the solver reads it: its id and the fields that constrain.
	 *
	 * @param array<string,mixed> $wish A timetableWish object.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.1
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-6.1
	 */
	public function solverWish(array $wish): array {
		$out = ['id' => (string)($wish['id'] ?? ($wish['@self']['id'] ?? ''))];
		foreach (self::WISH_FIELDS as $field) {
			if (array_key_exists($field, $wish) === true) {
				$out[$field] = $wish[$field];
			}
		}

		return $out;
	}//end solverWish()

	/**
	 * A stored sheet, or an empty list.
	 *
	 * @param string $key The app config key.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function stored(string $key): array {
		$rows = json_decode($this->appConfig->getValueString(Application::APP_ID, $key, '[]', lazy: true), true);
		if (is_array($rows) === false) {
			return [];
		}

		return array_values(array_filter($rows, 'is_array'));
	}//end stored()
}//end class
