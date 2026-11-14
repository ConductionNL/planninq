<?php

/**
 * Planninq Mailbox Client
 *
 * The intake mailbox, as the intake job reads and files it.
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

/**
 * One open connection to the intake mailbox's folder.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
interface MailboxClient {

	/**
	 * The unseen messages of the folder, oldest first.
	 *
	 * @param int $limit At most this many.
	 *
	 * @return array<int,IncomingMail>
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function unseen(int $limit): array;

	/**
	 * Move a message to another folder, creating it when needed.
	 *
	 * @param string $uid    The message's id in the folder.
	 * @param string $folder The folder to move it to.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function move(string $uid, string $folder): void;

	/**
	 * How many messages the folder holds.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-an-admin-connects-a-mailbox-for-task-intake
	 */
	public function count(): int;
}//end interface
