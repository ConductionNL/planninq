<?php

/**
 * Planninq IMAP Transport
 *
 * The byte stream an IMAP session runs over.
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

use SensitiveParameter;

/**
 * A connection to an IMAP server: lines and counted bytes in, raw bytes out.
 * The socket implementation talks to a real server; a test hands the client
 * a scripted one.
 *
 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
 */
interface ImapTransport {

	/**
	 * Send bytes as they are.
	 *
	 * @param string $data The bytes, line endings included.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When the connection is gone.
	 *
	 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
	 */
	public function write(#[SensitiveParameter] string $data): void;

	/**
	 * Read one line, its CRLF included.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the connection is gone or times out.
	 *
	 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
	 */
	public function readLine(): string;

	/**
	 * Read exactly this many bytes (an IMAP literal).
	 *
	 * @param int $length How many bytes.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the connection is gone or times out.
	 *
	 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
	 */
	public function read(int $length): string;

	/**
	 * Switch the open connection to TLS (STARTTLS).
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When the handshake fails.
	 *
	 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
	 */
	public function startTls(): void;

	/**
	 * Close the connection; closing twice does nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void;
}//end interface
