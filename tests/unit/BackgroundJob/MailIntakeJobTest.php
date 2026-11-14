<?php

/**
 * Unit tests for MailIntakeJob: a no-op while intake is off, at most fifty messages a run.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\MailIntakeJob;
use OCA\Planninq\Service\MailIntake\IncomingMail;
use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MailboxConnector;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use OCA\Planninq\Service\MailIntakeService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * The job over doubles of the settings, the connector and the intake.
 *
 * @covers \OCA\Planninq\BackgroundJob\MailIntakeJob
 * @uses \OCA\Planninq\Service\MailIntake\IncomingMail
 */
class MailIntakeJobTest extends TestCase {

	/**
	 * Run the job once.
	 *
	 * @param MailIntakeJob $job The job.
	 *
	 * @return void
	 */
	private function runOnce(MailIntakeJob $job): void {
		(new ReflectionMethod(MailIntakeJob::class, 'run'))->invoke($job, null);
	}//end runOnce()

	/**
	 * Settings that are ready, with the given password.
	 *
	 * @param bool        $ready    Whether intake is on and configured.
	 * @param string|null $password The resolved password.
	 *
	 * @return MailIntakeSettings
	 */
	private function settings(bool $ready, ?string $password = 's3cret!'): MailIntakeSettings {
		$settings = $this->createMock(MailIntakeSettings::class);
		$settings->method('isReady')->willReturn($ready);
		$settings->method('password')->willReturn($password);
		$settings->method('get')->willReturn(MailIntakeSettings::DEFAULTS);

		return $settings;
	}//end settings()

	/**
	 * Messages numbered from one.
	 *
	 * @param int $count How many.
	 *
	 * @return array<int,IncomingMail>
	 */
	private function messages(int $count): array {
		$messages = [];
		for ($i = 1; $i <= $count; $i++) {
			$messages[] = new IncomingMail(uid: (string)$i, message: ['messageId' => '<' . $i . '@mail>', 'from' => 'anna@gemeente.nl']);
		}

		return $messages;
	}//end messages()

	/**
	 * The job runs every five minutes.
	 *
	 * @return void
	 */
	public function testTheJobRunsEveryFiveMinutes(): void {
		$job = new MailIntakeJob($this->createMock(ITimeFactory::class), $this->settings(ready: false), $this->createMock(MailIntakeService::class), $this->createMock(LoggerInterface::class));

		$this->assertSame(300, $job->getInterval());
	}//end testTheJobRunsEveryFiveMinutes()

	/**
	 * With intake off the mailbox is not opened and no password is resolved.
	 *
	 * @return void
	 */
	public function testDisabledIntakeDoesNothing(): void {
		$settings = $this->settings(ready: false);
		$settings->expects($this->never())->method('password');
		$connector = $this->createMock(MailboxConnector::class);
		$connector->expects($this->never())->method('connect');
		$intake = $this->createMock(MailIntakeService::class);
		$intake->expects($this->never())->method('process');

		$this->runOnce(job: new MailIntakeJob($this->createMock(ITimeFactory::class), $settings, $intake, $this->createMock(LoggerInterface::class), $connector));
	}//end testDisabledIntakeDoesNothing()

	/**
	 * The job asks for fifty messages and hands each to the intake, with the resolved password.
	 *
	 * @return void
	 */
	public function testProcessesAtMostFiftyMessages(): void {
		$box = $this->createMock(MailboxClient::class);
		$box->expects($this->once())->method('unseen')->with(50)->willReturn($this->messages(count: 50));
		$connector = $this->createMock(MailboxConnector::class);
		$connector->expects($this->once())->method('connect')->with(MailIntakeSettings::DEFAULTS, 's3cret!')->willReturn($box);

		$seen = [];
		$intake = $this->createMock(MailIntakeService::class);
		$intake->method('process')->willReturnCallback(
			function (IncomingMail $mail, MailboxClient $from) use (&$seen, $box): string {
				$this->assertSame($box, $from);
				$seen[] = $mail->uid;
				return MailIntakeService::CREATED;
			}
		);

		$this->runOnce(job: new MailIntakeJob($this->createMock(ITimeFactory::class), $this->settings(ready: true), $intake, $this->createMock(LoggerInterface::class), $connector));

		$this->assertSame(array_map('strval', range(1, 50)), $seen);
	}//end testProcessesAtMostFiftyMessages()

	/**
	 * One message that fails does not stop the others.
	 *
	 * @return void
	 */
	public function testAFailingMessageDoesNotStopTheRun(): void {
		$box = $this->createMock(MailboxClient::class);
		$box->method('unseen')->willReturn($this->messages(count: 3));
		$connector = $this->createMock(MailboxConnector::class);
		$connector->method('connect')->willReturn($box);

		$seen = [];
		$intake = $this->createMock(MailIntakeService::class);
		$intake->method('process')->willReturnCallback(
			function (IncomingMail $mail) use (&$seen): string {
				$seen[] = $mail->uid;
				if ($mail->uid === '2') {
					throw new RuntimeException('save failed');
				}

				return MailIntakeService::CREATED;
			}
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->runOnce(job: new MailIntakeJob($this->createMock(ITimeFactory::class), $this->settings(ready: true), $intake, $logger, $connector));

		$this->assertSame(['1', '2', '3'], $seen);
	}//end testAFailingMessageDoesNotStopTheRun()

	/**
	 * Without an IMAP client, without a password, or when the mailbox cannot be opened, nothing is processed and it is logged.
	 *
	 * @return void
	 */
	public function testARunThatCannotReadTheMailboxLogsWhy(): void {
		$intake = $this->createMock(MailIntakeService::class);
		$intake->expects($this->never())->method('process');

		$failing = $this->createMock(MailboxConnector::class);
		$failing->method('connect')->willThrowException(new RuntimeException('login refused'));

		$cases = [
			[$this->settings(ready: true), null, 'no IMAP client'],
			[$this->settings(ready: true, password: null), $this->createMock(MailboxConnector::class), 'password'],
			[$this->settings(ready: true), $failing, 'could not be opened'],
		];
		foreach ($cases as [$settings, $connector, $expected]) {
			$logger = $this->createMock(LoggerInterface::class);
			$logger->expects($this->once())->method('warning')->with($this->stringContains($expected));
			$this->runOnce(job: new MailIntakeJob($this->createMock(ITimeFactory::class), $settings, $intake, $logger, $connector));
		}
	}//end testARunThatCannotReadTheMailboxLogsWhy()
}//end class
