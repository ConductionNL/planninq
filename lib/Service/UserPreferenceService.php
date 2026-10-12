<?php

/**
 * Planninq UserPreferenceService
 *
 * A person's own stored preferences: running timer, dashboard project order, due-reminder opt-out and board views.
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
 *
 * @spec openspec/specs/admin-user-settings.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCP\IConfig;

/**
 * Reads and stores a person's own preferences as user values.
 *
 * @spec openspec/specs/admin-user-settings.md
 */
class UserPreferenceService {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The user config interface.
	 */
	public function __construct(
		private IConfig $config,
	) {
	}//end __construct()

	/**
	 * The person's running timer, or null when none runs or the stored value is broken.
	 *
	 * @param string $userId The user UID.
	 *
	 * @return array{task: string, startedAt: string}|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function runningTimer(string $userId): ?array {
		$raw = $this->config->getUserValue($userId, Application::APP_ID, SettingsService::RUNNING_TIMER_KEY, '');
		if ((string)$raw === '') {
			return null;
		}

		return $this->cleanTimer(value: json_decode((string)$raw, true));
	}//end runningTimer()

	/**
	 * Store or clear the person's running timer; a malformed value is refused.
	 *
	 * @param string $userId The user UID.
	 * @param mixed  $timer  `{task, startedAt}`, or null to clear.
	 *
	 * @return bool Whether the value was stored or cleared.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function storeRunningTimer(string $userId, mixed $timer): bool {
		if ($timer === null) {
			$this->config->deleteUserValue($userId, Application::APP_ID, SettingsService::RUNNING_TIMER_KEY);
			return true;
		}

		$clean = $this->cleanTimer(value: $timer);
		if ($clean === null) {
			return false;
		}

		$this->config->setUserValue($userId, Application::APP_ID, SettingsService::RUNNING_TIMER_KEY, (string)json_encode($clean));
		return true;
	}//end storeRunningTimer()

	/**
	 * A timer with a task id and a parseable start time, or null.
	 *
	 * @param mixed $value The decoded value.
	 *
	 * @return array{task: string, startedAt: string}|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	private function cleanTimer(mixed $value): ?array {
		if (is_array($value) === false || is_string($value['task'] ?? null) === false || $value['task'] === '') {
			return null;
		}

		$started = $value['startedAt'] ?? null;
		if (is_string($started) === false || strtotime($started) === false) {
			return null;
		}

		return ['task' => $value['task'], 'startedAt' => $started];
	}//end cleanTimer()

	/**
	 * A user's own order of pinned dashboard projects.
	 *
	 * @param string $userId The user UID.
	 *
	 * @return array<int,string> Project ids, pinned first to last.
	 *
	 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
	 */
	public function dashboardOrder(string $userId): array {
		$raw     = $this->config->getUserValue($userId, Application::APP_ID, SettingsService::DASHBOARD_ORDER_KEY, '[]');
		$decoded = json_decode((string)$raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $this->cleanProjectOrder(order: $decoded);
	}//end dashboardOrder()

	/**
	 * Store a user's own order of pinned dashboard projects.
	 *
	 * @param string             $userId The user UID.
	 * @param array<mixed,mixed> $order  Project ids, pinned first to last.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
	 */
	public function storeDashboardOrder(string $userId, array $order): void {
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			SettingsService::DASHBOARD_ORDER_KEY,
			(string)json_encode($this->cleanProjectOrder(order: $order))
		);
	}//end storeDashboardOrder()

	/**
	 * Non-empty string ids, first occurrence kept, at most DASHBOARD_ORDER_MAX.
	 *
	 * @param array<mixed,mixed> $order The ids as given.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
	 */
	private function cleanProjectOrder(array $order): array {
		$clean = [];
		foreach ($order as $id) {
			if (is_string($id) === true && $id !== '' && in_array($id, $clean, true) === false) {
				$clean[] = $id;
			}
		}

		return array_slice($clean, 0, SettingsService::DASHBOARD_ORDER_MAX);
	}//end cleanProjectOrder()

	/**
	 * Whether the user wants due-date reminders (default on).
	 *
	 * @param string $userId The user UID.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
	 */
	public function notifyDueReminder(string $userId): bool {
		$value = $this->config->getUserValue($userId, Application::APP_ID, 'notify_due_reminder', 'true');
		return ($value !== 'false');
	}//end notifyDueReminder()

	/**
	 * Store the user's due-date reminder preference.
	 *
	 * @param string $userId  The user UID.
	 * @param bool   $enabled Whether reminders are wanted.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
	 */
	public function storeNotifyDueReminder(string $userId, bool $enabled): void {
		$storedValue = 'false';
		if ($enabled === true) {
			$storedValue = 'true';
		}

		$this->config->setUserValue($userId, Application::APP_ID, 'notify_due_reminder', $storedValue);
	}//end storeNotifyDueReminder()

	/**
	 * The per-person board view store, on this service's IConfig.
	 *
	 * @return BoardViewPreferenceService
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function boardViews(): BoardViewPreferenceService {
		return new BoardViewPreferenceService(config: $this->config);
	}//end boardViews()

	/**
	 * The user's board views as a settings entry, keyed as the frontend reads it.
	 *
	 * @param string $userId The user UID.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function boardViewSetting(string $userId): array {
		return [BoardViewPreferenceService::KEY => $this->boardViews()->views(userId: $userId)];
	}//end boardViewSetting()
}//end class
