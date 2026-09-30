<?php

/**
 * Planninq DueSoonEmailRecipientResolver
 *
 * Recipient of the due-date reminder email rule: silent when the assignee switched
 * due-date reminders off.
 *
 * @category Notification
 * @package  OCA\Planninq\Notification
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Notification;

use OCA\Planninq\Service\NotificationSwitchService;

/**
 * The assignee of `taskDueSoonEmail`.
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */
final class DueSoonEmailRecipientResolver extends EmailOptInRecipientResolver {

	/**
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	protected function inAppSwitch(): string {
		return NotificationSwitchService::DUE_REMINDER_KEY;
	}//end inAppSwitch()
}//end class
