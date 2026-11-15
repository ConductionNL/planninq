<?php

/**
 * Planninq Mailbox Connector
 *
 * Opens the intake mailbox.
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
 * Opens a {@see MailboxClient} on the configured mailbox.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
interface MailboxConnector {

	/**
	 * Log in and open the folder.
	 *
	 * @param array<string,string> $settings The `mail_intake_*` settings.
	 * @param string               $password The resolved password.
	 *
	 * @return MailboxClient
	 *
	 * @throws \RuntimeException When the mailbox cannot be reached or refuses the login.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function connect(array $settings, #[\SensitiveParameter] string $password): MailboxClient;
}//end interface
