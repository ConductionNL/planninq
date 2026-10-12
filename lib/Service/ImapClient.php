<?php

/**
 * Planninq ImapClient
 *
 * A small IMAP4rev1 client for the intake mailbox: login, select, find unseen
 * messages, fetch them whole and move them between folders. It talks through
 * an ImapTransport, so tests replace the socket.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\Service\Mail\ImapTransport;
use OCA\Planninq\Service\Mail\IncomingMail;
use OCA\Planninq\Service\Mail\Mailbox;
use OCA\Planninq\Service\Mail\MimeParser;
use RuntimeException;

/**
 * IMAP mailbox over an ImapTransport.
 */
class ImapClient implements Mailbox {

	/**
	 * The number of the next command tag.
	 *
	 * @var int
	 */
	private int $tag = 0;

	/**
	 * Whether the server greeting was read.
	 *
	 * @var bool
	 */
	private bool $greeted = false;

	/**
	 * Constructor.
	 *
	 * @param ImapTransport $transport The connection.
	 * @param string        $username  The login name.
	 * @param string        $password  The password, resolved at run time and never stored.
	 * @param string        $folder    The folder to read.
	 * @param MimeParser    $parser    Reads fetched messages.
	 */
	public function __construct(
		private ImapTransport $transport,
		private string $username,
		private string $password,
		private string $folder = 'INBOX',
		private MimeParser $parser = new MimeParser(),
	) {
	}//end __construct()

	/**
	 * Log in and open the intake folder.
	 *
	 * @return int The number of messages in the folder.
	 *
	 * @throws RuntimeException When the server refuses the connection or the login.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function open(): int {
		if ($this->greeted === false) {
			$greeting = $this->transport->readLine();
			if (str_starts_with($greeting, '* OK') === false && str_starts_with($greeting, '* PREAUTH') === false) {
				throw new RuntimeException('The mail server did not accept the connection.');
			}

			$this->greeted = true;
		}

		$this->command(line: 'LOGIN ' . $this->quote(value: $this->username) . ' ' . $this->quote(value: $this->password));
		$lines = $this->command(line: 'SELECT ' . $this->quote(value: $this->folder));
		foreach ($lines as $line) {
			if (preg_match('/^\* (\d+) EXISTS/', $line, $found) === 1) {
				return (int)$found[1];
			}
		}

		return 0;
	}//end open()

	/**
	 * The unseen messages, oldest first, at most $limit.
	 *
	 * @param int $limit The most messages to return.
	 *
	 * @return array<int,IncomingMail>
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function unseen(int $limit): array {
		$found = [];
		foreach ($this->command(line: 'UID SEARCH UNSEEN') as $line) {
			if (str_starts_with($line, '* SEARCH') === true) {
				$found = array_filter(explode(' ', trim(substr($line, 8))), static fn (string $uid): bool => ctype_digit($uid));
			}
		}

		$mails = [];
		foreach (array_slice(array_values($found), 0, max(0, $limit)) as $uid) {
			$raw = $this->fetch(uid: (string)$uid);
			if ($raw !== null) {
				$mails[] = $this->parser->parse(uid: (string)$uid, raw: $raw);
			}
		}

		return $mails;
	}//end unseen()

	/**
	 * Move a message to another folder, creating the folder when needed.
	 *
	 * @param string $uid    The message uid.
	 * @param string $folder The target folder.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function moveTo(string $uid, string $folder): void {
		try {
			$this->command(line: 'CREATE ' . $this->quote(value: $folder));
		} catch (RuntimeException) {
			// The folder exists already.
		}

		$this->command(line: 'UID COPY ' . (int)$uid . ' ' . $this->quote(value: $folder));
		$this->command(line: 'UID STORE ' . (int)$uid . ' +FLAGS.SILENT (\\Seen \\Deleted)');
		$this->command(line: 'EXPUNGE');
	}//end moveTo()

	/**
	 * Log out and close the connection.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void {
		try {
			$this->command(line: 'LOGOUT');
		} catch (RuntimeException) {
			// The server may drop the line on logout.
		}

		$this->transport->close();
	}//end close()

	/**
	 * The raw bytes of one message, read without setting its seen flag.
	 *
	 * @param string $uid The message uid.
	 *
	 * @return string|null Null when the server returned no body.
	 */
	private function fetch(string $uid): ?string {
		$raw = null;
		$this->command(line: 'UID FETCH ' . (int)$uid . ' BODY.PEEK[]', literal: $raw);

		return $raw;
	}//end fetch()

	/**
	 * Send a command and read its untagged lines up to the tagged result.
	 *
	 * @param string      $line    The command without its tag.
	 * @param string|null $literal Receives the first counted block of the response.
	 *
	 * @return array<int,string> The untagged lines.
	 *
	 * @throws RuntimeException When the server answers NO or BAD.
	 */
	private function command(string $line, ?string &$literal = null): array {
		$tag = 'A' . (++$this->tag);
		$this->transport->writeLine($tag . ' ' . $line);
		$lines = [];
		while (true) {
			$reply = $this->transport->readLine();
			if (str_starts_with($reply, $tag . ' ') === true) {
				if (str_starts_with($reply, $tag . ' OK') === false) {
					throw new RuntimeException('The mail server refused ' . strtok($line, ' ') . ': ' . trim(substr($reply, strlen($tag))));
				}

				return $lines;
			}

			if (preg_match('/\{(\d+)\}$/', $reply, $found) === 1) {
				$block = $this->transport->readBytes((int)$found[1]);
				if ($literal === null) {
					$literal = $block;
				}

				// The closing parenthesis of the FETCH follows the block on its own line.
				$reply = $this->transport->readLine();
			}

			$lines[] = $reply;
		}
	}//end command()

	/**
	 * A quoted IMAP string.
	 *
	 * @param string $value The raw value.
	 *
	 * @return string
	 */
	private function quote(string $value): string {
		return '"' . addcslashes(str_replace(["\r", "\n"], '', $value), '"\\') . '"';
	}//end quote()
}//end class
