<?php

/**
 * Planninq TimeEntryGuardListener
 *
 * Keeps the fields of a time entry that decide whose time it is and what it
 * costs on the server. OpenRegister's rules match fields of the entry itself,
 * so they can say "the author edits it" but not "the author was the caller" or
 * "only a project manager sets the rate"; this listener holds that on the
 * pre-save events.
 *
 * - `user`: on create the caller, whatever was sent (an admin may book for
 *   someone else); on update the stored value, so an author cannot hand an
 *   entry to someone else.
 * - `project`: always the project of the entry's task, so time cannot be
 *   counted in a project the task is not in.
 * - `hourlyRate` and `billable`: set only by the project's owner, a manager
 *   (in person or through the owning or manager groups) or an admin. For
 *   anyone else a create leaves them empty, so the entry inherits the
 *   project's terms, and an update keeps the stored values.
 *
 * Membership of the task's project is checked by ProjectMemberAccessListener.
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
 * @spec openspec/specs/time-tracking/spec.md
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

/**
 * Stamps and keeps a time entry's author, project, rate and billable flag.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/time-tracking/spec.md
 */
class TimeEntryGuardListener implements IEventListener {

	/**
	 * The time entry schema slug.
	 *
	 * @var string
	 */
	public const SCHEMA = 'plannedTimeEntry';

	/**
	 * The fields only a project manager sets, with their value when someone else creates an entry.
	 *
	 * @var array<string,mixed>
	 */
	private const MANAGED_FIELDS = ['hourlyRate' => 0, 'billable' => null];

	/**
	 * The project user lists that manage the project's terms.
	 *
	 * @var array<int,string>
	 */
	private const MANAGER_USERS = ['managers'];

	/**
	 * The project group lists that manage the project's terms.
	 *
	 * @var array<int,string>
	 */
	private const MANAGER_GROUPS = ['ownerGroups', 'managerGroups'];

	/**
	 * OpenRegister's system-operation flag, set during imports.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Resolves the task's project.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq time entry from any other object.
	 * @param IUserSession             $userSession   The caller.
	 * @param IGroupManager            $groupManager  Tells an admin and the caller's groups.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Restamp a time entry on a pre-create or pre-update.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/time-tracking/spec.md
	 */
	public function handle(Event $event): void {
		$user = $this->caller();
		if ($user === null) {
			return;
		}

		if ($event instanceof ObjectCreatingEvent === true && $this->isTimeEntry(object: $event->getObject()) === true) {
			$this->restamp(event: $event, user: $user, data: (array)$event->getObject()->getObject(), stored: null);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true && $this->isTimeEntry(object: $event->getNewObject()) === true) {
			$old    = $event->getOldObject();
			$stored = [];
			if ($old !== null) {
				$stored = (array)$old->getObject();
			}

			$this->restamp(event: $event, user: $user, data: (array)$event->getNewObject()->getObject(), stored: $stored);
		}
	}//end handle()

	/**
	 * Overwrite the server-owned fields that differ from what they must be.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param IUser                                   $user   The caller.
	 * @param array<string,mixed>                     $data   The data being written.
	 * @param array<string,mixed>|null                $stored The stored data on an update, null on a create.
	 *
	 * @return void
	 */
	private function restamp(ObjectCreatingEvent|ObjectUpdatingEvent $event, IUser $user, array $data, ?array $stored): void {
		$uid       = $user->getUID();
		$isAdmin   = $this->groupManager->isAdmin($uid);
		$projectId = $this->membership->projectIdFor(schemaSlug: self::SCHEMA, data: $data);
		$project   = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);

		$wanted = ['user' => $this->author(uid: $uid, isAdmin: $isAdmin, data: $data, stored: $stored)];
		if ($projectId !== '') {
			$wanted['project'] = $projectId;
		}

		if ($isAdmin === false && $this->managesProject(project: ($project ?? []), user: $user) === false) {
			$wanted = array_merge($wanted, $this->managedValues(stored: $stored));
		}

		$changes = [];
		foreach ($wanted as $field => $value) {
			if (array_key_exists($field, $data) === false || $data[$field] !== $value) {
				$changes[$field] = $value;
			}
		}

		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end restamp()

	/**
	 * The rate terms someone who does not manage the project gets: the stored ones, or none on a create.
	 *
	 * @param array<string,mixed>|null $stored The stored data on an update, null on a create.
	 *
	 * @return array<string,mixed>
	 */
	private function managedValues(?array $stored): array {
		if ($stored === null) {
			return self::MANAGED_FIELDS;
		}

		$values = [];
		foreach (self::MANAGED_FIELDS as $field => $default) {
			$values[$field] = ($stored[$field] ?? $default);
		}

		return $values;
	}//end managedValues()

	/**
	 * The entry's author: the stored one on an update, else the caller (or, for an admin, whoever was sent).
	 *
	 * @param string                   $uid     The caller.
	 * @param bool                     $isAdmin Whether the caller is an admin.
	 * @param array<string,mixed>      $data    The data being written.
	 * @param array<string,mixed>|null $stored  The stored data on an update, null on a create.
	 *
	 * @return string
	 */
	private function author(string $uid, bool $isAdmin, array $data, ?array $stored): string {
		$storedUser = ($stored['user'] ?? null);
		if (is_string($storedUser) === true && $storedUser !== '') {
			return $storedUser;
		}

		$sent = ($data['user'] ?? null);
		if ($isAdmin === true && is_string($sent) === true && $sent !== '') {
			return $sent;
		}

		return $uid;
	}//end author()

	/**
	 * Whether the caller owns or manages the project, in person or through a group.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param IUser               $user    The caller.
	 *
	 * @return bool
	 */
	private function managesProject(array $project, IUser $user): bool {
		$uid = $user->getUID();
		if (($project['owner'] ?? null) === $uid) {
			return true;
		}

		foreach (self::MANAGER_USERS as $field) {
			if (in_array($uid, $this->membership->normalise(members: ($project[$field] ?? null)), true) === true) {
				return true;
			}
		}

		$groupIds = $this->groupManager->getUserGroupIds($user);
		foreach (self::MANAGER_GROUPS as $field) {
			if (array_intersect($this->membership->normalise(members: ($project[$field] ?? null)), $groupIds) !== []) {
				return true;
			}
		}

		return false;
	}//end managesProject()

	/**
	 * Whether the object is a planninq time entry.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isTimeEntry(object $object): bool {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		return ($slug === self::SCHEMA);
	}//end isTimeEntry()

	/**
	 * The signed-in caller, or null for occ, cron, repair steps and system imports.
	 *
	 * @return IUser|null
	 */
	private function caller(): ?IUser {
		$user  = $this->userSession->getUser();
		$class = self::OR_SYSTEM_CONTEXT;
		if ($user === null || (class_exists($class) === true && $class::isActive() === true)) {
			return null;
		}

		return $user;
	}//end caller()
}//end class
