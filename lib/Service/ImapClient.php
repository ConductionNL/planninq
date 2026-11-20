<?php

/**
 * Planninq IMAP Client
 *
 * The intake mailbox over IMAP4rev1.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\Service\MailIntake\ImapTransport;
use OCA\Planninq\Service\MailIntake\IncomingMail;
use OCA\Planninq\Service\MailIntake\MailboxClient;
use OCA\Planninq\Service\MailIntake\MimeMessageParser;
use RuntimeException;
use Throwable;

/**
 * One logged-in IMAP session on one folder: the few IMAP4rev1 commands the
 * intake needs (RFC 3501), with MOVE (RFC 6851) and UIDPLUS (RFC 4315) used
 * when the server offers them. Messages are read with BODY.PEEK, so a message
 * that fails to become a task stays unseen and is read again on the next run.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */
final class ImapClient implements MailboxClient {

	/**
	 * A message larger than this is read as its headers only, so one huge
	 * mail cannot exhaust the job's memory.
	 */
	public const MAX_MESSAGE_BYTES = 52428800;

	/**
	 * The next command tag's number.
	 *
	 * @var int
	 */
	private int $tag = 0;

	/**
	 * The server's capabilities, upper-cased.
	 *
	 * @var array<int,string>
	 */
	private array $capabilities = [];

	/**
	 * How many messages the selected folder holds, as the server last said.
	 *
	 * @var int
	 */
	private int $exists = 0;

	/**
	 * Whether the session has ended.
	 *
	 * @var bool
	 */
	private bool $closed = false;

	/**
	 * @param ImapTransport          $transport The connection, before the server's greeting.
	 * @param MimeMessageParser $parser    Reads the fetched messages.
	 */
	private function __construct(
		private readonly ImapTransport $transport,
		private readonly MimeMessageParser $parser = new MimeMessageParser(),
	) {
	}//end __construct()

	/**
	 * Greet the server, secure the connection when asked, log in and select the folder.
	 *
	 * @param ImapTransport $transport The connection, before the server's greeting.
	 * @param string        $username  The login name.
	 * @param string        $password  The password.
	 * @param string        $folder    The folder to read, e.g. INBOX.
	 * @param bool          $startTls  Whether to upgrade the connection with STARTTLS first.
	 *
	 * @return self
	 *
	 * @throws RuntimeException When the server refuses the session; the connection is closed then.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public static function open(
		ImapTransport $transport,
		string $username,
		#[\SensitiveParameter] string $password,
		string $folder,
		bool $startTls,
	): self {
		$client = new self(transport: $transport);
		try {
			$client->secure(startTls: $startTls);
			$client->authenticate(username: $username, password: $password);
			$client->command(command: 'SELECT '.self::mailbox(name: $folder));
		} catch (Throwable $e) {
			$client->abort(reason: $e);
		}

		return $client;
	}//end open()

	/**
	 * Read the server's greeting and upgrade the connection with STARTTLS when asked.
	 *
	 * @param bool $startTls Whether to upgrade the connection.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod) open() calls it on the instance it builds; PHPMD only counts calls on $this.
	 */
	private function secure(bool $startTls): void {
		$this->greet();
		if ($startTls === true) {
			$this->command(command: 'STARTTLS');
			$this->transport->startTls();
		}
	}//end secure()

	/**
	 * Log in and learn what the server can do once logged in.
	 *
	 * @param string $username The login name.
	 * @param string $password The password.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod) open() calls it on the instance it builds; PHPMD only counts calls on $this.
	 */
	private function authenticate(string $username, #[\SensitiveParameter] string $password): void {
		$this->command(
			command: ['LOGIN ', self::astring(value: $username), ' ', self::astring(value: $password)],
			refusal: 'The mail server refused the login.'
		);
		$this->learnCapabilities();
	}//end authenticate()

	/**
	 * End a session that could not be opened and say why.
	 *
	 * @param Throwable $reason What went wrong.
	 *
	 * @return never
	 *
	 * @throws RuntimeException Always: the reason itself, or one that wraps it.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod) open() calls it on the instance it builds; PHPMD only counts calls on $this.
	 */
	private function abort(Throwable $reason): never {
		$this->closed = true;
		$this->transport->close();
		if ($reason instanceof RuntimeException) {
			throw $reason;
		}

		throw new RuntimeException('The mailbox could not be opened.', 0, $reason);
	}//end abort()

	/**
	 * The unseen messages of the folder, oldest first.
	 *
	 * @param int $limit At most this many.
	 *
	 * @return array<int,IncomingMail>
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function unseen(int $limit): array {
		if ($limit < 1) {
			return [];
		}

		$mails = [];
		foreach (array_slice($this->unseenUids(), 0, $limit) as $uid) {
			$raw = $this->fetch(uid: $uid);
			if ($raw !== null) {
				$mails[] = $this->parser->parse(uid: (string)$uid, raw: $raw);
			}
		}

		return $mails;
	}//end unseen()

	/**
	 * The UIDs of the unseen messages, ascending, each once.
	 *
	 * @return array<int,int>
	 */
	private function unseenUids(): array {
		$uids = [];
		foreach ($this->command(command: 'UID SEARCH UNSEEN') as $response) {
			if (preg_match('/^\* SEARCH((?: \d+)*)\s*$/i', $response['text'], $match) === 1) {
				$uids = array_merge($uids, array_map('intval', self::words(text: $match[1])));
			}
		}

		sort($uids);
		return array_values(array_unique($uids));
	}//end unseenUids()

	/**
	 * Move a message to another folder, creating the folder when needed.
	 *
	 * @param string $uid    The message's UID.
	 * @param string $folder The folder to move it to.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the UID is not one or the server refuses the move.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function move(string $uid, string $folder): void {
		if (preg_match('/^[1-9]\d*$/', $uid) !== 1) {
			throw new RuntimeException('Not a message UID.');
		}

		$target = self::mailbox(name: $folder);
		try {
			$this->command(command: 'CREATE '.$target);
		} catch (RuntimeException) {
			// The folder is already there; any other refusal shows in the move below.
		}

		if (in_array('MOVE', $this->capabilities, true) === true) {
			$this->command(command: 'UID MOVE '.$uid.' '.$target);
			return;
		}

		$this->command(command: 'UID COPY '.$uid.' '.$target);
		$this->command(command: 'UID STORE '.$uid.' +FLAGS.SILENT (\\Deleted)');
		if (in_array('UIDPLUS', $this->capabilities, true) === true) {
			$this->command(command: 'UID EXPUNGE '.$uid);
			return;
		}

		$this->command(command: 'EXPUNGE');
	}//end move()

	/**
	 * How many messages the folder holds.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function count(): int {
		$this->command(command: 'NOOP');
		return $this->exists;
	}//end count()

	/**
	 * Log out and close the connection; closing twice does nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void {
		if ($this->closed === true) {
			return;
		}

		$this->closed = true;
		try {
			$this->command(command: 'LOGOUT');
		} catch (Throwable) {
			// The session ends either way.
		}

		$this->transport->close();
	}//end close()

	/**
	 * Log out when the client goes away.
	 */
	public function __destruct() {
		$this->close();
	}//end __destruct()

	/**
	 * One message as the server holds it, or its headers when it is too large;
	 * null when the server has no such message any more.
	 *
	 * @param int $uid The message's UID.
	 *
	 * @return string|null
	 */
	private function fetch(int $uid): ?string {
		$size = $this->messageSize(uid: $uid);
		if ($size === null) {
			return null;
		}

		$section = 'BODY.PEEK[]';
		if ($size > self::MAX_MESSAGE_BYTES) {
			$section = 'BODY.PEEK[HEADER]';
		}

		return $this->literalOf(uid: $uid, section: $section);
	}//end fetch()

	/**
	 * The first literal the server sends for one section of a message, or null.
	 *
	 * @param int    $uid     The message's UID.
	 * @param string $section The section, e.g. BODY.PEEK[].
	 *
	 * @return string|null
	 */
	private function literalOf(int $uid, string $section): ?string {
		foreach ($this->command(command: 'UID FETCH '.$uid.' (UID '.$section.')') as $response) {
			if (self::fetchUid(text: $response['text']) === $uid && $response['literals'] !== []) {
				return $response['literals'][0];
			}
		}

		return null;
	}//end literalOf()

	/**
	 * The size of one message as the server reports it, or null when it has no such message.
	 *
	 * @param int $uid The message's UID.
	 *
	 * @return int|null
	 */
	private function messageSize(int $uid): ?int {
		$size = null;
		foreach ($this->command(command: 'UID FETCH '.$uid.' (UID RFC822.SIZE)') as $response) {
			if (self::fetchUid(text: $response['text']) === $uid && preg_match('/RFC822\.SIZE (\d+)/i', $response['text'], $match) === 1) {
				$size = (int)$match[1];
			}
		}

		return $size;
	}//end messageSize()

	/**
	 * The UID a FETCH response is about, or null when it is not a FETCH response.
	 *
	 * @param string $text The untagged response.
	 *
	 * @return int|null
	 */
	private static function fetchUid(string $text): ?int {
		if (preg_match('/^\* \d+ FETCH \(.*\bUID (\d+)/is', $text, $match) !== 1) {
			return null;
		}

		return (int)$match[1];
	}//end fetchUid()

	/**
	 * Read the server's greeting.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the server does not greet with OK.
	 */
	private function greet(): void {
		$line = $this->transport->readLine();
		if (preg_match('/^\* (OK|PREAUTH)\b/i', $line) !== 1) {
			throw new RuntimeException('The mail server refused the connection.');
		}

		$this->noteCapabilities(text: $line);
	}//end greet()

	/**
	 * Ask the server what it supports.
	 *
	 * @return void
	 */
	private function learnCapabilities(): void {
		foreach ($this->command(command: 'CAPABILITY') as $response) {
			$this->noteCapabilities(text: $response['text']);
		}
	}//end learnCapabilities()

	/**
	 * Remember the capabilities a CAPABILITY response or response code lists.
	 *
	 * @param string $text A response line.
	 *
	 * @return void
	 */
	private function noteCapabilities(string $text): void {
		if (preg_match('/(?:^\* |\[)CAPABILITY ([^\]\r\n]*)/i', $text, $match) === 1) {
			$this->capabilities = array_map('strtoupper', self::words(text: $match[1]));
		}
	}//end noteCapabilities()

	/**
	 * Send one command and read its untagged responses until its tagged completion.
	 *
	 * @param string|array<int,string|array{literal:string}> $command The command without its tag: a
	 *                                                           string, or segments where a literal is `['literal' => bytes]`.
	 * @param string                                            $refusal What to say when the server refuses it; a command that
	 *                                                           carries a secret says so without repeating the command.
	 *
	 * @return array<int,array{text:string,literals:array<int,string>}> The untagged responses,
	 *         each with its literals in order (their place in the text marked `{n}`).
	 *
	 * @throws RuntimeException When the server answers NO or BAD, or says BYE.
	 */
	private function command(#[\SensitiveParameter] string|array $command, string $refusal = 'The mail server refused a command.'): array {
		$tag = 'P'.str_pad((string)(++$this->tag), 4, '0', STR_PAD_LEFT);
		$this->send(tag: $tag, command: $command);

		$untagged = [];
		while (true) {
			$response = $this->readResponse();
			$text     = $response['text'];
			if (str_starts_with($text, $tag.' ') === true) {
				if (preg_match('/^'.$tag.' OK\b/i', $text) !== 1) {
					throw new RuntimeException($refusal);
				}

				$this->noteCapabilities(text: $text);
				return $untagged;
			}

			if (preg_match('/^\* BYE\b/i', $text) === 1 && $command !== 'LOGOUT') {
				throw new RuntimeException('The mail server closed the session.');
			}

			if (preg_match('/^\* (\d+) EXISTS\b/i', $text, $match) === 1) {
				$this->exists = (int)$match[1];
			}

			if (str_starts_with($text, '* ') === true) {
				$untagged[] = $response;
			}
		}
	}//end command()

	/**
	 * Send a tagged command, waiting for the server's go-ahead before each literal.
	 *
	 * @param string                                            $tag     The tag.
	 * @param string|array<int,string|array{literal:string}> $command The command or its segments.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the server does not accept a literal.
	 */
	private function send(string $tag, #[\SensitiveParameter] string|array $command): void {
		$out = $tag.' ';
		if (is_string($command) === true) {
			$command = [$command];
		}

		foreach ($command as $segment) {
			if (is_string($segment) === true) {
				$out .= $segment;
				continue;
			}

			$this->transport->write($out.'{'.strlen($segment['literal'])."}\r\n");
			if (str_starts_with($this->transport->readLine(), '+') === false) {
				throw new RuntimeException('The mail server refused a command.');
			}

			$out = $segment['literal'];
		}

		$this->transport->write($out."\r\n");
	}//end send()

	/**
	 * Read one response: a line, and every literal it announces with the rest of the line after it.
	 *
	 * @return array{text:string,literals:array<int,string>}
	 */
	private function readResponse(): array {
		$text     = '';
		$literals = [];
		while (true) {
			$line = $this->transport->readLine();
			if (preg_match('/\{(\d+)\}\r?\n$/', $line, $match) === 1) {
				$length = (int)$match[1];
				if ($length > (self::MAX_MESSAGE_BYTES + 1048576)) {
					throw new RuntimeException('The mail server sent more than a message may hold.');
				}

				$text      .= rtrim($line, "\r\n");
				$literals[] = $this->transport->read($length);
				continue;
			}

			return ['text' => $text.rtrim($line, "\r\n"), 'literals' => $literals];
		}
	}//end readResponse()

	/**
	 * The space-separated words of a text.
	 *
	 * @param string $text The text.
	 *
	 * @return array<int,string>
	 */
	private static function words(string $text): array {
		$words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
		if ($words === false) {
			return [];
		}

		return $words;
	}//end words()

	/**
	 * An IMAP astring: a quoted string, or a literal when the value cannot be quoted.
	 *
	 * @param string $value The value.
	 *
	 * @return string|array{literal:string}
	 */
	private static function astring(#[\SensitiveParameter] string $value): string|array {
		if (preg_match('/^[\x20-\x7e]*$/', $value) === 1) {
			return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
		}

		return ['literal' => $value];
	}//end astring()

	/**
	 * A mailbox name as IMAP wants it: modified UTF-7 (RFC 3501 section 5.1.3), quoted.
	 *
	 * @param string $name The folder name in UTF-8.
	 *
	 * @return string
	 *
	 * @throws RuntimeException When the name holds a line break.
	 */
	private static function mailbox(string $name): string {
		if ($name === '' || preg_match('/[\r\n\x00]/', $name) === 1) {
			throw new RuntimeException('Not a folder name.');
		}

		if (strcasecmp($name, 'INBOX') === 0) {
			return 'INBOX';
		}

		$encoded = str_replace('&', '&-', $name);
		if (preg_match('/^[\x20-\x7e]*$/', $name) !== 1) {
			$encoded = (string)mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
		}

		return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $encoded).'"';
	}//end mailbox()
}//end class
