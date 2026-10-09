<?php

/**
 * Planninq MailIntakeJob
 *
 * Every five minutes reads the intake mailbox and turns member mail into
 * tasks. Does nothing while intake is switched off.
 *
 * @category BackgroundJob
 * @package  OCA\Planninq\BackgroundJob
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\MailIntakeConfig;
use OCA\Planninq\Service\MailIntakeService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Timed job: one batch of intake mail per run.
 */
class MailIntakeJob extends TimedJob {

	/**
	 * The most messages one run handles; the rest wait for the next run.
	 *
	 * @var int
	 */
	public const BATCH = 50;

	/**
	 * Seconds between runs.
	 *
	 * @var int
	 */
	public const INTERVAL = 300;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory      $time    The time factory the job base needs.
	 * @param MailIntakeConfig  $config  Tells whether intake is on.
	 * @param MailIntakeService $service Handles the mail.
	 * @param LoggerInterface   $logger  The logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private MailIntakeConfig $config,
		private MailIntakeService $service,
		private LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL);
	}//end __construct()

	/**
	 * Run one batch.
	 *
	 * @param mixed $argument Unused; the job takes no argument, the signature is TimedJob's.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	protected function run($argument): void {
		$this->runBatch();
	}//end run()

	/**
	 * Handle a batch when intake is on.
	 *
	 * @return array<string,int> Count per outcome; empty when intake is off.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	public function runBatch(): array {
		if ($this->config->isEnabled() === false) {
			return [];
		}

		try {
			return $this->service->runBatch(limit: self::BATCH);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: mail intake failed', ['exception' => $e->getMessage()]);
			return [];
		}
	}//end runBatch()
}//end class
