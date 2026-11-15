<?php

/**
 * Planninq ProjectExportController
 *
 * Queues the export of a whole project for its owner or a manager. The
 * export itself runs in ExportProjectJob as the requester; this endpoint
 * only checks the caller's role on the project and answers 202.
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
 * @spec openspec/changes/project-data-export/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\BackgroundJob\ExportProjectJob;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\ProjectRoles;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Starts a project export.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-2.1
 */
class ProjectExportController extends Controller {

	/**
	 * Constructor
	 *
	 * @param IRequest                 $request      The request.
	 * @param ProjectMembershipService $membership   Reads the project.
	 * @param ProjectRoles             $roles        Tells owners and managers apart.
	 * @param IUserSession             $userSession  The caller.
	 * @param IGroupManager            $groupManager The caller's groups.
	 * @param IJobList                 $jobList      Queues the export.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-2.1
	 */
	public function __construct(
		IRequest $request,
		private readonly ProjectMembershipService $membership,
		private readonly ProjectRoles $roles,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IJobList $jobList,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Queue the export of a project for the caller.
	 *
	 * 401 without a session, 404 when the project does not resolve, 403
	 * unless the caller owns or manages the project, else 202.
	 *
	 * @param string $id The project uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-2.1
	 */
	#[NoAdminRequired]
	public function create(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $id);
		if ($project === null) {
			return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
		}

		$uid = $user->getUID();
		if ($this->roles->mayManage(project: $project, uid: $uid, groupIds: $this->groupManager->getUserGroupIds($user)) === false) {
			return new JSONResponse(['error' => 'Only an owner or manager of the project can export it.'], Http::STATUS_FORBIDDEN);
		}

		$this->jobList->add(ExportProjectJob::class, ['projectId' => $id, 'userId' => $uid]);

		return new JSONResponse(['status' => 'queued'], Http::STATUS_ACCEPTED);
	}//end create()
}//end class
