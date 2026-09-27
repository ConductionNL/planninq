<?php

/**
 * Planninq ProjectMemberAccessListener
 *
 * Stamps a project's members on every task, column, phase and planned time
 * entry as it is written, and refuses a create or a move into a project the
 * caller is not a member of.
 *
 * WHY (planninq#681)
 * ------------------
 * The four schemas scope read, update and delete with
 * `{"members": {"$contains": "$userId"}}`, a rule OpenRegister evaluates. The
 * list has to be on the object, and the client must not choose it, so this
 * listener overwrites it from the project on every create and update.
 *
 * WHY THE CREATE CHECK LIVES HERE AND NOT IN THE SCHEMA
 * -----------------------------------------------------
 * OpenRegister checks `create` before the object exists:
 * `ObjectService::checkSavePermissions()` passes no object, so a `match` on
 * create is evaluated against an empty one and refuses every member. The create
 * rule on `task`, `column` and `projectPhase` therefore admits any signed-in
 * user, and this listener, on ObjectCreatingEvent, refuses one who is not a
 * member of the target project. A refused write surfaces as HTTP 422.
 * `plannedTimeEntry` keeps its own `user: $userId` write rules and is only
 * stamped here.
 *
 * WHY THE `*ing` EVENTS
 * ---------------------
 * They run inside the write and may veto it or change its data
 * (`setModifiedData()` is merged by MagicMapper before the row is stored),
 * which is exactly what this needs. `ObjectUpdatingEvent` has no `getObject()`,
 * only `getNewObject()` and `getOldObject()`.
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
 * @spec openspec/specs/tasks.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Keeps the `members` list of project-scoped objects in step, and gates writes into a project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/tasks.md
 */
class ProjectMemberAccessListener implements IEventListener {

	/**
	 * Error code of a refused write, so a client can branch on it.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'planninq-not-a-project-member';

	/**
	 * Schemas whose writes into a project need membership of that project.
	 *
	 * @var array<int,string>
	 */
	private const GATED_SCHEMAS = ['task', 'column', 'projectPhase'];

	/**
	 * OpenRegister's ambient system-operation marker, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Resolves projects and members.
	 * @param TaskScopeResolver $scopeResolver Tells whether an object is a planninq schema.
	 * @param IUserSession $userSession The acting user.
	 * @param IGroupManager $groupManager Tells whether the acting user is an admin.
	 * @param LoggerInterface $logger The logger.
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
	 * Stamp and gate a pre-create or pre-update of a project-scoped object.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/tasks.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->apply(event: $event, object: $event->getObject(), oldData: null);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$oldData = [];
			$old = $event->getOldObject();
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$this->apply(event: $event, object: $event->getNewObject(), oldData: $oldData);
		}
	}//end handle()

	/**
	 * Stamp the members list, or refuse the write.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The pre-save event.
	 * @param object $object The object being written.
	 * @param array<string,mixed>|null $oldData The stored data on an update, null on a create.
	 *
	 * @return void
	 */
	private function apply(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, ?array $oldData): void {
		$schemaSlug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if (in_array($schemaSlug, ProjectMembershipService::SCOPED_SCHEMAS, true) === false) {
			return;
		}

		$data = (array)$object->getObject();
		$projectId = $this->membership->projectIdFor(schemaSlug: $schemaSlug, data: $data);
		$members = $this->membership->membersOfProject(projectId: $projectId);

		if ($this->mayWrite(schemaSlug: $schemaSlug, projectId: $projectId, members: $members, oldData: $oldData) === false) {
			$event->setErrors(
				[
					'message' => 'You are not a member of this project.',
					'code' => self::ERROR_CODE,
					'project' => $projectId,
				]
			);
			$event->stopPropagation();
			$this->logger->info(
				'Planninq: refused a write into a project the caller is not a member of',
				['schema' => $schemaSlug, 'project' => $projectId]
			);
			return;
		}

		$members = ($members ?? []);
		if (array_key_exists('members', $data) === true && $this->membership->normalise(members: $data['members']) === $members) {
			// Already in step. Leaving the data untouched matters beyond speed:
			// OpenRegister's batched soft delete falls back to a full-row save
			// for every object a pre-update hook modifies.
			return;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), ['members' => $members]));
	}//end apply()

	/**
	 * Whether the acting user may put this object in its project.
	 *
	 * Asked on a create, and on an update that changes the project. An update
	 * inside the same project was already checked by OpenRegister against the
	 * stored members list.
	 *
	 * @param string $schemaSlug The schema slug.
	 * @param string $projectId The target project uuid.
	 * @param array<int,string>|null $members The target project's members list, null when it does not resolve.
	 * @param array<string,mixed>|null $oldData The stored data on an update, null on a create.
	 *
	 * @return bool
	 */
	private function mayWrite(string $schemaSlug, string $projectId, ?array $members, ?array $oldData): bool {
		if (in_array($schemaSlug, self::GATED_SCHEMAS, true) === false) {
			return true;
		}

		if ($oldData !== null
			&& $this->membership->projectIdFor(schemaSlug: $schemaSlug, data: $oldData) === $projectId
		) {
			return true;
		}

		$user = $this->userSession->getUser();
		if ($user === null || $this->isSystemOperation() === true) {
			// occ, cron, a repair step or an OpenRegister system import.
			return true;
		}

		$uid = $user->getUID();
		if ($this->groupManager->isAdmin($uid) === true) {
			return true;
		}

		return ($members !== null && in_array($uid, $members, true) === true);
	}//end mayWrite()

	/**
	 * Whether OpenRegister runs this write as a trusted system operation.
	 *
	 * The class is named as a string so planninq keeps no compile-time
	 * dependency on OpenRegister.
	 *
	 * @return bool
	 */
	private function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()
}//end class
