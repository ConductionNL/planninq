<?php

/**
 * Unit tests for MimeMessageParser: a raw message as the intake reads it.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service\MailIntake;

use OCA\Planninq\Service\MailIntake\MimeMessageParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\MailIntake\MimeMessageParser
 * @uses   \OCA\Planninq\Service\MailIntake\IncomingMail
 */
class MimeMessageParserTest extends TestCase {

	/**
	 * A plain message: decoded headers, lower-cased addresses, the body as text.
	 *
	 * @return void
	 */
	public function testAPlainMessage(): void {
		$raw = "Return-Path: <anna@gemeente.nl>\r\n"
			."Authentication-Results: mx.gemeente.nl;\r\n dkim=pass header.d=gemeente.nl;\r\n spf=pass\r\n"
			."Authentication-Results: relay.example; dkim=fail\r\n"
			."Message-ID: <CA+abc@mail.gemeente.nl>\r\n"
			."From: \"de Vries, Anna\" <Anna.de.Vries@Gemeente.NL>\r\n"
			."To: planninq+VC@gemeente.nl, \"Jan\" <jan@gemeente.nl>\r\n"
			."Cc: eva@gemeente.nl\r\n"
			."Subject: =?UTF-8?Q?Printer_2e_verdieping_kapot_=E2=80=93?=\r\n =?UTF-8?B?IGRyaW5nZW5k?=\r\n"
			."\r\n"
			."Hij print niets meer.\r\nGraag snel.\r\n";

		$mail = (new MimeMessageParser())->parse(uid: '17', raw: $raw);

		$this->assertSame(expected: '17', actual: $mail->uid);
		$this->assertSame(expected: '<CA+abc@mail.gemeente.nl>', actual: $mail->messageId);
		$this->assertSame(expected: 'anna.de.vries@gemeente.nl', actual: $mail->from);
		$this->assertSame(expected: ['planninq+vc@gemeente.nl', 'jan@gemeente.nl'], actual: $mail->to);
		$this->assertSame(expected: ['eva@gemeente.nl'], actual: $mail->cc);
		$this->assertSame(expected: 'Printer 2e verdieping kapot – dringend', actual: $mail->subject);
		$this->assertSame(expected: "Hij print niets meer.\nGraag snel.\n", actual: $mail->textBody);
		$this->assertSame(expected: '', actual: $mail->htmlBody);
		$this->assertSame(
			expected: 'mx.gemeente.nl; dkim=pass header.d=gemeente.nl; spf=pass',
			actual: $mail->authenticationResults,
			message: 'the topmost header is the receiving server\'s'
		);
		$this->assertSame(expected: [], actual: $mail->attachments);
	}//end testAPlainMessage()

	/**
	 * A multipart message: the text and HTML alternatives, a quoted-printable
	 * Latin-1 body in UTF-8, and a base64 photo with an RFC 2231 file name.
	 *
	 * @return void
	 */
	public function testAMultipartMessageWithAPhoto(): void {
		$photo = "\x89PNG\r\n\x1a\nfake-image-bytes";
		$raw   = "From: anna@gemeente.nl\r\nSubject: Foto\r\nMIME-Version: 1.0\r\n"
			."Content-Type: multipart/mixed; boundary=\"outer\"\r\n\r\n"
			."This is a multi-part message.\r\n"
			."--outer\r\n"
			."Content-Type: multipart/alternative; boundary=inner\r\n\r\n"
			."--inner\r\n"
			."Content-Type: text/plain; charset=ISO-8859-1\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
			."Caf=E9 op de 2e verdieping, zie foto.\r\n"
			."--inner\r\n"
			."Content-Type: text/html; charset=utf-8\r\n\r\n"
			."<p>Café op de 2e verdieping</p>\r\n"
			."--inner--\r\n"
			."--outer\r\n"
			."Content-Type: image/png\r\nContent-Transfer-Encoding: base64\r\n"
			."Content-Disposition: attachment; filename*=UTF-8''printer%20kapot%20%E2%80%93.png\r\n\r\n"
			.chunk_split(base64_encode($photo), 8, "\r\n")
			."--outer--\r\n"
			."epilogue\r\n";

		$mail = (new MimeMessageParser())->parse(uid: '3', raw: $raw);

		$this->assertSame(expected: "Café op de 2e verdieping, zie foto.", actual: $mail->textBody);
		$this->assertSame(expected: '<p>Café op de 2e verdieping</p>', actual: $mail->htmlBody);
		$this->assertSame(expected: [['name' => 'printer kapot –.png', 'mimeType' => 'image/png', 'content' => $photo]], actual: $mail->attachments);
	}//end testAMultipartMessageWithAPhoto()

	/**
	 * A text part with a file name is an attachment, and a second text body is too;
	 * a path in a file name is dropped and a nameless part gets a name.
	 *
	 * @return void
	 */
	public function testNamedTextAndNamelessPartsAreAttachments(): void {
		$raw = "From: anna@gemeente.nl\r\nContent-Type: multipart/mixed; boundary=b\r\n\r\n"
			."--b\r\nContent-Type: text/plain\r\n\r\nThe body.\r\n"
			."--b\r\nContent-Type: text/plain; name=\"..\\\\..\\\\notes.txt\"\r\n\r\nNotes.\r\n"
			."--b\r\nContent-Type: application/octet-stream\r\n\r\nraw\r\n"
			."--b\r\nContent-Type: message/rfc822\r\n\r\nSubject: inner\r\n\r\nInner.\r\n"
			."--b--\r\n";

		$mail = (new MimeMessageParser())->parse(uid: '4', raw: $raw);

		$this->assertSame(expected: 'The body.', actual: $mail->textBody);
		$this->assertSame(
			expected: [
				['name' => 'notes.txt', 'mimeType' => 'text/plain', 'content' => 'Notes.'],
				['name' => 'attachment-2', 'mimeType' => 'application/octet-stream', 'content' => 'raw'],
				['name' => 'message.eml', 'mimeType' => 'message/rfc822', 'content' => "Subject: inner\r\n\r\nInner."],
			],
			actual: $mail->attachments
		);
	}//end testNamedTextAndNamelessPartsAreAttachments()

	/**
	 * Only the headers: everything the headers say, empty bodies.
	 *
	 * @return void
	 */
	public function testHeadersOnly(): void {
		$mail = (new MimeMessageParser())->parse(uid: '9', raw: "From: anna@gemeente.nl\r\nSubject: Huge\r\n\r\n");

		$this->assertSame(expected: 'Huge', actual: $mail->subject);
		$this->assertSame(expected: 'anna@gemeente.nl', actual: $mail->from);
		$this->assertSame(expected: '', actual: $mail->textBody);
		$this->assertNull(actual: $mail->authenticationResults);
	}//end testHeadersOnly()

	/**
	 * Addresses: commas and @ signs inside quoted names and comments are not addresses.
	 *
	 * @return void
	 */
	public function testAddressesIgnoreQuotedNamesAndComments(): void {
		$this->assertSame(
			expected: ['anna@gemeente.nl', 'jan@gemeente.nl', 'eva@gemeente.nl'],
			actual: MimeMessageParser::addresses(
				value: '"Vries, A. (anna@old.nl)" <ANNA@gemeente.nl>, jan@gemeente.nl (Jan, team), Eva <eva@gemeente.nl>, anna@gemeente.nl'
			)
		);
		$this->assertSame(expected: [], actual: MimeMessageParser::addresses(value: 'undisclosed-recipients:;'));
	}//end testAddressesIgnoreQuotedNamesAndComments()

	/**
	 * Encoded words in any known charset end up as UTF-8; invalid bytes are dropped.
	 *
	 * @return void
	 */
	public function testHeadersDecodeToUtf8(): void {
		$this->assertSame(expected: 'Één taak', actual: MimeMessageParser::decodeHeader(value: '=?ISO-8859-1?Q?=C9=E9n_taak?='));
		$this->assertSame(expected: 'plain', actual: MimeMessageParser::decodeHeader(value: '  plain '));
		$this->assertTrue(condition: mb_check_encoding(MimeMessageParser::decodeHeader(value: "bad \xff byte"), 'UTF-8'));
	}//end testHeadersDecodeToUtf8()
}//end class
