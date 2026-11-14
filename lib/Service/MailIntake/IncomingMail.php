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
	 * The Message-ID header.
	 *
	 * @var string
	 */
	public readonly string $messageId;

	/**
	 * The sender's address.
	 *
	 * @var string
	 */
	public readonly string $from;

	/**
	 * The To addresses.
	 *
	 * @var array<int,string>
	 */
	public readonly array $to;

	/**
	 * The Cc addresses.
	 *
	 * @var array<int,string>
	 */
	public readonly array $cc;

	/**
	 * The decoded subject.
	 *
	 * @var string
	 */
	public readonly string $subject;

	/**
	 * The plain-text body, empty when there is none.
	 *
	 * @var string
	 */
	public readonly string $textBody;

	/**
	 * The HTML body, empty when there is none.
	 *
	 * @var string
	 */
	public readonly string $htmlBody;

	/**
	 * The receiving server's Authentication-Results header.
	 *
	 * @var string|null
	 */
	public readonly ?string $authenticationResults;

	/**
	 * The attachments.
	 *
	 * @var array<int,array{name:string,mimeType:string,content:string}>
	 */
	public readonly array $attachments;

	/**
	 * @param string              $uid     The message's id in its mailbox folder.
	 * @param array<string,mixed> $message The message's parts by property name: `messageId`, `from`,
	 *                                     `to`, `cc`, `subject`, `textBody`, `htmlBody`,
	 *                                     `authenticationResults` and `attachments`; a missing one is empty.
	 */
	public function __construct(
		public readonly string $uid,
		array $message = [],
	) {
		$this->messageId = (string)($message['messageId'] ?? '');
		$this->from = (string)($message['from'] ?? '');
		$this->to = self::listOf(value: ($message['to'] ?? []));
		$this->cc = self::listOf(value: ($message['cc'] ?? []));
		$this->subject = (string)($message['subject'] ?? '');
		$this->textBody = (string)($message['textBody'] ?? '');
		$this->htmlBody = (string)($message['htmlBody'] ?? '');
		$this->authenticationResults = self::optional(value: ($message['authenticationResults'] ?? null));
		$this->attachments = self::listOf(value: ($message['attachments'] ?? []));
	}//end __construct()

	/**
	 * A list part, or an empty list.
	 *
	 * @param mixed $value The part.
	 *
	 * @return array<mixed>
	 */
	private static function listOf(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		return $value;
	}//end listOf()

	/**
	 * An optional string part.
	 *
	 * @param mixed $value The part.
	 *
	 * @return string|null
	 */
	private static function optional(mixed $value): ?string {
		if ($value === null) {
			return null;
		}

		return (string)$value;
	}//end optional()
}//end class
