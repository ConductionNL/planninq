<?php

/**
 * Planninq MailIntakeController
 *
 * The admin's "Test connection" for the task intake mailbox.
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\MailboxFactory;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin-only mailbox test.
 */
class MailIntakeController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request   The request.
	 * @param SettingsService $settings  The settings service (admin check).
	 * @param MailboxFactory  $mailboxes Opens the stored mailbox.
	 */
	public function __construct(
		IRequest $request,
		private SettingsService $settings,
		private MailboxFactory $mailboxes,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Log in to the stored mailbox and report the folder's message count.
	 *
	 * @return JSONResponse 200 with `{success, messages}`; 400 with the server's reason; 403 for non-admins.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.2
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function test(): JSONResponse {
		if ($this->settings->isCurrentUserAdmin() === false) {
			return new JSONResponse(['error' => 'Admin privileges required.'], Http::STATUS_FORBIDDEN);
		}

		try {
			return new JSONResponse(['success' => true, 'messages' => $this->mailboxes->test()]);
		} catch (\Throwable $e) {
			return new JSONResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end test()
}//end class
