<?php

/**
 * Planninq TaskReporterGuardListener
 *
 * A task's `reporter` is the person who created it, and the reporter, the
 * project owner or an admin may delete it. OpenRegister's schema rules match
 * fields of the object itself, so "the owner of the task's project" cannot be
 * a rule there; this listener holds it on the pre-delete event, as
 * ColumnOwnerGuardListener does for columns.
 *
 * - On create, `reporter` is set to the signed-in caller, whatever was sent.
 * - On update, the stored `reporter` is kept. A PUT that leaves the field out
 *   arrives with it nulled, and a member may not claim someone else's task, so
 *   a missing, nulled or changed value is restored rather than refused.
 * - On delete, anyone but the reporter, the project owner or an admin is
 *   refused, and a task with logged time is refused for everyone: the hours
 *   belong to the people who logged them. The dialog offers to cancel instead.
 *
 * No session (occ, cron, a repair step) and OpenRegister system operations pass.
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
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Stamps and keeps a task's reporter, and holds task deletes to the reporter, the owner and admins.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-5.1
 */
class TaskReporterGuardListener implements IEventListener {

	/**
	 * Error code of a delete by someone who is not the reporter, the owner or an admin.
	 *
	 * @var string
	 */
	public const ERROR_NOT_ALLOWED = 'planninq-task-delete-not-allowed';

	/**
	 * Error code of a delete of a task with logged time.
	 *
	 * @var string
	 */
	public const ERROR_HAS_TIME = 'planninq-task-has-logged-time';

	/**
	 * OpenRegister's system-operation flag, set during imports.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads the project owner and the time entries.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq task from any other object.
	 * @param IUserSession             $userSession   The caller.
	 * @param IGroupManager            $groupManager  Tells an admin.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a pre-save or pre-delete event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-5.1
	 */
	public function handle(Event $event): void {
		$uid = $this->callerUid();
		if ($uid === null) {
			return;
		}

		if ($event instanceof ObjectCreatingEvent === true && $this->isTask(object: $event->getObject()) === true) {
			$event->setModifiedData(array_merge($event->getModifiedData(), ['reporter' => $uid]));
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true && $this->isTask(object: $event->getNewObject()) === true) {
			$this->keepReporter(event: $event);
			return;
		}

		if ($event instanceof ObjectDeletingEvent === true && $this->isTask(object: $event->getObject()) === true) {
			$this->guardDelete(event: $event, uid: $uid);
		}
	}//end handle()

	/**
	 * Restore the stored reporter when the new data differs from it.
	 *
	 * @param ObjectUpdatingEvent $event The event.
	 *
	 * @return void
	 */
	private function keepReporter(ObjectUpdatingEvent $event): void {
		$old = $event->getOldObject();
		if ($old === null) {
			return;
		}

		$stored = ((array)$old->getObject())['reporter'] ?? null;
		$sent   = ((array)$event->getNewObject()->getObject())['reporter'] ?? null;
		if ($sent === $stored) {
			return;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), ['reporter' => $stored]));
	}//end keepReporter()

	/**
	 * Stop a delete by someone who may not, or of a task with logged time.
	 *
	 * @param ObjectDeletingEvent $event The event.
	 * @param string              $uid   The caller.
	 *
	 * @return void
	 */
	private function guardDelete(ObjectDeletingEvent $event, string $uid): void {
		$task      = $event->getObject();
		$data      = (array)$task->getObject();
		$projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $data);

		if ($this->mayDelete(uid: $uid, reporter: $data['reporter'] ?? null, projectId: $projectId) === false) {
			$this->refuse(event: $event, code: self::ERROR_NOT_ALLOWED, message: 'Only the reporter, the project owner or an admin can delete this task.');
			return;
		}

		$taskId = (string)($task->getUuid() ?? '');
		if ($taskId !== '' && $this->membership->rows(schema: 'plannedTimeEntry', filters: ['task' => $taskId]) !== []) {
			$this->refuse(event: $event, code: self::ERROR_HAS_TIME, message: 'This task has logged time. Cancel it instead.');
		}
	}//end guardDelete()

	/**
	 * Whether the caller is the reporter, the project owner or an admin.
	 *
	 * @param string $uid       The caller.
	 * @param mixed  $reporter  The task's reporter.
	 * @param string $projectId The task's project.
	 *
	 * @return bool
	 */
	private function mayDelete(string $uid, mixed $reporter, string $projectId): bool {
		if ($this->groupManager->isAdmin($uid) === true || $reporter === $uid) {
			return true;
		}

		return ($projectId !== '' && $this->membership->ownerOfProject(projectId: $projectId) === $uid);
	}//end mayDelete()

	/**
	 * Stop the event with an error the API returns.
	 *
	 * @param ObjectDeletingEvent $event   The event.
	 * @param string              $code    The error code.
	 * @param string              $message The message.
	 *
	 * @return void
	 */
	private function refuse(ObjectDeletingEvent $event, string $code, string $message): void {
		$event->setErrors(['message' => $message, 'code' => $code]);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a task delete', ['code' => $code]);
	}//end refuse()

	/**
	 * Whether the object is a planninq task.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isTask(object $object): bool {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		return ($slug === 'task');
	}//end isTask()

	/**
	 * The signed-in caller, or null for occ, cron, repair steps and system imports.
	 *
	 * @return string|null
	 */
	private function callerUid(): ?string {
		$user  = $this->userSession->getUser();
		$class = self::OR_SYSTEM_CONTEXT;
		if ($user === null || (class_exists($class) === true && $class::isActive() === true)) {
			return null;
		}

		return $user->getUID();
	}//end callerUid()
}//end class
