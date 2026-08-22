<?php

/**
 * Planninq Reconcile Due-Reminder Overrides Repair Step
 *
 * One-shot repair step that seeds OpenRegister notification overrides for users
 * who opted out of due-date reminders (notify_due_reminder = false) before the
 * declarative dispatch existed.
 *
 * @category Repair
 * @package  OCA\Planninq\Repair
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\SettingsService;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Seeds OpenRegister notification overrides for pre-existing due-reminder opt-outs.
 *
 * @spec openspec/specs/task-notifications.md
 */
class ReconcileDueReminderOverrides implements IRepairStep {
	/**
	 * Constructor for ReconcileDueReminderOverrides.
	 *
	 * @param IConfig $config The user config interface
	 * @param SettingsService $settingsService The settings service
	 * @param LoggerInterface $logger The logger interface
	 *
	 * @return void
	 */
	public function __construct(
		private IConfig $config,
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Get the name of this repair step.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/task-notifications.md
	 */
	public function getName(): string {
		return 'Reconcile Planninq due-date reminder opt-outs to OpenRegister overrides';
	}//end getName()

	/**
	 * Seed `{"enabled": false}` overrides for every user with a stored
	 * notify_due_reminder = false. Idempotent: only seeds when no override
	 * already exists for the user, so it never clobbers an override the user
	 * changed since.
	 *
	 * @param IOutput $output The output interface for progress reporting
	 *
	 * @return void
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
	 */
	public function run(IOutput $output): void {
		if ($this->settingsService->isOpenRegisterAvailable() === false) {
			$output->warning('OpenRegister is not available; skipping due-reminder override reconciliation.');
			return;
		}

		$userIds = $this->config->getUsersForUserValue(Application::APP_ID, 'notify_due_reminder', 'false');
		if (count($userIds) === 0) {
			$output->info('No pre-existing due-reminder opt-outs to reconcile.');
			return;
		}

		$seeded = 0;
		foreach ($userIds as $userId) {
			try {
				if ($this->hasExistingOverride(userId: $userId) === true) {
					// User already has an explicit override — never clobber it.
					continue;
				}

				$written = $this->settingsService->writeDueReminderOverride(
					userId: $userId,
					override: ['enabled' => false]
				);
				if ($written === true) {
					$seeded++;
				}
			} catch (\Throwable $e) {
				$this->logger->warning(
					'Planninq: failed to reconcile due-reminder override',
					['user' => $userId, 'exception' => $e->getMessage()]
				);
			}//end try
		}//end foreach

		$output->info(sprintf('Seeded %d due-reminder override(s) for pre-existing opt-outs.', $seeded));
	}//end run()

	/**
	 * Check whether the user already has an explicit OpenRegister override for
	 * (task, taskDueSoon). Returns false when OpenRegister is unavailable or the
	 * lookup fails (the caller then attempts a best-effort seed).
	 *
	 * @param string $userId The user UID.
	 *
	 * @return bool True when an override already exists.
	 */
	private function hasExistingOverride(string $userId): bool {
		try {
			$preferenceService = $this->settingsService->getNotificationPreferenceService();
			if ($preferenceService === null) {
				return false;
			}

			$override = $preferenceService->getOverride(
				userId: $userId,
				schemaSlug: SettingsService::TASK_SCHEMA_SLUG,
				notificationKey: SettingsService::DUE_REMINDER_RULE_KEY
			);
			return ($override !== null);
		} catch (\Throwable $e) {
			return false;
		}//end try

	}//end hasExistingOverride()
}//end class
