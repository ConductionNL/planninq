<?php

/**
 * Planninq Retention Settings Controller
 *
 * GET and PUT /api/settings/retention: the selection-list categories the
 * admin can pick from and the category picked for projects, tasks and
 * planned hours (retention-on-planninq-records task 2.1). Admin only.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\Kernel\AppIdentity;
use OCA\Planninq\Service\RetentionCategoryService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The admin's retention category per planninq schema.
 *
 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
class RetentionSettingsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request         The request.
	 * @param RetentionCategoryService $retention       The categories and the pick.
	 * @param SettingsService          $settingsService Tells whether the caller is an admin.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function __construct(
		IRequest $request,
		private readonly RetentionCategoryService $retention,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The categories of OpenRegister's selection list and the current pick.
	 *
	 * @return JSONResponse `{categories, picks}`; 403 for a non-admin.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function index(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Admin privileges required.'], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(['categories' => $this->retention->categories(), 'picks' => $this->retention->picks()]);
	}//end index()

	/**
	 * Store the pick and write it into the live schemas.
	 *
	 * @return JSONResponse `{picks, refused, applied}`; 400 when a category is not on the list, 403 for a non-admin.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function update(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Admin privileges required.'], Http::STATUS_FORBIDDEN);
		}

		$picks = $this->request->getParam('picks', []);
		if (is_array($picks) === false) {
			return new JSONResponse(['error' => 'picks must be an object of schema to category.'], Http::STATUS_BAD_REQUEST);
		}

		$result = $this->retention->save(input: $picks);
		if ($result['refused'] !== []) {
			return new JSONResponse(['error' => 'Not a category of the selection list.'] + $result, Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($result);
	}//end update()
}//end class
