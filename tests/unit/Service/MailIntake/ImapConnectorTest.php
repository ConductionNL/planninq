<?php

/**
 * Unit tests for ImapConnector: the mailbox opened from the intake settings.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service\MailIntake;

use OCA\Planninq\Service\ImapClient;
use OCA\Planninq\Service\MailIntake\ImapConnector;
use OCA\Planninq\Service\MailIntake\ImapTransport;
use OCA\Planninq\Service\MailIntake\MailIntakeSettings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\MailIntake\ImapConnector
 * @uses   \OCA\Planninq\Service\ImapClient
 */
class ImapConnectorTest extends TestCase {

	/**
	 * Complete settings for a mailbox.
	 *
	 * @param array<string,string> $override Values to change.
	 *
	 * @return array<string,string>
	 */
	private static function settings(array $override = []): array {
		return array_merge(
			[
				MailIntakeSettings::HOST => 'imap.gemeente.nl',
				MailIntakeSettings::PORT => '993',
				MailIntakeSettings::ENCRYPTION => 'ssl',
				MailIntakeSettings::USERNAME => 'planninq',
				MailIntakeSettings::FOLDER => 'Taken',
			],
			$override
		);
	}//end settings()

	/**
	 * A connector whose connections come from a scripted server, noting how each was opened.
	 *
	 * @param array<int,string> $lines  What the server says, line by line.
	 * @param array<int,array>  $opened Filled with each connection's host, port and TLS flag.
	 * @param array<int,string> $sent   Filled with what the client sends.
	 *
	 * @return ImapConnector
	 */
	private function connector(array $lines, array &$opened, array &$sent): ImapConnector {
		$transport = $this->createMock(originalClassName: ImapTransport::class);
		$transport->method('readLine')->willReturnOnConsecutiveCalls(...$lines);
		$transport->method('write')->willReturnCallback(static function (string $data) use (&$sent): void {
			$sent[] = $data;
		});
		$transport->method('startTls')->willReturnCallback(static function () use (&$sent): void {
			$sent[] = '<starttls>';
		});

		return new class($transport, $opened) extends ImapConnector {

			/**
			 * @param ImapTransport    $transport The scripted connection.
			 * @param array<int,array> $opened    Notes each connection opened.
			 */
			public function __construct(private readonly ImapTransport $transport, private array &$opened) {
			}

			/**
			 * @param string $host The host.
			 * @param int    $port The port.
			 * @param bool   $tls  TLS from the start.
			 *
			 * @return ImapTransport
			 */
			protected function openTransport(string $host, int $port, bool $tls): ImapTransport {
				$this->opened[] = [$host, $port, $tls];
				return $this->transport;
			}
		};
	}//end connector()

	/**
	 * `ssl` opens a TLS connection and logs in without STARTTLS, on the configured folder.
	 *
	 * @return void
	 */
	public function testSslConnectsWithTlsFromTheStart(): void {
		$opened = [];
		$sent   = [];
		$box    = $this->connector(
			lines: ["* OK ready\r\n", "P0001 OK\r\n", "* CAPABILITY IMAP4rev1\r\n", "P0002 OK\r\n", "* 2 EXISTS\r\n", "P0003 OK\r\n", "P0004 OK\r\n"],
			opened: $opened,
			sent: $sent
		)
			->connect(settings: self::settings(), password: 'pw');

		$this->assertInstanceOf(expected: ImapClient::class, actual: $box);
		$this->assertSame(expected: [['imap.gemeente.nl', 993, true]], actual: $opened);
		$this->assertSame(expected: ["P0001 LOGIN \"planninq\" \"pw\"\r\n", "P0002 CAPABILITY\r\n", "P0003 SELECT \"Taken\"\r\n"], actual: $sent);
		$this->assertSame(expected: 2, actual: $box->count());
	}//end testSslConnectsWithTlsFromTheStart()

	/**
	 * `tls` opens a plain connection and upgrades it before the login; no folder means INBOX.
	 *
	 * @return void
	 */
	public function testTlsUpgradesWithStartTls(): void {
		$opened = [];
		$sent   = [];
		$box    = $this->connector(
			lines: ["* OK ready\r\n", "P0001 OK\r\n", "P0002 OK\r\n", "* CAPABILITY IMAP4rev1\r\n", "P0003 OK\r\n", "P0004 OK\r\n"],
			opened: $opened,
			sent: $sent
		)
			->connect(
				settings: self::settings(
					override: [MailIntakeSettings::ENCRYPTION => 'tls', MailIntakeSettings::PORT => '143', MailIntakeSettings::FOLDER => '']
				),
				password: 'pw'
			);

		$this->assertInstanceOf(expected: ImapClient::class, actual: $box);
		$this->assertSame(expected: [['imap.gemeente.nl', 143, false]], actual: $opened);
		$this->assertSame(
			expected: ["P0001 STARTTLS\r\n", '<starttls>', "P0002 LOGIN \"planninq\" \"pw\"\r\n", "P0003 CAPABILITY\r\n", "P0004 SELECT INBOX\r\n"],
			actual: $sent
		);
	}//end testTlsUpgradesWithStartTls()

	/**
	 * Incomplete settings are refused before any connection is opened.
	 *
	 * @return void
	 */
	public function testIncompleteSettingsOpenNoConnection(): void {
		$overrides = [
			[MailIntakeSettings::HOST => ''],
			[MailIntakeSettings::PORT => '0'],
			[MailIntakeSettings::PORT => '70000'],
			[MailIntakeSettings::USERNAME => ''],
			[MailIntakeSettings::ENCRYPTION => 'ssl3'],
		];
		foreach ($overrides as $override) {
			$opened = [];
			$sent   = [];
			try {
				$this->connector(lines: [], opened: $opened, sent: $sent)->connect(settings: self::settings(override: $override), password: 'pw');
				$this->fail(message: 'Incomplete settings must be refused: '.json_encode($override));
			} catch (RuntimeException $e) {
				$this->assertSame(expected: 'The mailbox settings are incomplete.', actual: $e->getMessage());
			}

			$this->assertSame(expected: [], actual: $opened);
		}
	}//end testIncompleteSettingsOpenNoConnection()
}//end class
