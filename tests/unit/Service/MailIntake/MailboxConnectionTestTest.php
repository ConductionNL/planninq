<?php

/**
 * Unit tests for MailboxConnectionTest: the admin's "Test connection".
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service\MailIntake;

use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MailboxConnectionTest;
use OCA\Planninq\Service\MailIntake\MailboxConnector;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The test opens the folder with the stored password and counts it, or says why not.
 *
 * @covers \OCA\Planninq\Service\MailIntake\MailboxConnectionTest
 */
class MailboxConnectionTestTest extends TestCase {

	/**
	 * Settings with a host, a username and a stored password reference, intake still off.
	 *
	 * @param array<string,string> $override Keys to change.
	 * @param string|null          $password What the broker hands out.
	 *
	 * @return MailIntakeSettings
	 */
	private function settings(array $override = [], ?string $password = 's3cret!'): MailIntakeSettings {
		$values = array_merge(
			MailIntakeSettings::DEFAULTS,
			[
				MailIntakeSettings::HOST           => 'imap.example.org',
				MailIntakeSettings::USERNAME       => 'planninq',
				MailIntakeSettings::CREDENTIAL_REF => 'cred-1',
			],
			$override
		);
		$settings = $this->createMock(MailIntakeSettings::class);
		$settings->method('get')->willReturn($values);
		$settings->method('password')->willReturn($password);

		return $settings;
	}//end settings()

	/**
	 * A connector whose folder holds this many messages.
	 *
	 * @param int $count The message count.
	 *
	 * @return MailboxConnector
	 */
	private function connector(int $count): MailboxConnector {
		$box = $this->createMock(MailboxClient::class);
		$box->method('count')->willReturn($count);
		$connector = $this->createMock(MailboxConnector::class);
		$connector->expects($this->once())
			->method('connect')
			->with($this->callback(static fn (array $settings): bool => $settings[MailIntakeSettings::HOST] === 'imap.example.org'), 's3cret!')
			->willReturn($box);

		return $connector;
	}//end connector()

	/**
	 * A reachable mailbox answers ok with its message count, even while intake is off.
	 *
	 * @return void
	 */
	public function testAReachableMailboxAnswersItsMessageCount(): void {
		$test = new MailboxConnectionTest($this->settings(), $this->createMock(LoggerInterface::class), $this->connector(count: 7));

		$this->assertSame(['ok' => true, 'reason' => null, 'messages' => 7], $test->run());
	}//end testAReachableMailboxAnswersItsMessageCount()

	/**
	 * A missing host, username or stored password is incomplete and nothing is opened.
	 *
	 * @return void
	 */
	public function testIncompleteSettingsOpenNothing(): void {
		foreach ([MailIntakeSettings::HOST, MailIntakeSettings::USERNAME, MailIntakeSettings::CREDENTIAL_REF] as $key) {
			$connector = $this->createMock(MailboxConnector::class);
			$connector->expects($this->never())->method('connect');
			$test = new MailboxConnectionTest($this->settings(override: [$key => '']), $this->createMock(LoggerInterface::class), $connector);

			$this->assertSame(['ok' => false, 'reason' => MailboxConnectionTest::INCOMPLETE, 'messages' => null], $test->run(), $key);
		}
	}//end testIncompleteSettingsOpenNothing()

	/**
	 * Without an IMAP client the test says so.
	 *
	 * @return void
	 */
	public function testWithoutAnImapClientTheTestSaysSo(): void {
		$test = new MailboxConnectionTest($this->settings(), $this->createMock(LoggerInterface::class));

		$this->assertSame(MailboxConnectionTest::NO_CLIENT, $test->run()['reason']);
	}//end testWithoutAnImapClientTheTestSaysSo()

	/**
	 * A password the broker does not hand out is reported and nothing is opened.
	 *
	 * @return void
	 */
	public function testAnUnresolvedPasswordOpensNothing(): void {
		$connector = $this->createMock(MailboxConnector::class);
		$connector->expects($this->never())->method('connect');
		$test = new MailboxConnectionTest($this->settings(password: null), $this->createMock(LoggerInterface::class), $connector);

		$this->assertSame(['ok' => false, 'reason' => MailboxConnectionTest::NO_PASSWORD, 'messages' => null], $test->run());
	}//end testAnUnresolvedPasswordOpensNothing()

	/**
	 * A refused connection is reported without the server's text, and logged by exception class only.
	 *
	 * @return void
	 */
	public function testARefusedConnectionIsReportedWithoutTheServersText(): void {
		$connector = $this->createMock(MailboxConnector::class);
		$connector->method('connect')->willThrowException(new RuntimeException('NO [AUTHENTICATIONFAILED] for planninq s3cret!'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with('Planninq: the intake mailbox connection test failed', ['exception' => RuntimeException::class]);
		$test = new MailboxConnectionTest($this->settings(), $logger, $connector);

		$this->assertSame(['ok' => false, 'reason' => MailboxConnectionTest::CONNECT_FAILED, 'messages' => null], $test->run());
	}//end testARefusedConnectionIsReportedWithoutTheServersText()
}//end class
