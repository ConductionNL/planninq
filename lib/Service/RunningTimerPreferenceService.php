<?php

/**
 * Planninq Running Timer Preference Service
 *
 * A person's one running timer: the task it runs on and when it started.
 * It is a personal preference, not a time entry, so nothing is booked while
 * it runs and a reload or another device shows the same timer.
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

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Planninq\AppInfo\Application;
use OCP\IConfig;

/**
 * Stores each person's running timer as one user value.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
 */
class RunningTimerPreferenceService {

	/**
	 * The user value holding the running timer.
	 *
	 * @var string
	 */
	public const KEY = 'running_timer';

	/**
	 * The longest task id accepted.
	 *
	 * @var int
	 */
	private const MAX_TASK_LENGTH = 64;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Per-user values.
	 */
	public function __construct(
		private IConfig $config,
	) {
	}//end __construct()

	/**
	 * A person's running timer, or null when none runs.
	 *
	 * A stored value that no longer validates reads as no timer.
	 *
	 * @param string $userId The user UID.
	 *
	 * @return array{task:string,startedAt:string}|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function get(string $userId): ?array {
		$raw = (string)$this->config->getUserValue($userId, Application::APP_ID, self::KEY, '');
		if ($raw === '') {
			return null;
		}

		return $this->normalise(value: json_decode($raw, true));
	}//end get()

	/**
	 * Store, or clear, a person's running timer.
	 *
	 * Null or an empty string clears it. A malformed value is refused and
	 * leaves the stored timer as it was.
	 *
	 * @param string $userId The user UID.
	 * @param mixed  $value  `{task, startedAt}` (array or JSON string), or null to clear.
	 *
	 * @return bool True when the value was stored or cleared, false when it was refused.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function save(string $userId, mixed $value): bool {
		if ($value === null || $value === '') {
			$this->config->deleteUserValue($userId, Application::APP_ID, self::KEY);
			return true;
		}

		if (is_string($value) === true) {
			$value = json_decode($value, true);
		}

		$timer = $this->normalise(value: $value);
		if ($timer === null) {
			return false;
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::KEY, (string)json_encode($timer));
		return true;
	}//end save()

	/**
	 * A valid timer, or null.
	 *
	 * @param mixed $value The value as given.
	 *
	 * @return array{task:string,startedAt:string}|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	private function normalise(mixed $value): ?array {
		if (is_array($value) === false) {
			return null;
		}

		$task      = $value['task'] ?? null;
		$startedAt = $value['startedAt'] ?? null;
		if (is_string($task) === false || is_string($startedAt) === false) {
			return null;
		}

		$task = trim($task);
		if ($task === '' || strlen($task) > self::MAX_TASK_LENGTH || preg_match('/^[A-Za-z0-9_-]+$/', $task) !== 1) {
			return null;
		}

		$started = $this->instant(value: trim($startedAt));
		if ($started === null) {
			return null;
		}

		return ['task' => $task, 'startedAt' => $started];
	}//end normalise()

	/**
	 * An ISO 8601 date-time with an offset, in ATOM form, or null.
	 *
	 * @param string $value The submitted start.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	private function instant(string $value): ?string {
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value) !== 1) {
			return null;
		}

		try {
			return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
		} catch (\Exception $e) {
			return null;
		}
	}//end instant()
}//end class
