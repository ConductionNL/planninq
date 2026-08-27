<?php

/**
 * Planninq Initialize Settings Repair Step
 *
 * Repair step that initializes Planninq register and schemas on install/upgrade.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\SettingsService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Repair step that initializes Planninq configuration via SettingsService.
 *
 * @spec openspec/specs/register-schemas/spec.md
 */
class InitializeSettings implements IRepairStep {
	/**
	 * Constructor for InitializeSettings.
	 *
	 * @param SettingsService $settingsService The settings service
	 * @param RegisterImportService $registerImport The register import service
	 * @param LoggerInterface $logger The logger interface
	 *
	 * @return void
	 */
	public function __construct(
		private SettingsService $settingsService,
		private RegisterImportService $registerImport,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Get the name of this repair step.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/register-schemas/spec.md
	 */
	public function getName(): string {
		return 'Initialize Planninq register and schemas via ConfigurationService';
	}//end getName()

	/**
	 * Run the repair step to initialize Planninq configuration.
	 *
	 * @param IOutput $output The output interface for progress reporting
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-1
	 */
	public function run(IOutput $output): void {
		$output->info('Initializing Planninq configuration...');

		if ($this->settingsService->isOpenRegisterAvailable() === false) {
			$output->warning(
				'OpenRegister is not installed or enabled. Skipping auto-configuration.'
			);
			$this->logger->warning(
				'Planninq: OpenRegister not available, skipping register initialization'
			);
			return;
		}

		try {
			$result = $this->registerImport->load();

			if ($result['success'] === true) {
				$version = ($result['version'] ?? 'unknown');
				$output->info(
					'Planninq configuration imported successfully (version: ' . $version . ')'
				);
				return;
			}

			$message = ($result['message'] ?? 'unknown error');
			$output->warning(
				'Planninq configuration import issue: ' . $message
			);
		} catch (\Throwable $e) {
			$output->warning('Could not auto-configure Planninq: ' . $e->getMessage());
			$this->logger->error(
				'Planninq initialization failed',
				['exception' => $e->getMessage()]
			);
		}//end try
	}//end run()
}//end class
