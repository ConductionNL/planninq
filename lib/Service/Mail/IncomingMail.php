<?php

/**
 * Planninq IncomingMail
 *
 * One message read from the intake mailbox, reduced to what task intake needs.
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\Mail;

/**
 * A parsed message.
 */
class IncomingMail {

	/**
	 * Constructor.
	 *
	 * @param string                                                         $uid         The mailbox id the message is filed by.
	 * @param string                                                         $messageId   The Message-ID header, without angle brackets.
	 * @param string                                                         $from        The sender's email address, lower case.
	 * @param array<int,string>                                              $recipients  Every To and Cc address, lower case.
	 * @param string                                                         $subject     The decoded subject.
	 * @param string                                                         $body        The plain-text body.
	 * @param string                                                         $authResults The Authentication-Results header, or ''.
	 * @param array<int,array{name:string,size:int,content:string}>          $attachments The attached files.
	 */
	public function __construct(
		public readonly string $uid,
		public readonly string $messageId,
		public readonly string $from,
		public readonly array $recipients,
		public readonly string $subject,
		public readonly string $body,
		public readonly string $authResults = '',
		public readonly array $attachments = [],
	) {
	}//end __construct()
}//end class
