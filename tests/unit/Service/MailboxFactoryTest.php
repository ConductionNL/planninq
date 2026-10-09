<?php

/**
 * Tests for the intake mailbox factory.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\MailboxFactory;
use OCA\Planninq\Service\MailCredentialStore;
use OCA\Planninq\Service\MailIntakeConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\MailboxFactory
 * @uses \OCA\Planninq\Service\Mail\SocketImapTransport
 */
class MailboxFactoryTest extends TestCase {

	/**
	 * A factory over fixed settings and a fixed password.
	 *
	 * @param string|null $password The resolved password, or null for none.
	 * @param int         $port     The port to connect to.
	 *
	 * @return MailboxFactory
	 */
	private function factory(?string $password, int $port = 1): MailboxFactory {
		$config = $this->createMock(originalClassName: MailIntakeConfig::class);
		$config->method('get')->willReturnCallback(
			static fn (string $key): string => match ($key) {
				'mail_intake_host' => '127.0.0.1',
				'mail_intake_port' => (string)$port,
				'mail_intake_encryption' => 'none',
				'mail_intake_username' => 'planninq',
				'mail_intake_folder' => 'INBOX',
				default => 'ref',
			}
		);
		$credentials = $this->createMock(originalClassName: MailCredentialStore::class);
		$credentials->method('resolve')->willReturn($password);

		return new MailboxFactory(config: $config, credentials: $credentials);
	}//end factory()

	/**
	 * Without a resolvable password nothing is opened or tested.
	 *
	 * @return void
	 */
	public function testNoPasswordMeansNoConnection(): void {
		foreach (['open', 'test'] as $method) {
			try {
				$this->factory(password: null)->{$method}();
				self::fail($method . ' should have thrown');
			} catch (RuntimeException $e) {
				self::assertStringContainsString('password', $e->getMessage());
			}
		}
	}//end testNoPasswordMeansNoConnection()

	/**
	 * A server that cannot be reached surfaces as an exception.
	 *
	 * @return void
	 */
	public function testUnreachableServerThrows(): void {
		$server = stream_socket_server('tcp://127.0.0.1:0');
		$port   = (int)substr(strrchr((string)stream_socket_get_name($server, false), ':'), 1);
		fclose($server);

		$this->expectException(RuntimeException::class);
		$this->factory(password: 'pw', port: $port)->open();
	}//end testUnreachableServerThrows()
}//end class
