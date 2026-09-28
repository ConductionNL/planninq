<?php

/**
 * Planninq ColumnOwnerGuardListener
 *
 * A project's board columns are managed by the project owner. OpenRegister
 * scopes a column by its `members` list and cannot match a rule against the
 * owner of another object, so the owner rule lives here, on the pre-save and
 * pre-delete events: a signed-in caller who is neither the project owner nor
 * an admin cannot create, change or delete a column. Members still move cards,
 * which writes the task, not the column.
 *
 * A write that changes nothing but the `members` list passes: planninq keeps
 * that list itself, and rewrites it as whoever changed the project's
 * membership, including a member who leaves.
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
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.1
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
 * Refuses column writes by anyone but the project owner or an admin.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.1
 */
class ColumnOwnerGuardListener implements IEventListener {

	/**
	 * Error code put on a refused write.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'planninq-not-the-project-owner';

	/**
	 * OpenRegister's system-operation flag, set during imports.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads the project's owner.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq column from any other object.
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
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.1
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true || $event instanceof ObjectDeletingEvent === true) {
			$this->guard(event: $event, object: $event->getObject(), oldData: null);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$old = $event->getOldObject();
			$this->guard(event: $event, object: $event->getNewObject(), oldData: ($old === null ? null : (array)$old->getObject()));
		}
	}//end handle()

	/**
	 * Stop the event when the caller may not manage this column.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param object                                                      $object  The column.
	 * @param array<string,mixed>|null                                    $oldData The stored column, on update.
	 *
	 * @return void
	 */
	private function guard(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== 'column') {
			return;
		}

		$data = (array)$object->getObject();
		if ($oldData !== null && $this->withoutMembers(data: $data) === $this->withoutMembers(data: $oldData)) {
			return;
		}

		$projectId = $this->membership->projectIdFor(schemaSlug: 'column', data: $data);
		if ($this->mayManage(projectId: $projectId) === true) {
			return;
		}

		$event->setErrors(
			[
				'message' => 'Only the project owner can change the board columns.',
				'code'    => self::ERROR_CODE,
				'project' => $projectId,
			]
		);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a column change by someone who is not the project owner', ['project' => $projectId]);
	}//end guard()

	/**
	 * Whether the caller may manage the columns of this project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return bool
	 */
	private function mayManage(string $projectId): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->isSystemOperation() === true) {
			// No session (occ, cron, a repair step) or an OpenRegister system import.
			return true;
		}

		$uid = $user->getUID();
		if ($this->groupManager->isAdmin($uid) === true) {
			return true;
		}

		return ($this->membership->ownerOfProject(projectId: $projectId) === $uid);
	}//end mayManage()

	/**
	 * A column's data without the planninq-kept members list, key order ignored.
	 *
	 * @param array<string,mixed> $data The column data.
	 *
	 * @return array<string,mixed>
	 */
	private function withoutMembers(array $data): array {
		unset($data['members'], $data['@self'], $data['id']);
		ksort($data);
		return $data;
	}//end withoutMembers()

	/**
	 * Whether OpenRegister is running a system operation (an import).
	 *
	 * @return bool
	 */
	private function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;
		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()
}//end class
