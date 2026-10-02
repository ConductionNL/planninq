<?php

/**
 * Planninq ProjectCopyController
 *
 * `POST /api/projects/{projectId}/copy`: copy a project, or start one from a
 * template, on the server. ProjectCopyService enforces the creation policy
 * and who may copy, remaps every reference and removes a failed copy.
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\ProjectCopyException;
use OCA\Planninq\Service\ProjectCopyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Copies a project.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request      The request.
	 * @param ProjectCopyService $copyService  Copies the project.
	 * @param IUserSession       $userSession  The caller.
	 * @param IGroupManager      $groupManager The caller's groups, and whether they are an admin.
	 */
	public function __construct(
		IRequest $request,
		private ProjectCopyService $copyService,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Copy a project: body `title`, optional `key`, `startDate` (Y-m-d) and
	 * `parts` (columns, tasks, phases, dependencies, people).
	 *
	 * Only the owner, a manager, an owning or manager group or an admin copies
	 * a project; anyone who may create a project starts from a template. The
	 * service refuses everyone else with 403 before anything is written.
	 *
	 * @param string $projectId The project (or template) to copy.
	 *
	 * @return JSONResponse 201 with the new project and counts; 400, 401, 403, 404, 409; 500 naming the failed step.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	#[NoAdminRequired]
	public function copy(string $projectId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		try {
			$result = $this->copyService->copy(
				sourceId: $projectId,
				uid: $uid,
				groupIds: $this->groupManager->getUserGroupIds($user),
				isAdmin: $this->groupManager->isAdmin($uid),
				options: [
					'title'     => $this->request->getParam('title', ''),
					'key'       => $this->request->getParam('key'),
					'startDate' => $this->request->getParam('startDate', ''),
					'parts'     => $this->request->getParam('parts'),
				]
			);
		} catch (ProjectCopyException $e) {
			return new JSONResponse(
				['error' => $e->getMessage(), 'code' => $e->getReason(), 'step' => $e->getStep()],
				$e->getStatus()
			);
		}

		return new JSONResponse($result, Http::STATUS_CREATED);
	}//end copy()
}//end class
