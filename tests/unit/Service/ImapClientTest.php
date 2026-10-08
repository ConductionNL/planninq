<?php

/**
 * Unit tests for ImapClient and the MIME parser, over a scripted transport.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/FakeImapTransport.php';

use OCA\Planninq\Service\ImapClient;
use OCA\Planninq\Tests\Unit\Support\FakeImapTransport;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Service\ImapClient
 * @covers \OCA\Planninq\Service\Mail\MimeParser
 */
class ImapClientTest extends TestCase {

	/**
	 * A multipart message with a plain body and a photo.
	 *
	 * @return string
	 */
	private function raw(): string {
		return "Message-ID: <abc@mail>\r\nAuthentication-Results: mx; dkim=pass; spf=pass\r\n"
			. "From: Anna <Anna@Gemeente.nl>\r\nTo: planninq+VC@gemeente.nl\r\nCc: x@y.nl\r\n"
			. "Subject: =?UTF-8?Q?Printer_2e_verdieping_kapot_=E2=9C=93?=\r\n"
			. "Content-Type: multipart/mixed; boundary=\"B1\"\r\n\r\n"
			. "--B1\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nDe printer geeft een fout=2E\r\n"
			. "--B1\r\nContent-Type: image/png; name=\"foto.png\"\r\nContent-Disposition: attachment; filename=\"foto.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
			. base64_encode('PNGDATA') . "\r\n--B1--\r\n";
	}//end raw()

	/**
	 * Login, select, search, fetch and parse one unseen message.
	 *
	 * @return void
	 */
	public function testReadsAndParsesAnUnseenMessage(): void {
		$raw       = $this->raw();
		$transport = new FakeImapTransport(
			replies: [
				'SELECT' => ['* 3 EXISTS'],
				'UID SEARCH' => ['* SEARCH 7 9'],
				'UID FETCH' => ['* 1 FETCH (UID 7 BODY[] {' . strlen($raw) . '}', ['bytes' => $raw], ')'],
			]
		);
		$client = new ImapClient(transport: $transport, username: 'planninq', password: 'pa"ss', folder: 'INBOX');

		self::assertSame(expected: 3, actual: $client->open());
		$mails = $client->unseen(limit: 1);

		self::assertCount(expectedCount: 1, haystack: $mails);
		self::assertSame(expected: '7', actual: $mails[0]->uid);
		self::assertSame(expected: 'abc@mail', actual: $mails[0]->messageId);
		self::assertSame(expected: 'anna@gemeente.nl', actual: $mails[0]->from);
		self::assertSame(expected: ['planninq+vc@gemeente.nl', 'x@y.nl'], actual: $mails[0]->recipients);
		self::assertSame(expected: "Printer 2e verdieping kapot \u{2713}", actual: $mails[0]->subject);
		self::assertSame(expected: 'De printer geeft een fout.', actual: $mails[0]->body);
		self::assertSame(expected: [['name' => 'foto.png', 'size' => 7, 'content' => 'PNGDATA']], actual: $mails[0]->attachments);
		self::assertStringContainsString('dkim=pass', $mails[0]->authResults);
		self::assertSame(expected: 'LOGIN "planninq" "pa\"ss"', actual: $transport->commands[0]);
		self::assertContains('UID FETCH 7 BODY.PEEK[]', $transport->commands);
		self::assertNotContains('UID FETCH 9 BODY.PEEK[]', $transport->commands);

	}//end testReadsAndParsesAnUnseenMessage()

	/**
	 * Moving a message creates the folder, copies, flags deleted and expunges; a folder that exists is fine.
	 *
	 * @return void
	 */
	public function testMoveFilesTheMessageAndToleratesAnExistingFolder(): void {
		$transport = new FakeImapTransport(refuse: ['CREATE' => 'NO [ALREADYEXISTS] exists']);
		$client    = new ImapClient(transport: $transport, username: 'u', password: 'p');
		$client->open();
		$client->moveTo('7', 'Processed');
		$client->close();

		self::assertSame(
			expected: ['CREATE "Processed"', 'UID COPY 7 "Processed"', 'UID STORE 7 +FLAGS.SILENT (\\Seen \\Deleted)', 'EXPUNGE', 'LOGOUT'],
			actual: array_slice($transport->commands, 2)
		);
		self::assertTrue($transport->closed);

	}//end testMoveFilesTheMessageAndToleratesAnExistingFolder()

	/**
	 * A refused login surfaces as an exception that names the command.
	 *
	 * @return void
	 */
	public function testRefusedLoginThrows(): void {
		$client = new ImapClient(transport: new FakeImapTransport(refuse: ['LOGIN' => 'NO bad credentials']), username: 'u', password: 'p');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('LOGIN');
		$client->open();

	}//end testRefusedLoginThrows()
}//end class
