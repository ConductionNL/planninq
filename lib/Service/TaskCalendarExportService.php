<?php

/**
 * Planninq TaskCalendarExportService
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
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.2
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
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.2
 */
class TaskCalendarExportService {

	/**
	 * The per-user switch, a Nextcloud user value, off unless set to 'true'.
	 */
	public const SWITCH_KEY = 'export_tasks_to_caldav';

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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.1
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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.4
	 */
	public function apply(string $userId, array $data): void {
		if (array_key_exists(self::SWITCH_KEY, $data) === false) {
			return;
		}

		$wanted = filter_var($data[self::SWITCH_KEY], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
		$was    = $this->isOn(userId: $userId);
		$stored = 'false';
		if ($wanted === true) {
			$stored = 'true';
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::SWITCH_KEY, $stored);
		if ($wanted === true && $was === false) {
			$this->jobs->add(TaskCalendarBackfillJob::class, ['userId' => $userId]);
			return;
		}

		if ($wanted === false && $was === true) {
			$this->removeList(userId: $userId);
		}
	}//end apply()

	/**
	 * Whether a user switched the export on.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.1
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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function exportTask(string $taskId, array $task, array $users): int {
		$users   = array_values(array_filter($users, fn (string $user): bool => $this->isOn(userId: $user)));
		$backend = $this->davBackend();
		if ($users === [] || $backend === null || $taskId === '') {
			return 0;
		}

		$stored  = $task['calendarEventUid'] ?? null;
		$uid     = $this->uidOf(taskId: $taskId, stored: $stored);
		$project = $this->store->text(value: ($task['project'] ?? ''));
		$ics     = $this->builder->build(
			task: $task,
			uid: $uid,
			projectTitle: $this->store->projectTitle(projectId: $project),
			url: $this->taskUrl(projectId: $project, taskId: $taskId),
			managedNote: $this->l10n->t('Managed by Planninq. Changes made here are replaced by the next change in Planninq.'),
			stamp: gmdate('Ymd\THis\Z')
		);

		$written = 0;
		foreach ($users as $user) {
			$written += $this->writeFor(backend: $backend, userId: $user, taskId: $taskId, ics: $ics);
		}

		if ($written > 0 && $uid !== $stored) {
			$this->store->storeUid(taskId: $taskId, task: $task, uid: $uid);
		}

		return $written;
	}//end exportTask()

	/**
	 * Remove a task's VTODO from the list of every given user who has one.
	 *
	 * @param string            $taskId The task uuid.
	 * @param array<int,string> $users  The people to remove it for.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function removeTask(string $taskId, array $users): void {
		$backend = $this->davBackend();
		if ($backend === null || $taskId === '') {
			return;
		}

		foreach ($users as $user) {
			$listId = $this->listId(backend: $backend, userId: $user, create: false);
			if ($listId !== null && $backend->getCalendarObject($listId, $this->objectUri(taskId: $taskId)) !== null) {
				$backend->deleteCalendarObject($listId, $this->objectUri(taskId: $taskId));
			}
		}
	}//end removeTask()

	/**
	 * Delete a user's "Planninq" list, when there is one.
	 *
	 * @param string $userId The user.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.4
	 */
	public function removeList(string $userId): void {
		$backend = $this->davBackend();
		if ($backend === null) {
			return;
		}

		$listId = $this->listId(backend: $backend, userId: $userId, create: false);
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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.4
	 */
	public function backfill(string $userId): int {
		if ($userId === '' || $this->isOn(userId: $userId) === false || $this->davBackend() === null) {
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
	 * The UID a task's VTODO carries: planninq-task-<uuid>@<instance id>.
	 *
	 * @param string $taskId The task uuid.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
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
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.2
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

		foreach (self::DAV_METHODS as $method) {
			if (is_object($backend) === false || method_exists($backend, $method) === false) {
				$this->logger->warning('Planninq: the CalDAV backend lacks {method}; task export is off', ['method' => $method]);
				return null;
			}
		}

		return $backend;
	}//end davBackend()

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
	 * @param object $backend The CalDAV backend.
	 * @param string $userId  The user.
	 * @param string $taskId  The task uuid.
	 * @param string $ics     The VCALENDAR text.
	 *
	 * @return int 1 when written, 0 when the list could not be made.
	 */
	private function writeFor(object $backend, string $userId, string $taskId, string $ics): int {
		$listId = $this->listId(backend: $backend, userId: $userId, create: true);
		if ($listId === null) {
			return 0;
		}

		$uri = $this->objectUri(taskId: $taskId);
		if ($backend->getCalendarObject($listId, $uri) === null) {
			$backend->createCalendarObject($listId, $uri, $ics);
			return 1;
		}

		$backend->updateCalendarObject($listId, $uri, $ics);

		return 1;
	}//end writeFor()

	/**
	 * The id of a user's "Planninq" list, created on demand as a VTODO-only list.
	 *
	 * @param object $backend The CalDAV backend.
	 * @param string $userId  The user.
	 * @param bool   $create  Whether to create it when missing.
	 *
	 * @return int|string|null
	 */
	private function listId(object $backend, string $userId, bool $create): int|string|null {
		$principal = 'principals/users/' . $userId;
		$list      = $backend->getCalendarByUri($principal, self::LIST_URI);
		if (is_array($list) === true && isset($list['id']) === true) {
			return $list['id'];
		}

		if ($create === false) {
			return null;
		}

		$properties = ['components' => 'VTODO', '{DAV:}displayname' => 'Planninq'];

		return $backend->createCalendar($principal, self::LIST_URI, $properties);
	}//end listId()

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
