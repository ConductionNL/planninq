<?php

/**
 * Unit tests for ImapClient: the intake mailbox over IMAP, against a scripted server.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ImapClient;
use OCA\Planninq\Service\MailIntake\ImapTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\ImapClient
 * @uses   \OCA\Planninq\Service\MailIntake\MimeMessageParser
 * @uses   \OCA\Planninq\Service\MailIntake\IncomingMail
 */
class ImapClientTest extends TestCase {

	/**
	 * The server's side of a session up to and including SELECT, with these capabilities.
	 */
	private const OPENED = "* OK IMAP4rev1 ready\r\n"
		."P0001 OK LOGIN completed\r\n"
		."* CAPABILITY IMAP4rev1 MOVE UIDPLUS\r\n"
		."P0002 OK CAPABILITY completed\r\n"
		."* 3 EXISTS\r\n* 0 RECENT\r\n"
		."P0003 OK [READ-WRITE] SELECT completed\r\n";

	/**
	 * A transport that plays back what the server says and records what the client sends.
	 *
	 * @param string $server Everything the server sends, in order.
	 *
	 * @return ImapTransport&object{sent: array<int,string>, events: array<int,string>, closed: bool}
	 */
	private function server(string $server): ImapTransport {
		return new class($server) implements ImapTransport {

			/**
			 * @var array<int,string>
			 */
			public array $sent = [];

			/**
			 * @var array<int,string>
			 */
			public array $events = [];

			/**
			 * @var bool
			 */
			public bool $closed = false;

			/**
			 * @param string $buffer What the server still has to send.
			 */
			public function __construct(private string $buffer) {
			}

			/**
			 * @param string $data The bytes.
			 */
			public function write(string $data): void {
				$this->sent[]   = $data;
				$this->events[] = 'write';
			}

			/**
			 * @return string
			 */
			public function readLine(): string {
				$end = strpos($this->buffer, "\n");
				if ($end === false) {
					throw new RuntimeException('The scripted server has nothing more to say.');
				}

				$line         = substr($this->buffer, 0, ($end + 1));
				$this->buffer = substr($this->buffer, ($end + 1));
				return $line;
			}

			/**
			 * @param int $length How many bytes.
			 *
			 * @return string
			 */
			public function read(int $length): string {
				$data         = substr($this->buffer, 0, $length);
				$this->buffer = substr($this->buffer, $length);
				return $data;
			}

			/**
			 * @return void
			 */
			public function startTls(): void {
				$this->events[] = 'starttls';
			}

			/**
			 * @return void
			 */
			public function close(): void {
				$this->closed = true;
			}
		};
	}//end server()

	/**
	 * A message in raw form.
	 *
	 * @param string $subject The subject.
	 *
	 * @return string
	 */
	private static function message(string $subject): string {
		return "Message-ID: <{$subject}@gemeente.nl>\r\nFrom: Anna <anna@gemeente.nl>\r\nTo: planninq+vc@gemeente.nl\r\n"
			."Subject: {$subject}\r\n\r\nThe body of {$subject}.\r\n";
	}//end message()

	/**
	 * The server's two answers to fetching one message: its size, then its content.
	 *
	 * @param int    $sequence The sequence number.
	 * @param int    $uid      The UID.
	 * @param string $raw      The message.
	 * @param int    $tag      The tag number of the size fetch.
	 *
	 * @return string
	 */
	private static function fetched(int $sequence, int $uid, string $raw, int $tag): string {
		return "* {$sequence} FETCH (UID {$uid} RFC822.SIZE ".strlen($raw).")\r\n"
			.sprintf("P%04d OK FETCH completed\r\n", $tag)
			."* {$sequence} FETCH (UID {$uid} BODY[] {".strlen($raw)."}\r\n".$raw.")\r\n"
			.sprintf("P%04d OK FETCH completed\r\n", ($tag + 1));
	}//end fetched()

	/**
	 * Opening greets, logs in, asks the capabilities and selects the folder, in that order.
	 *
	 * @return void
	 */
	public function testOpenLogsInAndSelectsTheFolder(): void {
		$server = $this->server(self::OPENED."* 4 EXISTS\r\nP0004 OK NOOP completed\r\n");

		$client = ImapClient::open(transport: $server, username: 'intake', password: 'gehe"im', folder: 'INBOX', startTls: false);

		$this->assertSame(
			["P0001 LOGIN \"intake\" \"gehe\\\"im\"\r\n", "P0002 CAPABILITY\r\n", "P0003 SELECT INBOX\r\n"],
			$server->sent
		);
		$this->assertSame(4, $client->count(), 'count() asks the server again and reads the new EXISTS');
		$this->assertSame("P0004 NOOP\r\n", $server->sent[3]);
	}//end testOpenLogsInAndSelectsTheFolder()

	/**
	 * With STARTTLS, the connection is upgraded before the password is sent.
	 *
	 * @return void
	 */
	public function testStartTlsUpgradesBeforeTheLogin(): void {
		$script = "* OK ready\r\nP0001 OK Begin TLS\r\n"
			."P0002 OK LOGIN completed\r\n* CAPABILITY IMAP4rev1\r\nP0003 OK done\r\n* 0 EXISTS\r\nP0004 OK done\r\n";
		$server = $this->server($script);

		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: true);

		$this->assertInstanceOf(ImapClient::class, $client);
		$this->assertSame("P0001 STARTTLS\r\n", $server->sent[0]);
		$this->assertSame(['write', 'starttls', 'write', 'write', 'write'], $server->events);
		$this->assertStringStartsWith('P0002 LOGIN ', $server->sent[1]);
	}//end testStartTlsUpgradesBeforeTheLogin()

	/**
	 * A refused login throws a message that does not repeat the password, and closes the connection.
	 *
	 * @return void
	 */
	public function testARefusedLoginThrowsWithoutThePassword(): void {
		$server = $this->server("* OK ready\r\nP0001 NO [AUTHENTICATIONFAILED] Invalid credentials\r\n");

		try {
			ImapClient::open(transport: $server, username: 'intake', password: 's3cret-pw', folder: 'INBOX', startTls: false);
			$this->fail('A refused login must throw.');
		} catch (RuntimeException $e) {
			$this->assertSame('The mail server refused the login.', $e->getMessage());
			$this->assertStringNotContainsString('s3cret-pw', $e->getMessage());
		}

		$this->assertTrue($server->closed);
	}//end testARefusedLoginThrowsWithoutThePassword()

	/**
	 * A server that does not greet with OK is refused before anything is sent.
	 *
	 * @return void
	 */
	public function testAGreetingOtherThanOkIsRefused(): void {
		$server = $this->server("* BYE too many connections\r\n");

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The mail server refused the connection.');
		try {
			ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);
		} finally {
			$this->assertSame([], $server->sent);
			$this->assertTrue($server->closed);
		}
	}//end testAGreetingOtherThanOkIsRefused()

	/**
	 * A password that cannot be quoted goes as a literal, after the server's go-ahead.
	 *
	 * @return void
	 */
	public function testAPasswordThatCannotBeQuotedIsSentAsALiteral(): void {
		$password = 'wachtwoord€';
		$server   = $this->server("* OK ready\r\n+ go ahead\r\nP0001 OK LOGIN completed\r\n* CAPABILITY IMAP4rev1\r\nP0002 OK\r\n* 0 EXISTS\r\nP0003 OK\r\n");

		ImapClient::open(transport: $server, username: 'intake', password: $password, folder: 'INBOX', startTls: false);

		$this->assertSame('P0001 LOGIN "intake" {'.strlen($password)."}\r\n", $server->sent[0]);
		$this->assertSame($password."\r\n", $server->sent[1]);
	}//end testAPasswordThatCannotBeQuotedIsSentAsALiteral()

	/**
	 * Unseen messages are read oldest first, up to the limit, without marking them seen.
	 *
	 * @return void
	 */
	public function testUnseenReadsTheOldestUpToTheLimitWithoutMarkingThemSeen(): void {
		$script = self::OPENED."* SEARCH 9 3 5\r\nP0004 OK SEARCH completed\r\n"
			.self::fetched(sequence: 1, uid: 3, raw: self::message(subject: 'First'), tag: 5)
			.self::fetched(sequence: 2, uid: 5, raw: self::message(subject: 'Second'), tag: 7);
		$server = $this->server($script);
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$mails = $client->unseen(limit: 2);

		$this->assertSame(['3', '5'], array_map(static fn ($mail): string => $mail->uid, $mails));
		$this->assertSame(['First', 'Second'], array_map(static fn ($mail): string => $mail->subject, $mails));
		$this->assertSame('anna@gemeente.nl', $mails[0]->from);
		$this->assertSame("The body of First.\n", $mails[0]->textBody);
		$this->assertSame(
			["P0004 UID SEARCH UNSEEN\r\n", "P0005 UID FETCH 3 (UID RFC822.SIZE)\r\n", "P0006 UID FETCH 3 (UID BODY.PEEK[])\r\n", "P0007 UID FETCH 5 (UID RFC822.SIZE)\r\n", "P0008 UID FETCH 5 (UID BODY.PEEK[])\r\n"],
			array_slice($server->sent, 3)
		);
	}//end testUnseenReadsTheOldestUpToTheLimitWithoutMarkingThemSeen()

	/**
	 * A message larger than the limit is read as its headers only.
	 *
	 * @return void
	 */
	public function testAnOversizedMessageIsReadAsItsHeadersOnly(): void {
		$headers = "From: anna@gemeente.nl\r\nSubject: Huge\r\n\r\n";
		$script  = self::OPENED."* SEARCH 4\r\nP0004 OK\r\n"
			.'* 1 FETCH (UID 4 RFC822.SIZE '.(ImapClient::MAX_MESSAGE_BYTES + 1).")\r\nP0005 OK\r\n"
			.'* 1 FETCH (UID 4 BODY[HEADER] {'.strlen($headers)."}\r\n".$headers.")\r\nP0006 OK\r\n";
		$server = $this->server($script);
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$mails = $client->unseen(limit: 50);

		$this->assertSame("P0006 UID FETCH 4 (UID BODY.PEEK[HEADER])\r\n", $server->sent[5]);
		$this->assertSame('Huge', $mails[0]->subject);
		$this->assertSame('', $mails[0]->textBody);
	}//end testAnOversizedMessageIsReadAsItsHeadersOnly()

	/**
	 * No unseen messages, no fetch.
	 *
	 * @return void
	 */
	public function testNoUnseenMessagesFetchNothing(): void {
		$server = $this->server(self::OPENED."* SEARCH\r\nP0004 OK\r\n");
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$this->assertSame([], $client->unseen(limit: 50));
		$this->assertCount(4, $server->sent);
	}//end testNoUnseenMessagesFetchNothing()

	/**
	 * With MOVE offered, a message moves in one command, after the folder is made (or found).
	 *
	 * @return void
	 */
	public function testMoveUsesMoveWhenTheServerOffersIt(): void {
		$server = $this->server(self::OPENED."P0004 NO [ALREADYEXISTS] Mailbox exists\r\nP0005 OK MOVE completed\r\n");
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$client->move(uid: '12', folder: 'Processed');

		$this->assertSame(["P0004 CREATE \"Processed\"\r\n", "P0005 UID MOVE 12 \"Processed\"\r\n"], array_slice($server->sent, 3));
	}//end testMoveUsesMoveWhenTheServerOffersIt()

	/**
	 * Without MOVE or UIDPLUS, a message is copied, flagged deleted and expunged.
	 *
	 * @return void
	 */
	public function testMoveFallsBackToCopyDeleteAndExpunge(): void {
		$script = "* OK ready\r\nP0001 OK\r\n* CAPABILITY IMAP4rev1\r\nP0002 OK\r\n* 1 EXISTS\r\nP0003 OK\r\n"
			."P0004 OK CREATE completed\r\nP0005 OK COPY\r\nP0006 OK STORE\r\n* 1 EXPUNGE\r\nP0007 OK EXPUNGE\r\n";
		$server = $this->server($script);
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$client->move(uid: '7', folder: 'Rejected');

		$this->assertSame(
			["P0004 CREATE \"Rejected\"\r\n", "P0005 UID COPY 7 \"Rejected\"\r\n", "P0006 UID STORE 7 +FLAGS.SILENT (\\Deleted)\r\n", "P0007 EXPUNGE\r\n"],
			array_slice($server->sent, 3)
		);
	}//end testMoveFallsBackToCopyDeleteAndExpunge()

	/**
	 * With UIDPLUS but no MOVE, only the moved message is expunged.
	 *
	 * @return void
	 */
	public function testMoveExpungesOnlyThatMessageWithUidPlus(): void {
		$script = "* OK ready\r\nP0001 OK\r\n* CAPABILITY IMAP4rev1 UIDPLUS\r\nP0002 OK\r\n* 1 EXISTS\r\nP0003 OK\r\n"
			."P0004 OK\r\nP0005 OK\r\nP0006 OK\r\nP0007 OK\r\n";
		$server = $this->server($script);
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$client->move(uid: '7', folder: 'Processed');

		$this->assertSame("P0007 UID EXPUNGE 7\r\n", $server->sent[6]);
	}//end testMoveExpungesOnlyThatMessageWithUidPlus()

	/**
	 * A refused move throws.
	 *
	 * @return void
	 */
	public function testARefusedMoveThrows(): void {
		$server = $this->server(self::OPENED."P0004 OK\r\nP0005 NO [TRYCREATE] no such mailbox\r\n");
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The mail server refused a command.');
		$client->move(uid: '12', folder: 'Processed');
	}//end testARefusedMoveThrows()

	/**
	 * Anything but a UID is refused before a command is sent.
	 *
	 * @return void
	 */
	public function testMoveRefusesSomethingThatIsNotAUid(): void {
		$server = $this->server(self::OPENED);
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		try {
			$client->move(uid: '1:*', folder: 'Processed');
			$this->fail('A sequence set is not a UID.');
		} catch (RuntimeException $e) {
			$this->assertSame('Not a message UID.', $e->getMessage());
		}

		$this->assertCount(3, $server->sent);
	}//end testMoveRefusesSomethingThatIsNotAUid()

	/**
	 * A folder name outside ASCII is sent in modified UTF-7, and & is escaped.
	 *
	 * @return void
	 */
	public function testFolderNamesAreSentInModifiedUtf7(): void {
		$server = $this->server("* OK ready\r\nP0001 OK\r\n* CAPABILITY IMAP4rev1 MOVE\r\nP0002 OK\r\n* 0 EXISTS\r\nP0003 OK\r\nP0004 OK\r\nP0005 OK\r\n");
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'Taken & meldingen', startTls: false);
		$client->move(uid: '2', folder: 'Afgehandeld é');

		$this->assertSame("P0003 SELECT \"Taken &- meldingen\"\r\n", $server->sent[2]);
		$this->assertSame("P0004 CREATE \"Afgehandeld &AOk-\"\r\n", $server->sent[3]);
	}//end testFolderNamesAreSentInModifiedUtf7()

	/**
	 * Closing logs out once and closes the connection.
	 *
	 * @return void
	 */
	public function testCloseLogsOutOnce(): void {
		$server = $this->server(self::OPENED."* BYE logging out\r\nP0004 OK LOGOUT completed\r\n");
		$client = ImapClient::open(transport: $server, username: 'intake', password: 'pw', folder: 'INBOX', startTls: false);

		$client->close();
		$client->close();

		$this->assertSame("P0004 LOGOUT\r\n", $server->sent[3]);
		$this->assertCount(4, $server->sent);
		$this->assertTrue($server->closed);
	}//end testCloseLogsOutOnce()
}//end class
