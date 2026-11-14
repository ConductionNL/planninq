<?php

/**
 * Planninq Mailbox Connection Test
 *
 * The admin's "Test connection" for the intake mailbox: open the configured
 * folder with the stored password and count its messages, and say why not
 * when that fails, without the password or the server's own error text.
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

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Tests the connection to the intake mailbox.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
 */
class MailboxConnectionTest {

	/**
	 * Host, username or stored password is missing.
	 */
	public const INCOMPLETE = 'incomplete';

	/**
	 * No IMAP client is installed (tasks-create-by-email task 2.1).
	 */
	public const NO_CLIENT = 'no-client';

	/**
	 * The broker did not hand out the stored password.
	 */
	public const NO_PASSWORD = 'no-password';

	/**
	 * The server refused the connection, the login or the folder.
	 */
	public const CONNECT_FAILED = 'connect-failed';

	/**
	 * Constructor.
	 *
	 * @param MailIntakeSettings    $settings  The mailbox settings and its password.
	 * @param LoggerInterface       $logger    Logger.
	 * @param MailboxConnector|null $connector Opens the mailbox; null while no IMAP client is installed.
	 */
	public function __construct(
		private readonly MailIntakeSettings $settings,
		private readonly LoggerInterface $logger,
		private readonly ?MailboxConnector $connector = null,
	) {
	}//end __construct()

	/**
	 * Open the configured folder and count its messages.
	 *
	 * Works before intake is switched on, so an admin can test first.
	 *
	 * @return array{ok: bool, reason: string|null, messages: int|null}
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function run(): array {
		$settings = $this->settings->get();
		foreach ([MailIntakeSettings::HOST, MailIntakeSettings::USERNAME, MailIntakeSettings::CREDENTIAL_REF] as $required) {
			if ($settings[$required] === '') {
				return self::failed(reason: self::INCOMPLETE);
			}
		}

		if ($this->connector === null) {
			return self::failed(reason: self::NO_CLIENT);
		}

		$password = $this->settings->password();
		if ($password === null) {
			return self::failed(reason: self::NO_PASSWORD);
		}

		try {
			$messages = $this->connector->connect(settings: $settings, password: $password)->count();
		} catch (Throwable $e) {
			$this->logger->warning('Planninq: the intake mailbox connection test failed', ['exception' => get_class($e)]);
			return self::failed(reason: self::CONNECT_FAILED);
		}

		return ['ok' => true, 'reason' => null, 'messages' => $messages];
	}//end run()

	/**
	 * A failed test.
	 *
	 * @param string $reason Why.
	 *
	 * @return array{ok: bool, reason: string, messages: null}
	 */
	private static function failed(string $reason): array {
		return ['ok' => false, 'reason' => $reason, 'messages' => null];
	}//end failed()
}//end class
