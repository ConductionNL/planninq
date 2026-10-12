<?php

/**
 * Planninq ImapTransport
 *
 * The byte pipe under the IMAP client: lines and counted blocks, nothing else.
 *
 * @category Service
 * @package  OCA\Planninq\Service\Mail
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

namespace OCA\Planninq\Service\Mail;

/**
 * A connection the IMAP client talks through.
 */
interface ImapTransport {

	/**
	 * Read one line, without its line ending.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the connection ends.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function readLine(): string;

	/**
	 * Read exactly $length bytes.
	 *
	 * @param int $length The byte count.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the connection ends first.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function readBytes(int $length): string;

	/**
	 * Write one line; the transport adds the line ending.
	 *
	 * @param string $line The line.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function writeLine(string $line): void;

	/**
	 * Close the connection.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void;
}//end interface
