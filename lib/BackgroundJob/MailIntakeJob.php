<?php

/**
 * Planninq Mail Intake Job
 *
 * Reads the intake mailbox every five minutes and turns mail into tasks.
 *
 * @category BackgroundJob
 * @package  OCA\Planninq\BackgroundJob
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MailboxConnector;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use OCA\Planninq\Service\MailIntakeService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Does nothing unless intake is switched on and configured. Then it resolves
 * the password through the broker, opens the mailbox and hands at most fifty
 * unseen messages to {@see MailIntakeService}; the rest wait for the next run.
 * One message that fails stays in the inbox and does not stop the others.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
class MailIntakeJob extends TimedJob {

	/**
	 * Seconds between runs.
	 *
	 * @var int
	 */
	public const INTERVAL = 300;

	/**
	 * Messages handled per run.
	 *
	 * @var int
	 */
	public const BATCH = 50;

	/**
	 * @param ITimeFactory          $time      The clock.
	 * @param MailIntakeSettings    $settings  Whether intake is on, and the mailbox.
	 * @param MailIntakeService     $intake    Turns one message into a task.
	 * @param LoggerInterface       $logger    Logs why a run did nothing.
	 * @param MailboxConnector|null $connector Opens the mailbox; null while no IMAP client is installed.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly MailIntakeSettings $settings,
		private readonly MailIntakeService $intake,
		private readonly LoggerInterface $logger,
		private readonly ?MailboxConnector $connector = null,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL);
		$this->setTimeSensitivity(sensitivity: self::TIME_SENSITIVE);
	}//end __construct()

	/**
	 * Read the mailbox once.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	protected function run($argument): void {
		if ($this->settings->isReady() === false) {
			return;
		}

		$box = $this->openBox();
		if ($box === null) {
			return;
		}

		foreach ($box->unseen(limit: self::BATCH) as $mail) {
			try {
				$this->intake->process(mail: $mail, box: $box);
			} catch (Throwable $e) {
				$this->logger->error('Planninq: a mail could not be turned into a task; it stays in the inbox', ['exception' => get_class($e)]);
			}
		}
	}//end run()

	/**
	 * Open the intake mailbox, or log why it cannot be opened.
	 *
	 * @return MailboxClient|null The mailbox, or null when there is no IMAP client, no password or no connection.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	private function openBox(): ?MailboxClient {
		if ($this->connector === null) {
			$this->logger->warning('Planninq: mail intake is on, but no IMAP client is available');
			return null;
		}

		$password = $this->settings->password();
		if ($password === null) {
			$this->logger->warning('Planninq: mail intake is on, but its password could not be resolved');
			return null;
		}

		try {
			return $this->connector->connect(settings: $this->settings->get(), password: $password);
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: the intake mailbox could not be opened', ['exception' => get_class($e)]);
			return null;
		}
	}//end openBox()
}//end class
