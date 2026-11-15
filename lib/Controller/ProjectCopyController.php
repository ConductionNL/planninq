<?php

/**
 * Planninq ProjectCopyController
 *
 * `POST /api/projects/{projectId}/copy`: copy a project, or start one from a
 * template, with every reference inside it intact.
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\ProjectCopyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Project copy endpoint.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request     The request.
	 * @param ProjectCopyService $copies      Makes the copy, behind the creation policy and a read check on the source.
	 * @param IUserSession       $userSession The caller, who owns the copy.
	 */
	public function __construct(
		IRequest $request,
		private ProjectCopyService $copies,
		private IUserSession $userSession,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Copy a project the caller can read, when the caller may create projects.
	 *
	 * Body: `title` (required), `key`, `startDate` (`YYYY-MM-DD`) and `parts`
	 * (`columns`, `phases`, `tasks`, `dependencies`, `people`, booleans).
	 *
	 * @param string $projectId The project or template to copy.
	 *
	 * @return JSONResponse 201 with the new project; 400, 401, 403, 404, 500 or 503 otherwise.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	#[NoAdminRequired]
	public function copy(string $projectId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$request = [];
		foreach (['title', 'key', 'startDate', 'parts'] as $field) {
			$value = $this->request->getParam($field);
			if ($value !== null) {
				$request[$field] = $value;
			}
		}

		$result = $this->copies->copy(sourceId: $projectId, uid: $user->getUID(), request: $request);
		if ($result['status'] === Http::STATUS_CREATED) {
			return new JSONResponse($result['project'], Http::STATUS_CREATED);
		}

		return new JSONResponse(['error' => $result['error'] ?? ''], $result['status']);
	}//end copy()
}//end class
