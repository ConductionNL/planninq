<?php

/**
 * Planix Due-Reminder Window Service
 *
 * Owns one concern: reflecting the configured due-reminder lead time onto the
 * LIVE OpenRegister `task` schema, by patching the `withinNext` window of the
 * `taskDueSoon` notification rule.
 *
 * Split out of SettingsService, which had grown to cover the app/user settings
 * plane, the register bootstrap plane AND this live-schema write plane, and
 * tripped PHPMD's ExcessiveClassComplexity threshold — the rule was correctly
 * naming a real Single Responsibility violation. Both methods are moved
 * verbatim, so behaviour is unchanged.
 *
 * The OpenRegister availability probe is done here against IAppManager rather
 * than through SettingsService::isOpenRegisterAvailable(): SettingsService
 * depends on THIS class, so depending back on it would close a DI cycle.
 *
 * @category Service
 * @package  OCA\Planix\Service
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
 */

declare(strict_types=1);

namespace OCA\Planix\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Patches the live task schema's due-reminder window.
 *
 * @spec openspec/specs/task-notifications.md
 */
class DueReminderWindowService {
	/**
	 * Slug of the OpenRegister schema carrying the due-soon reminder rule.
	 *
	 * @var string
	 */
	public const TASK_SCHEMA_SLUG = 'task';

	/**
	 * Notification rule key for the due-date reminder.
	 *
	 * @var string
	 */
	public const DUE_REMINDER_RULE_KEY = 'taskDueSoon';

	/**
	 * Constructor for the DueReminderWindowService.
	 *
	 * @param IAppManager $appManager The app manager (OpenRegister availability probe)
	 * @param ContainerInterface $container The container
	 * @param LoggerInterface $logger The logger
	 *
	 * @return void
	 */
	public function __construct(
		private IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Build the ISO-8601 duration string for a `withinNext` lead window.
	 *
	 * @param int $hours The lead time in hours.
	 *
	 * @return string e.g. `PT24H`
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
	 */
	public function leadHoursToDuration(int $hours): string {
		return 'PT' . $hours . 'H';
	}//end leadHoursToDuration()

	/**
	 * Patch the live `taskDueSoon` rule's `withinNext` window on the OpenRegister
	 * task schema. No-op (logged) when OpenRegister is unavailable or the schema
	 * cannot be resolved — the IAppConfig value remains the source of truth and a
	 * later register import re-applies the rule.
	 *
	 * @param int $hours The lead time in hours to apply.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
	 */
	public function patch(int $hours): void {
		if ($this->appManager->isInstalled('openregister') === false) {
			$this->logger->info('Planix: OpenRegister unavailable, lead-time window not patched on live schema');
			return;
		}

		try {
			$schemaMapper = $this->container->get('OCA\OpenRegister\Db\SchemaMapper');
			$schemas = $schemaMapper->findBySlug(self::TASK_SCHEMA_SLUG);
			if (is_array($schemas) === false || count($schemas) === 0) {
				$this->logger->warning('Planix: task schema not found, cannot patch due-reminder window');
				return;
			}

			$schema = $schemas[0];
			$configuration = ($schema->getConfiguration() ?? []);
			if (isset($configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate']) === false) {
				$this->logger->warning('Planix: taskDueSoon rule not present on live schema, skipping window patch');
				return;
			}

			$configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate'] = [
				'operator' => 'withinNext',
				'value' => $this->leadHoursToDuration(hours: $hours),
			];

			$schema->setConfiguration($configuration);
			$schemaMapper->update($schema);
			$this->logger->info('Planix: patched taskDueSoon window to ' . $this->leadHoursToDuration(hours: $hours));
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Planix: failed to patch due-reminder window on live schema',
				['exception' => $e->getMessage()]
			);
		}//end try

	}//end patch()
}//end class
