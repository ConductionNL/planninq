<?php

/**
 * Planninq project copy controller
 *
 * Copies a project, or starts one from a template. The caller must be the
 * project owner or manager (or an admin), or, for a template, anyone who may
 * create projects. The copying lives in ProjectCopyService.
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\ProjectCopyException;
use OCA\Planninq\Service\CreationPolicyService;
use OCA\Planninq\Service\ProjectCopyService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
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
	 * @param IRequest                 $request      The request.
	 * @param ProjectCopyService       $copyService  Does the copy.
	 * @param ProjectMembershipService $membership   Reads the source project.
	 * @param CreationPolicyService    $policy       Tells who may create projects.
	 * @param IUserSession             $userSession  The caller.
	 * @param IGroupManager            $groupManager Tells an admin.
	 * @param WorkItemKeyService       $keys         The project key rules.
	 */
	public function __construct(
		IRequest $request,
		private ProjectCopyService $copyService,
		private ProjectMembershipService $membership,
		private CreationPolicyService $policy,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private WorkItemKeyService $keys,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Copy a project into a new one owned by the caller.
	 *
	 * @param string $id The source project UUID.
	 *
	 * @return JSONResponse 200 with the new id and counts; 401, 403, 404 or 500 naming the failed step.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	#[NoAdminRequired]
	public function create(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$source = $this->membership->objectData(schema: 'project', id: $id);
		if ($source === null) {
			return new JSONResponse(['error' => 'Project not found.'], Http::STATUS_NOT_FOUND);
		}

		if ($this->mayCopy(source: $source, uid: $user->getUID()) === false) {
			return new JSONResponse(['error' => 'You cannot copy this project.'], Http::STATUS_FORBIDDEN);
		}

		$params  = $this->request->getParams();
		$key     = $this->keys->normalise(key: ($params['key'] ?? null));
		$options = [
			'title'     => trim((string)($params['title'] ?? '')),
			'key'       => $key,
			'startDate' => (string)($params['startDate'] ?? ''),
			'parts'     => (array)($params['parts'] ?? []),
		];
		if ($options['title'] === '') {
			return new JSONResponse(['error' => 'Give the new project a title.'], Http::STATUS_BAD_REQUEST);
		}

		$refusal = $this->keyRefusal(key: $key);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return new JSONResponse($this->copyService->copy(sourceId: $id, userId: $user->getUID(), options: $options));
		} catch (ProjectCopyException $e) {
			return new JSONResponse(['error' => $e->getMessage(), 'step' => $e->getStep()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end create()

	/**
	 * The refusal for a malformed or taken key, or null; no key at all is fine.
	 *
	 * @param string $key The normalised key, empty for none.
	 *
	 * @return JSONResponse|null
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
	 */
	private function keyRefusal(string $key): ?JSONResponse {
		if ($key === '') {
			return null;
		}

		if ($this->keys->isValidFormat(key: $key) === false) {
			return new JSONResponse(
				['error' => 'A key has 2 to 10 letters and digits and starts with a letter.', 'code' => 'planninq-project-key-format'],
				Http::STATUS_BAD_REQUEST
			);
		}

		if ($this->keys->isTaken(key: $key) === true) {
			return new JSONResponse(['error' => 'This key is already used by another project.', 'code' => 'planninq-project-key-used'], Http::STATUS_CONFLICT);
		}

		return null;
	}//end keyRefusal()

	/**
	 * Whether the caller may copy this project.
	 *
	 * Owner, manager or admin may copy any project; anyone who may create
	 * projects may start one from a template.
	 *
	 * @param array<string,mixed> $source The source project.
	 * @param string              $uid    The caller.
	 *
	 * @return bool
	 */
	private function mayCopy(array $source, string $uid): bool {
		if (($source['owner'] ?? '') === $uid || in_array($uid, (array)($source['managers'] ?? []), true) === true) {
			return true;
		}

		if ($this->groupManager->isAdmin($uid) === true) {
			return true;
		}

		return ($source['isTemplate'] ?? false) === true && $this->policy->canCreate() === true;
	}//end mayCopy()
}//end class
