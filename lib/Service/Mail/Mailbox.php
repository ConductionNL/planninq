<?php

/**
 * Planninq Mailbox
 *
 * The mailbox operations task intake needs, so the job and the service never
 * depend on one IMAP client.
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
 * A mailbox that can be read and filed.
 */
interface Mailbox {

	/**
	 * Log in and open the intake folder.
	 *
	 * @return int The number of messages in the folder.
	 *
	 * @throws \RuntimeException When the server refuses the connection or the login.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function open(): int;

	/**
	 * The unseen messages, oldest first, at most $limit.
	 *
	 * @param int $limit The most messages to return.
	 *
	 * @return array<int,IncomingMail>
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function unseen(int $limit): array;

	/**
	 * Move a message to another folder, creating the folder when needed.
	 *
	 * @param string $uid    The message id from IncomingMail::$uid.
	 * @param string $folder The target folder.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function moveTo(string $uid, string $folder): void;

	/**
	 * Log out and close the connection.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void;
}//end interface
