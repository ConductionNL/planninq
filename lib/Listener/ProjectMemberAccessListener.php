<?php

/**
 * Planninq ProjectMemberAccessListener
 *
 * Stamps a project's members, and the managers of its portfolio, on every
 * task, column, phase, planned time entry, log entry, risk and status report
 * as it is written, and refuses a create or a move into a project the
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
 * `plannedTimeEntry` keeps its own `user: $userId` write rules on top: a time
 * entry is booked on a task of a project the author is a member of.
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
 * @spec openspec/specs/tasks/spec.md
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
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Keeps the `members` list of project-scoped objects in step, and gates writes into a project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/tasks/spec.md
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
	private const GATED_SCHEMAS = [
		'task',
		'column',
		'projectPhase',
		'plannedTimeEntry',
		'projectLogEntry',
		'risk',
		'projectRelease',
		'boardFilter',
		'forgeLink',
	];

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
	 * @spec openspec/specs/tasks/spec.md
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
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	private function apply(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, ?array $oldData): void {
		$target = $this->target(object: $object);
		if ($target === null) {
			return;
		}

		if ($this->mayWrite(target: $target, oldData: $oldData) === false) {
			$this->refuse(event: $event, target: $target);
			return;
		}

		$this->stamp(event: $event, target: $target);
	}//end apply()

	/**
	 * The project-scoped object being written: schema, data, project uuid, project and its role lists.
	 *
	 * @param object $object The object being written.
	 *
	 * @return array{slug:string,data:array<string,mixed>,projectId:string,project:array<string,mixed>|null,lists:array<string,array<int,string>>|null}|null
	 *   null when the object is not project-scoped
	 */
	private function target(object $object): ?array {
		$schemaSlug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if (in_array($schemaSlug, ProjectMembershipService::SCOPED_SCHEMAS, true) === false) {
			return null;
		}

		$data      = (array)$object->getObject();
		$projectId = $this->membership->projectIdFor(schemaSlug: $schemaSlug, data: $data);
		$project   = $this->projectOf(projectId: $projectId);

		return [
			'slug'      => $schemaSlug,
			'data'      => $data,
			'projectId' => $projectId,
			'project'   => $project,
			'lists'     => $this->listsOf(project: $project),
		];
	}//end target()

	/**
	 * A project's data, or null when there is none or it does not resolve.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return array<string,mixed>|null
	 */
	private function projectOf(string $projectId): ?array {
		if ($projectId === '') {
			return null;
		}

		return $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
	}//end projectOf()

	/**
	 * A project's role lists, or null when there is no project.
	 *
	 * @param array<string,mixed>|null $project The project data.
	 *
	 * @return array<string,array<int,string>>|null
	 */
	private function listsOf(?array $project): ?array {
		if ($project === null) {
			return null;
		}

		return $this->membership->childLists(project: $project);
	}//end listsOf()

	/**
	 * Refuse a write into a project the caller is not a member of.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param array{slug:string,projectId:string}     $target The object being written.
	 *
	 * @return void
	 */
	private function refuse(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $target): void {
		$event->setErrors(
			[
				'message' => 'You are not a member of this project.',
				'code' => self::ERROR_CODE,
				'project' => $target['projectId'],
			]
		);
		$event->stopPropagation();
		$this->logger->info(
			'Planninq: refused a write into a project the caller is not a member of',
			['schema' => $target['slug'], 'project' => $target['projectId']]
		);
	}//end refuse()

	/**
	 * Put the project's role and reader lists that are out of step on the object.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param array<string,mixed>                     $target The object being written, as target() reads it.
	 *
	 * @return void
	 */
	private function stamp(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $target): void {
		$lists = ($target['lists'] ?? ['members' => []]);
		$lists[ProjectMembershipService::READERS_FIELD] = $this->membership->normalise(
			members: ($target['project'][ProjectMembershipService::READERS_FIELD] ?? [])
		);
		$changes = $this->changedLists(data: $target['data'], lists: $lists);
		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end stamp()

	/**
	 * The lists that are not yet in step on the object.
	 *
	 * @param array<string,mixed>             $data  The object data.
	 * @param array<string,array<int,string>> $lists Field to the project's list.
	 *
	 * @return array<string,array<int,string>> The fields to write.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	private function changedLists(array $data, array $lists): array {
		$changes = [];
		foreach ($lists as $field => $values) {
			// Leave a list that is already in step untouched: OpenRegister's
			// batched soft delete falls back to a full-row save for every
			// object a pre-update hook modifies. A missing readers or role list reads
			// as empty, so objects outside any portfolio or role are never rewritten.
			$inStep = $this->membership->normalise(members: ($data[$field] ?? null)) === $values;
			if ($field === 'members') {
				$inStep = (array_key_exists('members', $data) === true && $inStep === true);
			}

			if ($inStep === false) {
				$changes[$field] = $values;
			}
		}

		return $changes;
	}//end changedLists()

	/**
	 * Whether the acting user may put this object in its project.
	 *
	 * Asked on a create, and on an update that changes the project. An update
	 * inside the same project was already checked by OpenRegister against the
	 * stored members list.
	 *
	 * @param array{slug:string,projectId:string,lists:array<string,array<int,string>>|null} $target  The object being written.
	 * @param array<string,mixed>|null                                                     $oldData The stored data on an update, null on a create.
	 *
	 * @return bool
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	private function mayWrite(array $target, ?array $oldData): bool {
		if (in_array($target['slug'], self::GATED_SCHEMAS, true) === false
			|| $this->staysInProject(target: $target, oldData: $oldData) === true
		) {
			return true;
		}

		return $this->actorMayWrite(lists: $target['lists']);
	}//end mayWrite()

	/**
	 * Whether the acting user may write into a project with these role lists.
	 *
	 * @param array<string,array<int,string>>|null $lists The project's role lists, null when it does not resolve.
	 *
	 * @return bool
	 */
	private function actorMayWrite(?array $lists): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->isTrusted(user: $user) === true) {
			// No session (occ, cron, a repair step), an OpenRegister system import, or an admin.
			return true;
		}

		return $this->isListed(user: $user, lists: $lists);
	}//end actorMayWrite()

	/**
	 * Whether an update keeps the object in the project it was stored in.
	 *
	 * @param array{slug:string,projectId:string} $target  The object being written.
	 * @param array<string,mixed>|null            $oldData The stored data on an update, null on a create.
	 *
	 * @return bool
	 */
	private function staysInProject(array $target, ?array $oldData): bool {
		return $oldData !== null
			&& $this->membership->projectIdFor(schemaSlug: $target['slug'], data: $oldData) === $target['projectId'];
	}//end staysInProject()

	/**
	 * Whether a signed-in caller writes as a system operation or is an admin.
	 *
	 * @param IUser $user The acting user.
	 *
	 * @return bool
	 */
	private function isTrusted(IUser $user): bool {
		return $this->isSystemOperation() === true || $this->groupManager->isAdmin($user->getUID()) === true;
	}//end isTrusted()

	/**
	 * Whether the caller is on the project's members list, in person or through an owning, manager or member group.
	 *
	 * @param IUser                                $user  The acting user.
	 * @param array<string,array<int,string>>|null $lists The project's role lists, null when it does not resolve.
	 *
	 * @return bool
	 */
	private function isListed(IUser $user, ?array $lists): bool {
		if ($lists === null) {
			return false;
		}

		if (in_array($user->getUID(), $lists['members'], true) === true) {
			return true;
		}

		return array_intersect($this->groupManager->getUserGroupIds($user), $lists['memberGroups']) !== [];
	}//end isListed()

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
