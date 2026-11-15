<?php

/**
 * Planninq TaskCalendarExportService
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one-way export of a person's planninq tasks to a "Planninq" task list in
 * their own Nextcloud calendar home (planning-calendar). Planninq is the only
 * writer that counts: every write replaces the whole VTODO.
 *
 * The write path (design.md, "Task 2.2 outcome"): Nextcloud's public calendar
 * API can create a VTODO but cannot replace or delete one, and cannot create a
 * calendar. Those calls live on the DAV app's CalDavBackend, which is not a
 * public API, so it is resolved by name behind class_exists() and a method
 * probe, the way hermiq's task tools resolve it. Without it the export reports
 * itself unavailable and the switch is not offered.
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
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\BackgroundJob\TaskCalendarBackfillJob;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes and removes the VTODOs of the "Planninq" task list.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
 */
class TaskCalendarExportService {

	/**
	 * The per-user switch, a Nextcloud user value, off unless set to 'true'.
	 */
	public const SWITCH_KEY = 'export_tasks_to_caldav';

	/**
	 * The stored switch value, indexed by the switch as an int.
	 */
	private const STORED_SWITCH = ['false', 'true'];

	/**
	 * The task list's URI in the user's calendar home.
	 */
	public const LIST_URI = 'planninq';

	/**
	 * The DAV app's CalDAV backend, by name so it is never a hard dependency.
	 */
	private const DAV_BACKEND = 'OCA\\DAV\\CalDAV\\CalDavBackend';

	/**
	 * The backend calls the export needs.
	 */
	private const DAV_METHODS = [
		'getCalendarByUri',
		'createCalendar',
		'deleteCalendar',
		'getCalendarObject',
		'createCalendarObject',
		'updateCalendarObject',
		'deleteCalendarObject',
	];

	/**
	 * Constructor.
	 *
	 * @param IConfig            $config    User values and the instance id.
	 * @param ContainerInterface $container Lazy DAV backend resolution.
	 * @param IURLGenerator      $urls      The task's address for the VTODO URL.
	 * @param IJobList           $jobs      Queues the backfill on switch-on.
	 * @param IL10N              $l10n      The managed note.
	 * @param TaskVtodoBuilder   $builder   Task to VTODO.
	 * @param TaskCalendarTaskStore $store  Task and project reads, and the UID write-back.
	 * @param LoggerInterface    $logger    Diagnostics.
	 */
	public function __construct(
		private IConfig $config,
		private ContainerInterface $container,
		private IURLGenerator $urls,
		private IJobList $jobs,
		private IL10N $l10n,
		private TaskVtodoBuilder $builder,
		private TaskCalendarTaskStore $store,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The switch and whether this server can export at all.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<string,bool>
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.1
	 */
	public function values(string $userId): array {
		return [
			self::SWITCH_KEY   => $this->isOn(userId: $userId),
			'caldavAvailable' => $this->davBackend() !== null,
		];
	}//end values()

	/**
	 * Apply the switch from a settings write: on queues the backfill, off removes the list.
	 *
	 * @param string              $userId The user.
	 * @param array<string,mixed> $data   The posted settings.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
	 */
	public function apply(string $userId, array $data): void {
		if (array_key_exists(self::SWITCH_KEY, $data) === false) {
			return;
		}

		$wanted = filter_var($data[self::SWITCH_KEY], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
		$was    = $this->isOn(userId: $userId);

		$this->config->setUserValue($userId, Application::APP_ID, self::SWITCH_KEY, self::STORED_SWITCH[(int)$wanted]);
		if ($wanted !== $was) {
			$this->switched(userId: $userId, enabled: $wanted);
		}
	}//end apply()

	/**
	 * Act on a switch that changed: on queues the backfill, off removes the list.
	 *
	 * @param string $userId  The user.
	 * @param bool   $enabled Whether the export is now on.
	 *
	 * @return void
	 */
	private function switched(string $userId, bool $enabled): void {
		if ($enabled === true) {
			$this->jobs->add(TaskCalendarBackfillJob::class, ['userId' => $userId]);
			return;
		}

		$this->removeList(userId: $userId);
	}//end switched()

	/**
	 * Whether a user switched the export on.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.1
	 */
	public function isOn(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, self::SWITCH_KEY, 'false') === 'true';
	}//end isOn()

	/**
	 * The people a task's VTODO belongs to: its assignee and everyone it is shared with.
	 *
	 * @param array<string,mixed> $task The task data.
	 *
	 * @return array<int,string> Distinct user ids.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.3
	 */
	public function recipients(array $task): array {
		$people = array_merge([($task['assignedTo'] ?? '')], (array)($task['sharedWith'] ?? []));
		$found  = [];
		foreach ($people as $person) {
			if (is_scalar($person) === true && (string)$person !== '') {
				$found[(string)$person] = true;
			}
		}

		return array_keys($found);
	}//end recipients()

	/**
	 * Write a task's VTODO into the list of every given user who switched the export on.
	 *
	 * The first export stores the UID on the task as a system write.
	 *
	 * @param string              $taskId The task uuid.
	 * @param array<string,mixed> $task   The task data.
	 * @param array<int,string>   $users  The people to write for.
	 *
	 * @return int How many lists were written.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.3
	 */
	public function exportTask(string $taskId, array $task, array $users): int {
		$users   = $this->switchedOn(users: $users);
		$backend = $this->davBackend();
		if ($users === [] || $backend === null || $taskId === '') {
			return 0;
		}

		return $this->writeTask(export: ['backend' => $backend, 'taskId' => $taskId, 'task' => $task], users: $users);
	}//end exportTask()

	/**
	 * The users who switched the export on, in order.
	 *
	 * @param array<int,string> $users The people to write for.
	 *
	 * @return array<int,string>
	 */
	private function switchedOn(array $users): array {
		return array_values(array_filter($users, fn (string $user): bool => $this->isOn(userId: $user)));
	}//end switchedOn()

	/**
	 * Write the task's VTODO for each user and store its UID on the task after the first write.
	 *
	 * @param array<string,mixed> $export The backend, the task uuid and the task data.
	 * @param array<int,string>   $users  The users who switched the export on.
	 *
	 * @return int How many lists were written.
	 */
	private function writeTask(array $export, array $users): int {
		$stored        = ($export['task']['calendarEventUid'] ?? null);
		$uid           = $this->uidOf(taskId: $export['taskId'], stored: $stored);
		$export['ics'] = $this->icsFor(export: $export, uid: $uid);

		$written = 0;
		foreach ($users as $user) {
			$written += $this->writeFor(export: $export, userId: $user);
		}

		if ($written > 0 && $uid !== $stored) {
			$this->store->storeUid(taskId: $export['taskId'], task: $export['task'], uid: $uid);
		}

		return $written;
	}//end writeTask()

	/**
	 * The task's VCALENDAR text.
	 *
	 * @param array<string,mixed> $export The task uuid and the task data.
	 * @param string              $uid    The VTODO UID.
	 *
	 * @return string
	 */
	private function icsFor(array $export, string $uid): string {
		$project = $this->store->text(value: ($export['task']['project'] ?? ''));

		return $this->builder->build(
			task: $export['task'],
			meta: [
				'uid'          => $uid,
				'projectTitle' => $this->store->projectTitle(projectId: $project),
				'url'          => $this->taskUrl(projectId: $project, taskId: $export['taskId']),
				'managedNote'  => $this->l10n->t('Managed by Planninq. Changes made here are replaced by the next change in Planninq.'),
				'stamp'        => gmdate('Ymd\THis\Z'),
			]
		);
	}//end icsFor()

	/**
	 * Remove a task's VTODO from the list of every given user who has one.
	 *
	 * @param string            $taskId The task uuid.
	 * @param array<int,string> $users  The people to remove it for.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.3
	 */
	public function removeTask(string $taskId, array $users): void {
		$backend = $this->davBackend();
		if ($backend === null || $taskId === '') {
			return;
		}

		foreach ($users as $user) {
			$this->removeFor(export: ['backend' => $backend, 'taskId' => $taskId], userId: $user);
		}
	}//end removeTask()

	/**
	 * Delete a user's "Planninq" list, when there is one.
	 *
	 * @param string $userId The user.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
	 */
	public function removeList(string $userId): void {
		$backend = $this->davBackend();
		if ($backend === null) {
			return;
		}

		$listId = $this->existingList(backend: $backend, userId: $userId);
		if ($listId !== null) {
			$backend->deleteCalendar($listId, true);
		}
	}//end removeList()

	/**
	 * Export every task assigned to or shared with a user once (the switch-on backfill).
	 *
	 * OpenRegister filters a property by equality, so a list property such as
	 * `sharedWith` cannot be searched for a member: the job reads the tasks as
	 * the system and picks the user's own, as the My tasks page does.
	 *
	 * @param string $userId The user.
	 *
	 * @return int How many tasks were written.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
	 */
	public function backfill(string $userId): int {
		if ($this->mayBackfill(userId: $userId) === false) {
			return 0;
		}

		$written = 0;
		foreach ($this->store->tasksOf(userId: $userId) as $taskId => $task) {
			if (in_array($userId, $this->recipients(task: $task), true) === false) {
				continue;
			}

			$written += $this->exportTask(taskId: $taskId, task: $task, users: [$userId]);
		}

		return $written;
	}//end backfill()

	/**
	 * Whether a backfill runs: for a named user who switched the export on, on a server that can export.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 */
	private function mayBackfill(string $userId): bool {
		return $userId !== '' && $this->isOn(userId: $userId) === true && $this->davBackend() !== null;
	}//end mayBackfill()

	/**
	 * The UID a task's VTODO carries: planninq-task-<uuid>@<instance id>.
	 *
	 * @param string $taskId The task uuid.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.3
	 */
	public function uidFor(string $taskId): string {
		return 'planninq-task-' . $taskId . '@' . $this->config->getSystemValueString('instanceid', 'planninq');
	}//end uidFor()

	/**
	 * Resolve the DAV app's CalDAV backend, or null when absent or drifted.
	 *
	 * Public and overridable so tests substitute a double: the class exists
	 * only inside a Nextcloud server.
	 *
	 * @return object|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
	 */
	public function davBackend(): ?object {
		if (class_exists(self::DAV_BACKEND) === false) {
			return null;
		}

		try {
			$backend = $this->container->get(self::DAV_BACKEND);
		} catch (\Throwable $e) {
			$this->logger->debug('Planninq: the CalDAV backend could not be resolved', ['exception' => $e->getMessage()]);
			return null;
		}

		return $this->withDavMethods(backend: $backend);
	}//end davBackend()

	/**
	 * The backend when it has every method the export uses, else null with a warning naming the first one missing.
	 *
	 * @param mixed $backend What the container gave.
	 *
	 * @return object|null
	 */
	private function withDavMethods(mixed $backend): ?object {
		foreach (self::DAV_METHODS as $method) {
			if (is_object($backend) === false || method_exists($backend, $method) === false) {
				$this->logger->warning('Planninq: the CalDAV backend lacks {method}; task export is off', ['method' => $method]);
				return null;
			}
		}

		return $backend;
	}//end withDavMethods()

	/**
	 * The UID to use: the stored one, or a new one for a first export.
	 *
	 * @param string $taskId The task uuid.
	 * @param mixed  $stored The task's calendarEventUid.
	 *
	 * @return string
	 */
	private function uidOf(string $taskId, mixed $stored): string {
		if (is_string($stored) === true && $stored !== '') {
			return $stored;
		}

		return $this->uidFor(taskId: $taskId);
	}//end uidOf()

	/**
	 * Create or replace the VTODO in one user's list.
	 *
	 * @param array<string,mixed> $export The backend, the task uuid and the VCALENDAR text.
	 * @param string              $userId The user.
	 *
	 * @return int 1 when written, 0 when the list could not be made.
	 */
	private function writeFor(array $export, string $userId): int {
		$backend = $export['backend'];
		$listId  = ($this->existingList(backend: $backend, userId: $userId) ?? $this->createList(backend: $backend, userId: $userId));
		if ($listId === null) {
			return 0;
		}

		$uri = $this->objectUri(taskId: $export['taskId']);
		if ($backend->getCalendarObject($listId, $uri) === null) {
			$backend->createCalendarObject($listId, $uri, $export['ics']);
			return 1;
		}

		$backend->updateCalendarObject($listId, $uri, $export['ics']);

		return 1;
	}//end writeFor()

	/**
	 * Remove the task's VTODO from one user's list when it is there.
	 *
	 * @param array<string,mixed> $export The backend and the task uuid.
	 * @param string              $userId The user.
	 *
	 * @return void
	 */
	private function removeFor(array $export, string $userId): void {
		$backend = $export['backend'];
		$listId  = $this->existingList(backend: $backend, userId: $userId);
		if ($listId !== null && $backend->getCalendarObject($listId, $this->objectUri(taskId: $export['taskId'])) !== null) {
			$backend->deleteCalendarObject($listId, $this->objectUri(taskId: $export['taskId']));
		}
	}//end removeFor()

	/**
	 * The id of a user's "Planninq" list, null when there is none.
	 *
	 * @param object $backend The CalDAV backend.
	 * @param string $userId  The user.
	 *
	 * @return int|string|null
	 */
	private function existingList(object $backend, string $userId): int|string|null {
		$list = $backend->getCalendarByUri('principals/users/' . $userId, self::LIST_URI);
		if (is_array($list) === true && isset($list['id']) === true) {
			return $list['id'];
		}

		return null;
	}//end existingList()

	/**
	 * Create a user's "Planninq" list as a VTODO-only list.
	 *
	 * @param object $backend The CalDAV backend.
	 * @param string $userId  The user.
	 *
	 * @return int|string|null The new list's id.
	 */
	private function createList(object $backend, string $userId): int|string|null {
		$properties = ['components' => 'VTODO', '{DAV:}displayname' => 'Planninq'];

		return $backend->createCalendar('principals/users/' . $userId, self::LIST_URI, $properties);
	}//end createList()

	/**
	 * The object's name in the list.
	 *
	 * @param string $taskId The task uuid.
	 *
	 * @return string
	 */
	private function objectUri(string $taskId): string {
		return 'planninq-task-' . $taskId . '.ics';
	}//end objectUri()

	/**
	 * The task's page, absolute.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $taskId    The task uuid.
	 *
	 * @return string
	 */
	private function taskUrl(string $projectId, string $taskId): string {
		if ($projectId === '') {
			return '';
		}

		return $this->urls->getAbsoluteURL('/index.php/apps/planninq/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId));
	}//end taskUrl()

}//end class
