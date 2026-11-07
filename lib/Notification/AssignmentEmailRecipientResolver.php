<?php

/**
 * Planninq AssignmentEmailRecipientResolver
 *
 * Recipient of the assignment email rules: silent when the assignee switched
 * assignment notifications off.
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
 * The assignee of `taskAssignedOnCreateEmail` and `taskAssignedEmail`.
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */
final class AssignmentEmailRecipientResolver extends EmailOptInRecipientResolver {

	/**
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	protected function inAppSwitch(): string {
		return NotificationSwitchService::ASSIGNED_KEY;
	}//end inAppSwitch()
}//end class
