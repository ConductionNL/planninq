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
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads and writes the assignment notification switch.
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
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
	 * The user value for email, off unless `true` (collaboration-notifications task 2.3).
	 *
	 * @var string
	 */
	public const EMAIL_KEY = 'notify_by_email';

	/**
	 * The due-date reminder switch (kept by SettingsService); off silences the reminder mail too.
	 *
	 * @var string
	 */
	public const DUE_REMINDER_KEY = 'notify_due_reminder';

	/**
	 * OpenRegister's per-user notification preference service.
	 *
	 * @var string
	 */
	private const PREFERENCES = 'OCA\OpenRegister\Service\Notification\NotificationPreferenceService';

	/**
	 * Constructor.
	 *
	 * @param IConfig            $config     The per-user values.
	 * @param IAppManager        $appManager Tells whether OpenRegister is installed.
	 * @param ContainerInterface $container  Resolves OpenRegister's preference service.
	 * @param LoggerInterface    $logger     Logs a skipped or failed override write.
	 * @param IUserManager       $users      Tells whether the account has an email address.
	 */
	public function __construct(
		private IConfig $config,
		private IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
		private IUserManager $users,
	) {
	}//end __construct()

	/**
	 * The user's switches, for GET /api/settings.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<string,bool>
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.3
	 */
	public function values(string $userId): array {
		$user = $this->users->get($userId);
		return [
			self::ASSIGNED_KEY => $this->isAssignedOn(userId: $userId),
			self::EMAIL_KEY    => $this->config->getUserValue($userId, Application::APP_ID, self::EMAIL_KEY, 'false') === 'true',
			'hasEmail'         => $user !== null && (string)$user->getEMailAddress() !== '',
		];
	}//end values()

	/**
	 * Apply the switches present in a personal-settings save.
	 *
	 * @param string              $userId The user.
	 * @param array<string,mixed> $data   The posted values.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.3
	 */
	public function apply(string $userId, array $data): void {
		if (array_key_exists(self::ASSIGNED_KEY, $data) === true) {
			$enabled = filter_var($data[self::ASSIGNED_KEY], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			$this->setAssigned(userId: $userId, enabled: ($enabled !== false));
		}

		if (array_key_exists(self::EMAIL_KEY, $data) === true) {
			$byEmail = filter_var($data[self::EMAIL_KEY], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			$stored  = 'false';
			if ($byEmail === true) {
				$stored = 'true';
			}

			$this->config->setUserValue($userId, Application::APP_ID, self::EMAIL_KEY, $stored);
		}
	}//end apply()

	/**
	 * Whether the user hears about tasks assigned to them (default on).
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 */
	private function isAssignedOn(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, self::ASSIGNED_KEY, 'true') !== 'false';
	}//end isAssignedOn()

	/**
	 * Store the switch and write it through to OpenRegister: off writes
	 * `{"enabled": false}` for both assignment rules, on clears the override
	 * so the rule's default applies. Without OpenRegister the value is still
	 * stored and the write-through is skipped.
	 *
	 * @param string $userId  The user.
	 * @param bool   $enabled Whether assignment notifications are on.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-1.2
	 */
	public function setAssigned(string $userId, bool $enabled): void {
		$stored   = 'false';
		$override = ['enabled' => false];
		if ($enabled === true) {
			$stored   = 'true';
			$override = null;
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::ASSIGNED_KEY, $stored);
		$preferences = $this->preferences();
		if ($preferences === null) {
			$this->logger->info('Planninq: OpenRegister unavailable, notify_assigned override skipped', ['user' => $userId]);
			return;
		}

		foreach (self::ASSIGNED_RULES as $rule) {
			try {
				$preferences->setOverride(userId: $userId, schemaSlug: 'task', notificationKey: $rule, override: $override);
			} catch (\Throwable $e) {
				$this->logger->warning(
					'Planninq: failed to write the notify_assigned override',
					['user' => $userId, 'rule' => $rule, 'exception' => $e->getMessage()]
				);
			}
		}
	}//end setAssigned()

	/**
	 * OpenRegister's preference service, or null without OpenRegister.
	 *
	 * @return object|null
	 */
	private function preferences(): ?object {
		if ($this->appManager->isInstalled('openregister') === false) {
			return null;
		}

		try {
			return $this->container->get(self::PREFERENCES);
		} catch (\Throwable $e) {
			$this->logger->info('Planninq: NotificationPreferenceService unavailable', ['exception' => $e->getMessage()]);
			return null;
		}
	}//end preferences()
}//end class
