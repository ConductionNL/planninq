<?php

/**
 * Planninq Mail Intake Controller
 *
 * POST /api/settings/mail-intake/test: the admin's "Test connection" for
 * the intake mailbox (tasks-create-by-email task 1.2).
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
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\MailIntake\MailboxConnectionTest;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The intake mailbox's admin actions.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
class MailIntakeController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request         The request.
	 * @param MailboxConnectionTest $connectionTest  Opens the mailbox once.
	 * @param SettingsService       $settingsService The admin check.
	 */
	public function __construct(
		IRequest $request,
		private readonly MailboxConnectionTest $connectionTest,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Test the connection with the saved settings.
	 *
	 * @return JSONResponse `{ok, reason, messages}`; 403 for anyone but an admin.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function test(): JSONResponse {
		if ($this->settingsService->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Admin privileges required to test the mailbox.'], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse($this->connectionTest->run());
	}//end test()
}//end class
