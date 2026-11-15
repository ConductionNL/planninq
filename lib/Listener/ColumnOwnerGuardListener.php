<?php

/**
 * Planninq ColumnOwnerGuardListener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A project's board columns are managed by the project owner. OpenRegister
 * scopes a column by its `members` list and cannot match a rule against the
 * owner of another object, so the owner rule lives here, on the pre-save and
 * pre-delete events: a signed-in caller who is neither the project owner nor
 * an admin cannot create, change or delete a column. Members still move cards,
 * which writes the task, not the column.
 *
 * A person's delete is refused while the column still holds cards (they would
 * keep a dangling `column`), and for the last done column of a project that
 * has tasks: the board needs somewhere to finish work. Both rules used to
 * live in the browser only.
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
	 * Error code of a delete of a column that still holds cards.
	 *
	 * @var string
	 */
	public const ERROR_HAS_CARDS = 'planninq-column-has-cards';

	/**
	 * Error code of a delete of a project's last done column.
	 *
	 * @var string
	 */
	public const ERROR_LAST_DONE = 'planninq-column-last-done';

	/**
	 * The message of each refused column delete, by error code.
	 *
	 * @var array<string,string>
	 */
	private const DELETE_REFUSALS = [
		self::ERROR_HAS_CARDS => 'Move the cards out of this column before removing it.',
		self::ERROR_LAST_DONE => 'A board needs at least one done column.',
	];

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
			$old     = $event->getOldObject();
			$oldData = null;
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$this->guard(event: $event, object: $event->getNewObject(), oldData: $oldData);
		}
	}//end handle()

	/**
	 * Refuse a column change by someone who may not manage the project's board, and a delete that would break it.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The pre-save event.
	 * @param object                                                      $object  The column being written.
	 * @param array<string,mixed>|null                                    $oldData The stored data on an update, null otherwise.
	 *
	 * @return void
	 */
	private function guard(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$column = $this->changedColumn(object: $object, oldData: $oldData);
		if ($column === []) {
			return;
		}

		if ($this->mayManage(projectId: $column['project']) === false) {
			$this->refuseChange(event: $event, projectId: $column['project']);
			return;
		}

		if ($event instanceof ObjectDeletingEvent === true) {
			$this->guardDelete(event: $event, column: $column);
		}
	}//end guard()

	/**
	 * The column being written, or nothing for another object or an update that changes only the access lists.
	 *
	 * @param object                   $object  The object being written.
	 * @param array<string,mixed>|null $oldData The stored data on an update, null otherwise.
	 *
	 * @return array{id?:string,data?:array<string,mixed>,project?:string}
	 */
	private function changedColumn(object $object, ?array $oldData): array {
		if ($this->isColumn(object: $object) === false) {
			return [];
		}

		$data = (array)$object->getObject();
		if ($oldData !== null && $this->withoutMembers(data: $data) === $this->withoutMembers(data: $oldData)) {
			return [];
		}

		return [
			'id'      => (string)($object->getUuid() ?? ''),
			'data'    => $data,
			'project' => $this->membership->projectIdFor(schemaSlug: 'column', data: $data),
		];
	}//end changedColumn()

	/**
	 * Whether the object is a planninq board column.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isColumn(object $object): bool {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === 'column';
	}//end isColumn()

	/**
	 * Refuse a column change; OpenRegister answers it with the error.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event     The pre-save event.
	 * @param string                                                      $projectId The column's project.
	 *
	 * @return void
	 */
	private function refuseChange(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, string $projectId): void {
		$event->setErrors(
			[
				'message' => 'Only the project owner can change the board columns.',
				'code'    => self::ERROR_CODE,
				'project' => $projectId,
			]
		);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a column change by someone who is not the project owner', ['project' => $projectId]);
	}//end refuseChange()

	/**
	 * Refuse removing a column that still holds cards, or the board's last done column while the project has tasks.
	 *
	 * A delete without a session or as a system operation is not checked.
	 *
	 * @param ObjectDeletingEvent                                  $event  The pre-delete event.
	 * @param array{id:string,data:array<string,mixed>,project:string} $column The column.
	 *
	 * @return void
	 */
	private function guardDelete(ObjectDeletingEvent $event, array $column): void {
		if ($this->checksDelete(column: $column) === false) {
			return;
		}

		if ($this->membership->rows(schema: 'task', filters: ['column' => $column['id']]) !== []) {
			$this->refuseDelete(event: $event, refusal: ['code' => self::ERROR_HAS_CARDS, 'project' => $column['project']]);
			return;
		}

		if ($this->isLastDoneWithTasks(column: $column) === true) {
			$this->refuseDelete(event: $event, refusal: ['code' => self::ERROR_LAST_DONE, 'project' => $column['project']]);
		}
	}//end guardDelete()

	/**
	 * Whether a column delete is checked: a person deletes, and the column has an id and a project.
	 *
	 * @param array{id:string,project:string} $column The column.
	 *
	 * @return bool
	 */
	private function checksDelete(array $column): bool {
		return $this->userSession->getUser() !== null
			&& $this->isSystemOperation() === false
			&& $column['id'] !== ''
			&& $column['project'] !== '';
	}//end checksDelete()

	/**
	 * Whether the column is the project's only done column while the project has tasks.
	 *
	 * @param array{id:string,data:array<string,mixed>,project:string} $column The column.
	 *
	 * @return bool
	 */
	private function isLastDoneWithTasks(array $column): bool {
		return ($column['data']['type'] ?? null) === 'done'
			&& $this->hasOtherDoneColumn(column: $column) === false
			&& $this->membership->rows(schema: 'task', filters: ['project' => $column['project']]) !== [];
	}//end isLastDoneWithTasks()

	/**
	 * Whether the project has another done column.
	 *
	 * @param array{id:string,project:string} $column The column.
	 *
	 * @return bool
	 */
	private function hasOtherDoneColumn(array $column): bool {
		foreach ($this->membership->rows(schema: 'column', filters: ['project' => $column['project']]) as $other) {
			if ($other['id'] !== $column['id'] && ($other['data']['type'] ?? null) === 'done') {
				return true;
			}
		}

		return false;
	}//end hasOtherDoneColumn()

	/**
	 * Refuse a column delete with a conflict; OpenRegister answers it with HTTP 409.
	 *
	 * @param ObjectDeletingEvent                            $event   The pre-delete event.
	 * @param array{code:string,project:string}          $refusal The error code and the project.
	 *
	 * @return void
	 */
	private function refuseDelete(ObjectDeletingEvent $event, array $refusal): void {
		$event->setErrors(
			['message' => self::DELETE_REFUSALS[$refusal['code']], 'code' => $refusal['code'], 'status' => 409, 'project' => $refusal['project']]
		);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a column delete', ['code' => $refusal['code'], 'project' => $refusal['project']]);
	}//end refuseDelete()

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
