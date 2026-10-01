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
	public static function mayWrite(array $project, string $uid, array $groupIds): bool {
		if ($uid === '') {
			return false;
		}

		if (($project['owner'] ?? null) === $uid) {
			return true;
		}

		foreach (self::WRITER_USERS as $field) {
			if (in_array($uid, self::listOf(project: $project, field: $field), true) === true) {
				return true;
			}
		}

		foreach (self::WRITER_GROUPS as $field) {
			if (array_intersect(self::listOf(project: $project, field: $field), $groupIds) !== []) {
				return true;
			}
		}

		return false;
	}//end mayWrite()

	/**
	 * One list of a project as strings, or an empty list.
	 *
	 * @param array<string,mixed> $project The project's data.
	 * @param string              $field   The property.
	 *
	 * @return array<int,string>
	 */
	private static function listOf(array $project, string $field): array {
		$value = ($project[$field] ?? []);
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter($value, 'is_string'));
	}//end listOf()
}//end class
