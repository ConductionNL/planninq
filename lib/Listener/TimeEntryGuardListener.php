<?php

/**
 * Planninq TimeEntryGuardListener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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

		$write = $this->writeOf(event: $event);
		if ($write !== []) {
			$this->restamp(event: $write['event'], write: ['user' => $user] + $write);
		}
	}//end handle()

	/**
	 * A time entry's pre-save event with the data being written and the stored data, or nothing for anything else.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return array{event?:ObjectCreatingEvent|ObjectUpdatingEvent,data?:array<string,mixed>,stored?:array<string,mixed>|null}
	 */
	private function writeOf(Event $event): array {
		if ($event instanceof ObjectCreatingEvent === true && $this->isTimeEntry(object: $event->getObject()) === true) {
			return ['event' => $event, 'data' => (array)$event->getObject()->getObject(), 'stored' => null];
		}

		if ($event instanceof ObjectUpdatingEvent === true && $this->isTimeEntry(object: $event->getNewObject()) === true) {
			return ['event' => $event, 'data' => (array)$event->getNewObject()->getObject(), 'stored' => $this->storedOf(event: $event)];
		}

		return [];
	}//end writeOf()

	/**
	 * The stored data of an update; empty when the event carries none.
	 *
	 * @param ObjectUpdatingEvent $event The pre-update event.
	 *
	 * @return array<string,mixed>
	 */
	private function storedOf(ObjectUpdatingEvent $event): array {
		$old = $event->getOldObject();
		if ($old === null) {
			return [];
		}

		return (array)$old->getObject();
	}//end storedOf()

	/**
	 * Overwrite the server-owned fields that differ from what they must be.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The pre-save event.
	 * @param array<string,mixed>                     $write The caller (user), the data being written, and the stored data (null on a create).
	 *
	 * @return void
	 */
	private function restamp(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $write): void {
		$changes = $this->changedFields(data: $write['data'], wanted: $this->wanted(write: $write));
		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end restamp()

	/**
	 * The values the server owns: the author, the task's project, and for anyone not managing the project its rate terms.
	 *
	 * @param array{user:IUser,data:array<string,mixed>,stored:array<string,mixed>|null} $write The write.
	 *
	 * @return array<string,mixed>
	 */
	private function wanted(array $write): array {
		$write['admin'] = $this->groupManager->isAdmin($write['user']->getUID());
		$projectId      = $this->membership->projectIdFor(schemaSlug: self::SCHEMA, data: $write['data']);
		$project        = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);

		$wanted = ['user' => $this->author(write: $write)];
		if ($projectId !== '') {
			$wanted['project'] = $projectId;
		}

		if ($write['admin'] === false && $this->managesProject(project: ($project ?? []), user: $write['user']) === false) {
			$wanted = array_merge($wanted, $this->managedValues(stored: $write['stored']));
		}

		return $wanted;
	}//end wanted()

	/**
	 * The wanted fields that are missing from the data or hold another value.
	 *
	 * @param array<string,mixed> $data   The data being written.
	 * @param array<string,mixed> $wanted The values the server owns.
	 *
	 * @return array<string,mixed>
	 */
	private function changedFields(array $data, array $wanted): array {
		$changes = [];
		foreach ($wanted as $field => $value) {
			if (array_key_exists($field, $data) === false || $data[$field] !== $value) {
				$changes[$field] = $value;
			}
		}

		return $changes;
	}//end changedFields()

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
	 * @param array{user:IUser,admin:bool,data:array<string,mixed>,stored:array<string,mixed>|null} $write The write.
	 *
	 * @return string
	 */
	private function author(array $write): string {
		$storedUser = ($write['stored']['user'] ?? null);
		if ($this->isName(value: $storedUser) === true) {
			return $storedUser;
		}

		$sent = ($write['data']['user'] ?? null);
		if ($write['admin'] === true && $this->isName(value: $sent) === true) {
			return $sent;
		}

		return $write['user']->getUID();
	}//end author()

	/**
	 * Whether a value is a non-empty user name.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 *
	 * @psalm-assert-if-true non-empty-string $value
	 * @phpstan-assert-if-true non-empty-string $value
	 */
	private function isName(mixed $value): bool {
		return is_string($value) === true && $value !== '';
	}//end isName()

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
		if (($project['owner'] ?? null) === $uid || in_array($uid, $this->listed(project: $project, fields: self::MANAGER_USERS), true) === true) {
			return true;
		}

		return array_intersect($this->listed(project: $project, fields: self::MANAGER_GROUPS), $this->groupManager->getUserGroupIds($user)) !== [];
	}//end managesProject()

	/**
	 * The ids on some of the project's lists, each list normalised.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param array<int,string>   $fields  The list fields.
	 *
	 * @return array<int,string>
	 */
	private function listed(array $project, array $fields): array {
		$ids = [];
		foreach ($fields as $field) {
			$ids = array_merge($ids, $this->membership->normalise(members: ($project[$field] ?? null)));
		}

		return $ids;
	}//end listed()

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
