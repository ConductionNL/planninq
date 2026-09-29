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
	 */
	public function __construct(
		private WorkItemKeyService $keys,
		private TaskScopeResolver $scopeResolver,
		private IJobList $jobList,
		private LoggerInterface $logger,
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
		if ($event instanceof ObjectCreatingEvent === false && $event instanceof ObjectUpdatingEvent === false) {
			return;
		}

		$oldData = null;
		if ($event instanceof ObjectUpdatingEvent === true) {
			$object  = $event->getNewObject();
			$old     = $event->getOldObject();
			$oldData = [];
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}
		} else {
			$object = $event->getObject();
		}

		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		$data = (array)$object->getObject();

		if ($slug === 'task' && $event instanceof ObjectCreatingEvent === true) {
			$this->assignTaskKey(event: $event, data: $data);
			return;
		}

		if ($slug === 'project' && $this->isSystemOperation() === false) {
			$this->guardProject(event: $event, projectId: (string)($object->getUuid() ?? ''), data: $data, oldData: $oldData);
		}
	}//end handle()

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
		$projectId = $data['project'] ?? '';
		if (is_array($projectId) === true) {
			$projectId = ($projectId['id'] ?? '');
		}

		if (trim((string)($data['key'] ?? '')) !== '' || is_string($projectId) === false || $projectId === '') {
			return;
		}

		try {
			$key = $this->keys->nextTaskKey(projectId: $projectId);
		} catch (LockedException $e) {
			$event->setErrors(['code' => self::ERROR_BUSY, 'message' => 'Could not number the task. Try again.']);
			$event->stopPropagation();
			$this->logger->warning('Planninq: task counter stayed locked', ['project' => $projectId]);
			return;
		}

		if ($key !== null) {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['key' => $key]));
		}
	}//end assignTaskKey()

	/**
	 * Hold a project write to the key rules and keep the counter to the system.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The event.
	 * @param string                                  $projectId The project UUID.
	 * @param array<string,mixed>                     $data      The new project data.
	 * @param array<string,mixed>|null                $oldData   The stored project on an update.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	private function guardProject(ObjectCreatingEvent|ObjectUpdatingEvent $event, string $projectId, array $data, ?array $oldData): void {
		$stored = ($oldData ?? []);
		$key    = $this->keys->normalise(key: ($data['key'] ?? null));
		$oldKey = $this->keys->normalise(key: ($stored['key'] ?? null));

		$counter = ($stored[WorkItemKeyService::COUNTER] ?? null);
		if (($data[WorkItemKeyService::COUNTER] ?? null) !== $counter) {
			$event->setModifiedData(array_merge($event->getModifiedData(), [WorkItemKeyService::COUNTER => $counter]));
		}

		// A write that leaves the key out (a PUT of other fields) keeps it.
		if (array_key_exists('key', $data) === false) {
			if ($oldKey !== '') {
				$event->setModifiedData(array_merge($event->getModifiedData(), ['key' => $stored['key']]));
			}

			return;
		}

		if ($key === $oldKey) {
			return;
		}

		$refusal = $this->keyRefusal(key: $key, oldKey: $oldKey, projectId: $projectId, stored: $stored);
		if ($refusal !== null) {
			$event->setErrors($refusal);
			$event->stopPropagation();
			return;
		}

		if ($key !== ($data['key'] ?? null)) {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['key' => ($key === '' ? null : $key)]));
		}

		if ($oldData !== null && $oldKey === '' && $key !== '' && $projectId !== '') {
			$this->jobList->add(NumberProjectTasks::class, ['project' => $projectId]);
		}
	}//end guardProject()

	/**
	 * Why a key change is refused, or null when it is allowed.
	 *
	 * @param string              $key       The new normalised key.
	 * @param string              $oldKey    The stored normalised key.
	 * @param string              $projectId The project UUID.
	 * @param array<string,mixed> $stored    The stored project.
	 *
	 * @return array{code:string,message:string}|null
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	private function keyRefusal(string $key, string $oldKey, string $projectId, array $stored): ?array {
		if ($oldKey !== '' && $this->keys->hasNumberedTasks(project: $stored) === true) {
			return ['code' => self::ERROR_FIXED, 'message' => 'The key cannot change once tasks carry it.'];
		}

		if ($key === '') {
			return null;
		}

		if ($this->keys->isValidFormat(key: $key) === false) {
			return ['code' => self::ERROR_FORMAT, 'message' => 'A key has 2 to 10 letters and digits and starts with a letter.'];
		}

		if ($this->keys->isTaken(key: $key, projectId: $projectId) === true) {
			return ['code' => self::ERROR_USED, 'message' => 'This key is already used by another project.'];
		}

		return null;
	}//end keyRefusal()

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
