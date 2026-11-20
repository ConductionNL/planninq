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

use Closure;
use OCA\Planninq\Kernel\AppIdentity;
use OCA\Planninq\Service\RetentionCategoryService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
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
	 * @return Http\JSONResponse `{categories, picks}`; 403 for a non-admin.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	#[Http\Attribute\AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function index(): Http\JSONResponse {
		return $this->asAdmin(
			action: fn (): Http\JSONResponse => new Http\JSONResponse(
				['categories' => $this->retention->categories(), 'picks' => $this->retention->picks()]
			)
		);
	}//end index()

	/**
	 * Store the pick and write it into the live schemas.
	 *
	 * @return Http\JSONResponse `{picks, refused, applied}`; 400 when a category is not on the list, 403 for a non-admin.
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-2.1
	 */
	#[Http\Attribute\AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function update(): Http\JSONResponse {
		return $this->asAdmin(action: fn (): Http\JSONResponse => $this->save());
	}//end update()

	/**
	 * Validate the posted pick and store it.
	 *
	 * @return Http\JSONResponse `{picks, refused, applied}`; 400 when the pick is malformed or a category is not on the list.
	 */
	private function save(): Http\JSONResponse {
		$picks = $this->request->getParam('picks', []);
		if (is_array($picks) === false) {
			return new Http\JSONResponse(['error' => 'picks must be an object of schema to category.'], Http::STATUS_BAD_REQUEST);
		}

		$result = $this->retention->save(input: $picks);
		if ($result['refused'] !== []) {
			return new Http\JSONResponse(['error' => 'Not a category of the selection list.'] + $result, Http::STATUS_BAD_REQUEST);
		}

		return new Http\JSONResponse($result);
	}//end save()

	/**
	 * Run an action for an admin; anyone else gets 403. The admin-setting
	 * attribute already enforces this; the check here is defence in depth.
	 *
	 * @param Closure(): Http\JSONResponse $action The action.
	 *
	 * @return Http\JSONResponse
	 */
	private function asAdmin(Closure $action): Http\JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new Http\JSONResponse(['error' => 'Admin privileges required.'], Http::STATUS_FORBIDDEN);
		}

		return $action();
	}//end asAdmin()
}//end class
