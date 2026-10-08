<?php

/**
 * Planninq WorkTypeController
 *
 * The admin's rename of a work type.
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
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkTypeRenameService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin-only work type rename.
 */
class WorkTypeController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request  The request.
	 * @param SettingsService       $settings The settings service (admin check).
	 * @param WorkTypeRenameService $renames  Does the rename.
	 */
	public function __construct(
		IRequest $request,
		private SettingsService $settings,
		private WorkTypeRenameService $renames,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Rename a work type, and optionally the entries that carry it.
	 *
	 * @return JSONResponse 200 with `{success, queued}`; 400 with the reason; 403 for non-admins.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function rename(): JSONResponse {
		if ($this->settings->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Admin privileges required.'], Http::STATUS_FORBIDDEN);
		}

		$params = $this->request->getParams();
		$result = $this->renames->rename(
			from: (string)($params['from'] ?? ''),
			to: (string)($params['to'] ?? ''),
			updateEntries: filter_var(($params['updateEntries'] ?? false), FILTER_VALIDATE_BOOLEAN)
		);
		if ($result['ok'] === false) {
			return new JSONResponse(['success' => false, 'error' => $result['error']], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['success' => true, 'queued' => $result['queued']]);
	}//end rename()
}//end class
