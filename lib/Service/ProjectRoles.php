<?php

/**
 * Planninq ProjectRoles
 *
 * The roles of the project schema, read from a project's user and group
 * lists the way its OpenRegister rules read them: who may change the
 * project's work (owner, owning group, managers, manager groups, members,
 * member groups). Server code that guards a write outside OpenRegister's
 * own rules asks this class instead of reading `members` alone.
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
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.4
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Who may write a project's work, by user and by group.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.4
 */
class ProjectRoles {

	/**
	 * The user lists whose people write the project's work.
	 *
	 * @var array<int,string>
	 */
	public const WRITER_USERS = ['managers', 'members'];

	/**
	 * The group lists whose members write the project's work.
	 *
	 * @var array<int,string>
	 */
	public const WRITER_GROUPS = ['ownerGroups', 'managerGroups', 'memberGroups'];

	/**
	 * Whether a person may change the project's tasks, columns, phases and links.
	 *
	 * @param array<string,mixed> $project  The project's data.
	 * @param string              $uid      The user id.
	 * @param array<int,string>   $groupIds The user's group ids.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.4
	 */
	public function mayWrite(array $project, string $uid, array $groupIds): bool {
		if ($uid === '') {
			return false;
		}

		if (($project['owner'] ?? null) === $uid) {
			return true;
		}

		foreach (self::WRITER_USERS as $field) {
			if (in_array($uid, $this->listOf(project: $project, field: $field), true) === true) {
				return true;
			}
		}

		foreach (self::WRITER_GROUPS as $field) {
			if (array_intersect($this->listOf(project: $project, field: $field), $groupIds) !== []) {
				return true;
			}
		}

		return false;
	}//end mayWrite()

	/**
	 * The user lists a person can be on.
	 *
	 * @var array<int,string>
	 */
	public const USER_LISTS = ['managers', 'members', 'viewers'];

	/**
	 * Whether a person is on the project at all: its owner or on a user list.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param string              $uid     The user id.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	public function holdsUser(array $project, string $uid): bool {
		if ($uid === '') {
			return false;
		}

		if (($project['owner'] ?? null) === $uid) {
			return true;
		}

		foreach (self::USER_LISTS as $field) {
			if (in_array($uid, $this->listOf(project: $project, field: $field), true) === true) {
				return true;
			}
		}

		return false;
	}//end holdsUser()

	/**
	 * The project without one person: off every user list, and when they owned
	 * it, ownership passes to the first remaining manager in alphabetical
	 * order, else to the first remaining member. Only the lists that held the
	 * person, and `owner` when it moves, are returned changed.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param string              $uid     The user id.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	public function withoutUser(array $project, string $uid): array {
		foreach (self::USER_LISTS as $field) {
			$list = $this->listOf(project: $project, field: $field);
			if (in_array($uid, $list, true) === true) {
				$project[$field] = array_values(array_filter($list, static fn (string $entry): bool => $entry !== $uid));
			}
		}

		if (($project['owner'] ?? null) !== $uid) {
			return $project;
		}

		foreach (['managers', 'members'] as $field) {
			$candidates = $this->listOf(project: $project, field: $field);
			if ($candidates !== []) {
				sort($candidates);
				$project['owner'] = $candidates[0];
				break;
			}
		}

		return $project;
	}//end withoutUser()

	/**
	 * Whether anyone who writes the project's work is left in person.
	 *
	 * @param array<string,mixed> $project The project's data.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.2
	 */
	public function hasWriters(array $project): bool {
		foreach (self::WRITER_USERS as $field) {
			if ($this->listOf(project: $project, field: $field) !== []) {
				return true;
			}
		}

		return false;
	}//end hasWriters()

	/**
	 * One list of a project as strings, or an empty list.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param string              $field   The property.
	 *
	 * @return array<int,string>
	 */
	private function listOf(array $project, string $field): array {
		$value = ($project[$field] ?? []);
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter($value, 'is_string'));
	}//end listOf()
}//end class
