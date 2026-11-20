<?php

/**
 * Planninq IMAP Connector
 *
 * Opens the intake mailbox from the mail intake settings.
 *
 * @category Service
 * @package  OCA\Planninq\Service\MailIntake
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

namespace OCA\Planninq\Service\MailIntake;

use OCA\Planninq\Service\ImapClient;
use RuntimeException;

/**
 * Connects to the configured IMAP server: `ssl` speaks TLS from the start,
 * `tls` upgrades a plain connection with STARTTLS, `none` stays plain.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */
class ImapConnector implements MailboxConnector {

	/**
	 * Log in and open the folder.
	 *
	 * @param array<string,string> $settings The `mail_intake_*` settings.
	 * @param string               $password The resolved password.
	 *
	 * @return MailboxClient
	 *
	 * @throws RuntimeException When the settings are incomplete, or the server cannot be reached or refuses.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) ImapClient::open() is the client's named constructor: it greets and logs in before a client exists.
	 */
	public function connect(array $settings, #[\SensitiveParameter] string $password): MailboxClient {
		$host       = trim((string)($settings[MailIntakeSettings::HOST] ?? ''));
		$port       = (int)($settings[MailIntakeSettings::PORT] ?? 0);
		$encryption = strtolower(trim((string)($settings[MailIntakeSettings::ENCRYPTION] ?? 'ssl')));
		$username   = (string)($settings[MailIntakeSettings::USERNAME] ?? '');
		$folder     = trim((string)($settings[MailIntakeSettings::FOLDER] ?? ''));

		if ($host === '' || $port < 1 || $port > 65535 || $username === '' || in_array($encryption, ['ssl', 'tls', 'none'], true) === false) {
			throw new RuntimeException('The mailbox settings are incomplete.');
		}

		if ($folder === '') {
			$folder = 'INBOX';
		}

		$transport = $this->openTransport(host: $host, port: $port, tls: ($encryption === 'ssl'));

		return ImapClient::open(
			transport: $transport,
			username: $username,
			password: $password,
			folder: $folder,
			startTls: ($encryption === 'tls'),
		);
	}//end connect()

	/**
	 * Open the connection to the server.
	 *
	 * @param string $host The server's host name.
	 * @param int    $port The port.
	 * @param bool   $tls  Whether to speak TLS from the first byte.
	 *
	 * @return ImapTransport
	 *
	 * @throws RuntimeException When the server cannot be reached.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	protected function openTransport(string $host, int $port, bool $tls): ImapTransport {
		return new SocketImapTransport(host: $host, port: $port, tls: $tls);
	}//end openTransport()
}//end class
