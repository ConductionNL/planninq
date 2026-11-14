<?php

/**
 * Planninq Incoming Mail
 *
 * One message read from the intake mailbox.
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
 * A message as the intake reads it: addresses lower-cased, the bodies decoded,
 * the attachments with their content.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
final class IncomingMail {

	/**
	 * @param string $uid The message's id in its mailbox folder.
	 * @param string $messageId The Message-ID header.
	 * @param string $from The sender's address.
	 * @param array<int,string> $to The To addresses.
	 * @param array<int,string> $cc The Cc addresses.
	 * @param string $subject The decoded subject.
	 * @param string $textBody The plain-text body, empty when there is none.
	 * @param string $htmlBody The HTML body, empty when there is none.
	 * @param string|null $authenticationResults The receiving server's Authentication-Results header.
	 * @param array<int,array{name:string,mimeType:string,content:string}> $attachments The attachments.
	 */
	public function __construct(
		public readonly string $uid,
		public readonly string $messageId,
		public readonly string $from,
		public readonly array $to = [],
		public readonly array $cc = [],
		public readonly string $subject = '',
		public readonly string $textBody = '',
		public readonly string $htmlBody = '',
		public readonly ?string $authenticationResults = null,
		public readonly array $attachments = [],
	) {
	}//end __construct()
}//end class
