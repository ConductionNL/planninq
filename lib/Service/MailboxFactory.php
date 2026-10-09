<?php

/**
 * Planninq MailboxFactory
 *
 * Opens the configured intake mailbox: settings from app config, the password
 * resolved from the credential broker only now.
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Service\Mail\Mailbox;
use OCA\Planninq\Service\Mail\SocketImapTransport;
use RuntimeException;

/**
 * Builds and opens the intake mailbox.
 */
class MailboxFactory {

	/**
	 * Constructor.
	 *
	 * @param MailIntakeConfig    $config      The mailbox settings.
	 * @param MailCredentialStore $credentials Resolves the password.
	 */
	public function __construct(
		private MailIntakeConfig $config,
		private MailCredentialStore $credentials,
	) {
	}//end __construct()

	/**
	 * Connect, log in and open the folder.
	 *
	 * @return Mailbox The open mailbox.
	 *
	 * @throws RuntimeException When the password cannot be resolved or the server refuses.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	public function open(): Mailbox {
		$client = $this->build();
		$client->open();

		return $client;
	}//end open()

	/**
	 * Try the connection and report the folder's message count.
	 *
	 * @return int The number of messages in the folder.
	 *
	 * @throws RuntimeException When the connection or login fails.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.3
	 */
	public function test(): int {
		$client = $this->build();
		try {
			return $client->open();
		} finally {
			$client->close();
		}
	}//end test()

	/**
	 * A client over a fresh connection, not yet logged in.
	 *
	 * @return ImapClient
	 *
	 * @throws RuntimeException When the password cannot be resolved or the server is unreachable.
	 */
	private function build(): ImapClient {
		$password = $this->credentials->resolve(ref: $this->config->get(key: 'mail_intake_credential_ref'));
		if ($password === null) {
			throw new RuntimeException('The mailbox password could not be resolved from the credential broker.');
		}

		$transport = new SocketImapTransport(
			host: $this->config->get(key: 'mail_intake_host'),
			port: (int)$this->config->get(key: 'mail_intake_port'),
			encryption: $this->config->get(key: 'mail_intake_encryption')
		);

		return new ImapClient(
			transport: $transport,
			username: $this->config->get(key: 'mail_intake_username'),
			password: $password,
			folder: $this->config->get(key: 'mail_intake_folder')
		);
	}//end build()
}//end class
