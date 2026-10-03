<?php

/**
 * Planninq CreationPolicyService
 *
 * Who may create a project and who may request one (projects-lifecycle-policy).
 * `allow_project_creation` is `all`, `admins` or `groups`; under `groups`,
 * `project_creation_groups` lists the groups whose members may create. With
 * `project_requests` on, everyone else may request a project, and the people
 * who may create review it.
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
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * The project creation policy.
 */
class CreationPolicyService {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig    $appConfig    The stored policy.
	 * @param IGroupManager $groupManager Group membership and existence.
	 * @param IUserSession  $userSession  The current user.
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * Whether the current user may create a project under the stored policy; admins always may.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
	 */
	public function canCreate(): bool {
		$policy = $this->appConfig->getValueString(Application::APP_ID, 'allow_project_creation', 'all');
		if ($policy === 'admins') {
			return $this->isAdmin();
		}

		if ($policy === 'groups') {
			return $this->isAdmin() === true || $this->isInCreationGroup() === true;
		}

		// Default ('all'): any authenticated user may create.
		return $this->userSession->getUser() !== null;
	}//end canCreate()

	/**
	 * Whether the current user may request a project: requests are on and the user may not create one.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	public function canRequest(): bool {
		return $this->userSession->getUser() !== null
			&& $this->appConfig->getValueString(Application::APP_ID, SettingsService::REQUESTS_KEY, 'off') === 'on'
			&& $this->canCreate() === false;
	}//end canRequest()

	/**
	 * Whether the current user is a Nextcloud admin.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
	 */
	public function isAdmin(): bool {
		$user = $this->userSession->getUser();

		return ($user !== null && $this->groupManager->isAdmin($user->getUID()));
	}//end isAdmin()

	/**
	 * Whether the current user is in one of the groups that may create projects.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
	 */
	private function isInCreationGroup(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		foreach ($this->creationGroups() as $group) {
			if ($this->groupManager->isInGroup($user->getUID(), $group) === true) {
				return true;
			}
		}

		return false;
	}//end isInCreationGroup()

	/**
	 * The groups besides admins who review project requests: the creation groups under the `groups` policy.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function reviewerGroups(): array {
		if ($this->appConfig->getValueString(Application::APP_ID, 'allow_project_creation', 'all') !== 'groups') {
			return [];
		}

		return $this->creationGroups();
	}//end reviewerGroups()

	/**
	 * The stored group ids of the `groups` creation policy.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
	 */
	public function creationGroups(): array {
		$groups = json_decode($this->appConfig->getValueString(Application::APP_ID, SettingsService::CREATION_GROUPS_KEY, '[]'), true);
		if (is_array($groups) === false) {
			return [];
		}

		return array_values(array_filter($groups, static fn ($group): bool => is_string($group) === true && $group !== ''));
	}//end creationGroups()

	/**
	 * A creation policy setting as it is stored, or null to refuse it; any other setting unchanged.
	 *
	 * @param string $key   The setting key.
	 * @param string $value The submitted value.
	 *
	 * @return string|null The value unchanged for other keys; the policy or the cleaned group list; null when refused.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.1
	 */
	public function normalise(string $key, string $value): ?string {
		if ($key === 'allow_project_creation') {
			if (in_array($value, SettingsService::CREATION_POLICIES, true) === false) {
				return null;
			}

			return $value;
		}

		if ($key === SettingsService::REQUESTS_KEY) {
			if (in_array($value, ['on', 'off'], true) === false) {
				return null;
			}

			return $value;
		}

		if ($key !== SettingsService::CREATION_GROUPS_KEY) {
			return $value;
		}

		$groups = json_decode($value, true);
		if (is_array($groups) === false) {
			return null;
		}

		$known = array_filter(
			$groups,
			fn ($group): bool => is_string($group) === true && $this->groupManager->groupExists($group) === true
		);

		return (string)json_encode(array_values(array_unique($known)));
	}//end normalise()

	/**
	 * For an admin: the listed creation groups that no longer exist.
	 *
	 * @return array<string,array<int,string>>
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
	 */
	public function missingGroups(): array {
		if ($this->isAdmin() === false) {
			return [];
		}

		return [
			'creationGroupsMissing' => array_values(
				array_filter($this->creationGroups(), fn (string $group): bool => $this->groupManager->groupExists($group) === false)
			),
		];
	}//end missingGroups()
}//end class
