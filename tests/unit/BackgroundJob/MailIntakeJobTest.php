<?php

/**
 * Unit tests for MailIntakeJob.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
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

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\MailIntakeJob;
use OCA\Planninq\Service\MailIntakeConfig;
use OCA\Planninq\Service\MailIntakeService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\BackgroundJob\MailIntakeJob
 * @uses \OCA\Planninq\Service\MailIntakeConfig
 */
class MailIntakeJobTest extends TestCase {

	/**
	 * Build the job over a config with these values and a service mock.
	 *
	 * @param array<string,string> $values  The mail_intake_* values.
	 * @param MailIntakeService    $service The service.
	 *
	 * @return MailIntakeJob
	 */
	private function job(array $values, MailIntakeService $service): MailIntakeJob {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($values[$key] ?? $default)
		);

		return new MailIntakeJob(
			time: $this->createMock(originalClassName: ITimeFactory::class),
			config: new MailIntakeConfig(appConfig: $appConfig),
			service: $service,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

	}//end job()

	/**
	 * While intake is off the mailbox is not opened.
	 *
	 * @return void
	 */
	public function testDisabledIntakeDoesNothing(): void {
		$service = $this->createMock(originalClassName: MailIntakeService::class);
		$service->expects(self::never())->method('runBatch');

		self::assertSame(expected: [], actual: $this->job(values: ['mail_intake_enabled' => 'false', 'mail_intake_host' => 'imap.x.nl', 'mail_intake_address' => 'p@x.nl'], service: $service)->runBatch());

		// Switched on but without a host there is no mailbox to read.
		self::assertSame(expected: [], actual: $this->job(values: ['mail_intake_enabled' => 'true'], service: $service)->runBatch());

	}//end testDisabledIntakeDoesNothing()

	/**
	 * A run asks the service for at most fifty messages.
	 *
	 * @return void
	 */
	public function testProcessesAtMostFiftyMessages(): void {
		$service = $this->createMock(originalClassName: MailIntakeService::class);
		$service->expects(self::once())->method('runBatch')->with(limit: 50)->willReturn(['created' => 2]);

		$values = ['mail_intake_enabled' => 'true', 'mail_intake_host' => 'imap.x.nl', 'mail_intake_address' => 'p@x.nl'];

		self::assertSame(expected: ['created' => 2], actual: $this->job(values: $values, service: $service)->runBatch());
		self::assertSame(expected: 50, actual: MailIntakeJob::BATCH);

	}//end testProcessesAtMostFiftyMessages()
}//end class
