<?php

/**
 * Planninq CurrentUserGroupsState
 *
 * Hands the signed-in user's Nextcloud group ids to the browser as the
 * initial state `planninq/groups`, so the interface can tell a project
 * shared with one of those groups from one that is not (the role helper in
 * src/utils/projectRole.js). The server still decides what is allowed.
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
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\AppFramework\Services\InitialStateProvider;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * The initial state `planninq/groups`: the caller's group ids.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
 */
class CurrentUserGroupsState extends InitialStateProvider {
	/**
	 * Constructor.
	 *
	 * @param IUserSession  $userSession  The session of the signed-in user.
	 * @param IGroupManager $groupManager Nextcloud's group manager.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * The initial-state key the browser reads with loadState('planninq', 'groups').
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
	 */
	public function getKey(): string {
		return 'groups';
	}//end getKey()

	/**
	 * The signed-in user's group ids, or an empty list without a session.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
	 */
	public function getData(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}

		return array_values(array_map('strval', $this->groupManager->getUserGroupIds($user)));
	}//end getData()
}//end class
