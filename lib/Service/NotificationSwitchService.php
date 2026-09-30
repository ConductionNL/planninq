<?php

/**
 * Planninq NotificationSwitchService
 *
 * A person's own notification switches beyond the due-date reminder. Each is
 * a Nextcloud user value; the assignment switch is also written through to
 * OpenRegister's per-user override of the two declared assignment rules, so
 * OpenRegister, which delivers the notification, skips a user who switched
 * it off. Planninq dispatches nothing itself.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Reads and writes the assignment notification switch.
 *
 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
 */
class NotificationSwitchService {

	/**
	 * The user value for assignment notifications, on unless `false`.
	 *
	 * @var string
	 */
	public const ASSIGNED_KEY = 'notify_assigned';

	/**
	 * The task schema's assignment rules the switch overrides.
	 *
	 * @var array<int,string>
	 */
	public const ASSIGNED_RULES = ['taskAssignedOnCreate', 'taskAssigned'];

	/**
	 * Constructor.
	 *
	 * @param IConfig         $config The per-user values.
	 * @param LoggerInterface $logger Logs a failed override write.
	 */
	public function __construct(
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether the user hears about tasks assigned to them (default on).
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
	 */
	public function isAssignedOn(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, self::ASSIGNED_KEY, 'true') !== 'false';
	}//end isAssignedOn()

	/**
	 * Store the switch and write it through to OpenRegister: off writes
	 * `{"enabled": false}` for both assignment rules, on clears the override
	 * so the rule's default applies. Without OpenRegister the value is still
	 * stored and the write-through is skipped.
	 *
	 * @param string      $userId      The user.
	 * @param bool        $enabled     Whether assignment notifications are on.
	 * @param object|null $preferences OpenRegister's NotificationPreferenceService, or null.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
	 */
	public function setAssigned(string $userId, bool $enabled, ?object $preferences): void {
		$stored   = 'false';
		$override = ['enabled' => false];
		if ($enabled === true) {
			$stored   = 'true';
			$override = null;
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::ASSIGNED_KEY, $stored);
		if ($preferences === null) {
			return;
		}

		foreach (self::ASSIGNED_RULES as $rule) {
			try {
				$preferences->setOverride(userId: $userId, schemaSlug: 'task', notificationKey: $rule, override: $override);
			} catch (\Throwable $e) {
				$this->logger->warning('Planninq: failed to write the notify_assigned override', ['user' => $userId, 'rule' => $rule, 'exception' => $e->getMessage()]);
			}
		}
	}//end setAssigned()
}//end class
