<?php

/**
 * Planninq Due-Reminder Window Service
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Patches the live task schema's due-reminder window.
 *
 * @spec openspec/specs/task-notifications/spec.md
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
			$this->logger->info('Planninq: OpenRegister unavailable, lead-time window not patched on live schema');
			return;
		}

		try {
			$this->patchLiveSchema(hours: $hours);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Planninq: failed to patch due-reminder window on live schema',
				['exception' => $e->getMessage()]
			);
		}//end try

	}//end patch()

	/**
	 * Write the lead-time window into the live task schema's `taskDueSoon`
	 * rule; logged and skipped when the schema or the rule is missing.
	 *
	 * @param int $hours The lead time in hours to apply.
	 *
	 * @return void
	 */
	private function patchLiveSchema(int $hours): void {
		$schemaMapper = $this->container->get('OCA\OpenRegister\Db\SchemaMapper');
		$schema       = $this->taskSchema(schemaMapper: $schemaMapper);
		if ($schema === null) {
			$this->logger->warning('Planninq: task schema not found, cannot patch due-reminder window');
			return;
		}

		$configuration = $this->withWindow(configuration: ($schema->getConfiguration() ?? []), hours: $hours);
		if ($configuration === null) {
			$this->logger->warning('Planninq: taskDueSoon rule not present on live schema, skipping window patch');
			return;
		}

		$schema->setConfiguration($configuration);
		$schemaMapper->update($schema);
		$this->logger->info('Planninq: patched taskDueSoon window to ' . $this->leadHoursToDuration(hours: $hours));
	}//end patchLiveSchema()

	/**
	 * A schema configuration with the `taskDueSoon` due-date filter set to the
	 * lead time, or null when the configuration has no such rule.
	 *
	 * @param array<string,mixed> $configuration The schema configuration.
	 * @param int                 $hours         The lead time in hours.
	 *
	 * @return array<string,mixed>|null
	 */
	private function withWindow(array $configuration, int $hours): ?array {
		if (isset($configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate']) === false) {
			return null;
		}

		$configuration['x-openregister-notifications'][self::DUE_REMINDER_RULE_KEY]['trigger']['filter']['dueDate'] = [
			'operator' => 'withinNext',
			'value' => $this->leadHoursToDuration(hours: $hours),
		];

		return $configuration;
	}//end withWindow()

	/**
	 * The first schema with the task slug, or null when there is none.
	 *
	 * @param object $schemaMapper OpenRegister's SchemaMapper.
	 *
	 * @return object|null
	 */
	private function taskSchema(object $schemaMapper): ?object {
		$schemas = $schemaMapper->findBySlug(self::TASK_SCHEMA_SLUG);
		if (is_array($schemas) === false || count($schemas) === 0) {
			return null;
		}

		return $schemas[0];
	}//end taskSchema()
}//end class
