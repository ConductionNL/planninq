<?php

/**
 * Planninq WorkItemKeyListener
 *
 * Pre-save listener for project keys and task keys. A new task in a project
 * with a key gets the project's next number (VERG-42) unless it arrives with a
 * key. A project write is held to the key rules: the format, one project per
 * key, and no change once a task carries the key. The counter
 * `nextTaskNumber` is written only by the system. Setting a project's first
 * key queues the numbering of its existing tasks.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\BackgroundJob\NumberProjectTasks;
use OCA\Planninq\Service\WorkItemKeyService;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

/**
 * Assigns task keys and guards project keys.
 *
 * @template-implements IEventListener<Event>
 */
class WorkItemKeyListener implements IEventListener {

	/**
	 * Error code: the key has the wrong format.
	 *
	 * @var string
	 */
	public const ERROR_FORMAT = 'planninq-project-key-format';

	/**
	 * Error code: another project uses the key.
	 *
	 * @var string
	 */
	public const ERROR_USED = 'planninq-project-key-used';

	/**
	 * Error code: tasks carry the key, so it stays.
	 *
	 * @var string
	 */
	public const ERROR_FIXED = 'planninq-project-key-fixed';

	/**
	 * Error code: the task counter stayed locked.
	 *
	 * @var string
	 */
	public const ERROR_BUSY = 'planninq-task-key-busy';

	/**
	 * OpenRegister's system-operation scope.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param WorkItemKeyService $keys          The key rules and the counter.
	 * @param TaskScopeResolver  $scopeResolver Tells a planninq schema from any other object.
	 * @param IJobList           $jobList       Queues the numbering of existing tasks.
	 * @param LoggerInterface    $logger        The logger.
	 * @param IUserSession       $userSession   Tells a person's write from occ, cron and repair steps.
	 */
	public function __construct(
		private WorkItemKeyService $keys,
		private TaskScopeResolver $scopeResolver,
		private IJobList $jobList,
		private LoggerInterface $logger,
		private IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * Route a pre-save event to the task or the project rules.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->route(write: ['event' => $event, 'object' => $event->getObject(), 'old' => null]);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->route(write: ['event' => $event, 'object' => $event->getNewObject(), 'old' => (array)$event->getOldObject()?->getObject()]);
		}
	}//end handle()

	/**
	 * Send a task write to the task key rules and a person's project write to the project key rules.
	 *
	 * @param array<string,mixed> $write The event, the written object and, on an update, the stored data.
	 *
	 * @return void
	 */
	private function route(array $write): void {
		$object = $write['object'];
		$slug   = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		$write += ['uuid' => (string)($object->getUuid() ?? ''), 'data' => (array)$object->getObject()];

		if ($slug === 'task') {
			$this->routeTask(write: $write);
			return;
		}

		if ($slug === 'project' && $this->isSystemOperation() === false) {
			$this->guardProject(write: $write);
		}
	}//end route()

	/**
	 * Number a new task; keep the stored key on an update.
	 *
	 * @param array<string,mixed> $write The task write.
	 *
	 * @return void
	 */
	private function routeTask(array $write): void {
		if ($write['event'] instanceof ObjectCreatingEvent === true) {
			$this->assignTaskKey(event: $write['event'], data: $write['data']);
			return;
		}

		$this->keepTaskKey(write: $write);
	}//end routeTask()

	/**
	 * Give a keyless new task the next key of its project.
	 *
	 * @param ObjectCreatingEvent $event The event.
	 * @param array<string,mixed> $data  The task data.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	private function assignTaskKey(ObjectCreatingEvent $event, array $data): void {
		if ($this->keepsSentKey(data: $data) === true) {
			// An import by occ, cron or the system brings its own keys.
			return;
		}

		$projectId = $this->projectIdOf(data: $data);
		try {
			$key = $this->reserveKey(projectId: $projectId);
		} catch (LockedException $e) {
			$this->refuseBusy(event: $event, projectId: $projectId);
			return;
		}

		if ($key !== null || trim((string)($data['key'] ?? '')) !== '') {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['key' => $key]));
		}
	}//end assignTaskKey()

	/**
	 * Whether a new task keeps the key it was sent: a trusted write that sends one.
	 *
	 * @param array<string,mixed> $data The task data.
	 *
	 * @return bool
	 */
	private function keepsSentKey(array $data): bool {
		return (trim((string)($data['key'] ?? '')) !== '' && $this->isTrustedWrite() === true);
	}//end keepsSentKey()

	/**
	 * The next task key of a project, null without a project or when it has no key.
	 *
	 * @param string $projectId The project UUID, empty for none.
	 *
	 * @return string|null
	 *
	 * @throws LockedException When the project's counter stays locked.
	 */
	private function reserveKey(string $projectId): ?string {
		if ($projectId === '') {
			return null;
		}

		return $this->keys->nextTaskKey(projectId: $projectId);
	}//end reserveKey()

	/**
	 * Refuse a new task whose project counter stayed locked.
	 *
	 * @param ObjectCreatingEvent $event     The event.
	 * @param string              $projectId The project UUID.
	 *
	 * @return void
	 */
	private function refuseBusy(ObjectCreatingEvent $event, string $projectId): void {
		$event->setErrors(['code' => self::ERROR_BUSY, 'message' => 'Could not number the task. Try again.']);
		$event->stopPropagation();
		$this->logger->warning('Planninq: task counter stayed locked', ['project' => $projectId]);
	}//end refuseBusy()

	/**
	 * A task's project uuid, or '' when it names none.
	 *
	 * @param array<string,mixed> $data The task data.
	 *
	 * @return string
	 */
	private function projectIdOf(array $data): string {
		$projectId = ($data['project'] ?? '');
		if (is_array($projectId) === true) {
			$projectId = ($projectId['id'] ?? '');
		}

		if (is_string($projectId) === false) {
			return '';
		}

		return $projectId;
	}//end projectIdOf()

	/**
	 * Put back a task's stored key when a person's update sends another one.
	 *
	 * A key names one task for good: forge events and links find the task by
	 * it, so a member may not give a task another project's key.
	 *
	 * @param array<string,mixed> $write The task update.
	 *
	 * @return void
	 */
	private function keepTaskKey(array $write): void {
		$stored = ($write['old']['key'] ?? null);
		if (($write['data']['key'] ?? null) === $stored || $this->isTrustedWrite() === true) {
			return;
		}

		$write['event']->setModifiedData(array_merge($write['event']->getModifiedData(), ['key' => $stored]));
	}//end keepTaskKey()

	/**
	 * Whether the write comes from occ, cron, a repair step or an OpenRegister system operation.
	 *
	 * @return bool
	 */
	private function isTrustedWrite(): bool {
		return ($this->userSession->getUser() === null || $this->isSystemOperation() === true);
	}//end isTrustedWrite()

	/**
	 * Hold a project write to the key rules and keep the counter to the system.
	 *
	 * @param array<string,mixed> $write The project write.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	private function guardProject(array $write): void {
		$write['stored'] = ($write['old'] ?? []);
		$this->keepCounter(write: $write);

		$write['key']    = $this->keys->normalise(key: ($write['data']['key'] ?? null));
		$write['oldKey'] = $this->keys->normalise(key: ($write['stored']['key'] ?? null));

		// A write that leaves the key out keeps it. OpenRegister's PUT fills a
		// property it was not sent with null, so an empty key on a project
		// whose tasks carry its key is read the same way: kept, not cleared.
		if ($this->keyLeftOut(write: $write) === true) {
			$this->keepKey(event: $write['event'], stored: $write['stored']);
			return;
		}

		if ($write['key'] !== $write['oldKey']) {
			$this->changeKey(write: $write);
		}
	}//end guardProject()

	/**
	 * Refuse a key change the rules do not allow; otherwise store the key and number the tasks of a project that gets its first key.
	 *
	 * @param array<string,mixed> $write The project write with its normalised new and stored key.
	 *
	 * @return void
	 */
	private function changeKey(array $write): void {
		$refusal = $this->keyRefusal(write: $write);
		if ($refusal !== null) {
			$write['event']->setErrors($refusal);
			$write['event']->stopPropagation();
			return;
		}

		$this->storeKey(write: $write);

		if ($this->isFirstKey(write: $write) === true) {
			$this->jobList->add(NumberProjectTasks::class, ['project' => $write['uuid']]);
		}
	}//end changeKey()

	/**
	 * Whether an update gives a stored project its first key.
	 *
	 * @param array<string,mixed> $write The project write with its normalised new and stored key.
	 *
	 * @return bool
	 */
	private function isFirstKey(array $write): bool {
		return ($write['old'] !== null && $write['oldKey'] === '' && $write['key'] !== '' && $write['uuid'] !== '');
	}//end isFirstKey()

	/**
	 * Whether a write left the key out: not sent, or nulled by a PUT on a numbered project.
	 *
	 * @param array<string,mixed> $write The project write with its normalised new key.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	private function keyLeftOut(array $write): bool {
		if (array_key_exists('key', $write['data']) === false) {
			return true;
		}

		return $write['key'] === '' && $this->keys->hasNumberedTasks(project: $write['stored']) === true;
	}//end keyLeftOut()

	/**
	 * Put back the stored task counter when a write sends another one.
	 *
	 * @param array<string,mixed> $write The project write with its stored data.
	 *
	 * @return void
	 */
	private function keepCounter(array $write): void {
		$counter = ($write['stored'][WorkItemKeyService::COUNTER] ?? null);
		if (($write['data'][WorkItemKeyService::COUNTER] ?? null) !== $counter) {
			$write['event']->setModifiedData(array_merge($write['event']->getModifiedData(), [WorkItemKeyService::COUNTER => $counter]));
		}
	}//end keepCounter()

	/**
	 * Keep the stored key on a write that left it out.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The event.
	 * @param array<string,mixed>                     $stored The stored project, empty on a create.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	private function keepKey(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $stored): void {
		if ($this->keys->normalise(key: ($stored['key'] ?? null)) !== '') {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['key' => $stored['key']]));
		}
	}//end keepKey()

	/**
	 * Write the normalised key, or null for an empty one, when it differs from what was sent.
	 *
	 * @param array<string,mixed> $write The project write with its normalised new key.
	 *
	 * @return void
	 */
	private function storeKey(array $write): void {
		$value = null;
		if ($write['key'] !== '') {
			$value = $write['key'];
		}

		if ($value !== $write['data']['key']) {
			$write['event']->setModifiedData(array_merge($write['event']->getModifiedData(), ['key' => $value]));
		}
	}//end storeKey()

	/**
	 * Why a key change is refused, or null when it is allowed.
	 *
	 * @param array<string,mixed> $write The project write with its normalised new and stored key.
	 *
	 * @return array{code:string,message:string}|null
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	private function keyRefusal(array $write): ?array {
		if ($write['oldKey'] !== '' && $this->keys->hasNumberedTasks(project: $write['stored']) === true) {
			return ['code' => self::ERROR_FIXED, 'message' => 'The key cannot change once tasks carry it.'];
		}

		if ($write['key'] === '') {
			return null;
		}

		return $this->newKeyRefusal(key: $write['key'], projectId: $write['uuid']);
	}//end keyRefusal()

	/**
	 * Why a new key is refused: its form, or another project holding it.
	 *
	 * @param string $key       The new normalised key, not empty.
	 * @param string $projectId The project UUID.
	 *
	 * @return array{code:string,message:string}|null
	 */
	private function newKeyRefusal(string $key, string $projectId): ?array {
		if ($this->keys->isValidFormat(key: $key) === false) {
			return ['code' => self::ERROR_FORMAT, 'message' => 'A key has 2 to 10 letters and digits and starts with a letter.'];
		}

		if ($this->keys->isTaken(key: $key, projectId: $projectId) === true) {
			return ['code' => self::ERROR_USED, 'message' => 'This key is already used by another project.'];
		}

		return null;
	}//end newKeyRefusal()

	/**
	 * Whether OpenRegister runs this write as a trusted system operation.
	 *
	 * @return bool
	 */
	private function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()
}//end class
