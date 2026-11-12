<?php

/**
 * Planninq ProjectOwnerGuard
 *
 * The owner-or-admin guard (IDOR) of the project endpoints only the project's
 * owner may use: the case handover and the plan import.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\Service\ProjectMembershipService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Refuses anyone but the project's owner or an admin: 401 with nobody signed
 * in, 404 for a project without an owner, 403 for anyone else.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.2
 */
final class ProjectOwnerGuard {

	/**
	 * The error the 403 answer carries; the endpoint names what was refused.
	 *
	 * @var string
	 */
	public string $forbiddenMessage = 'Only the project owner can do this.';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership   Reads the project's owner.
	 * @param IGroupManager            $groupManager Tells an admin.
	 */
	public function __construct(
		private readonly ProjectMembershipService $membership,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Why the caller may not act on this project, or null when they may.
	 *
	 * @param IUser|null $user      The signed-in caller.
	 * @param string     $projectId The project UUID.
	 *
	 * @return JSONResponse|null
	 *
	 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.2
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.2
	 */
	public function refusal(?IUser $user, string $projectId): ?JSONResponse {
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$owner = $this->membership->ownerOfProject(projectId: $projectId);
		if ($owner === null) {
			return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
		}

		$uid = $user->getUID();
		if ($owner !== $uid && $this->groupManager->isAdmin($uid) === false) {
			return new JSONResponse(['error' => $this->forbiddenMessage], Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end refusal()
}//end class
