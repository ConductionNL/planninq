<?php

/**
 * A scripted ImapTransport: answers each command from a map keyed by command verb.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Support
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Support;

use OCA\Planninq\Service\Mail\ImapTransport;

/**
 * Fake IMAP server connection.
 */
class FakeImapTransport implements ImapTransport {

	/**
	 * Lines (string) and counted blocks (['bytes' => string]) waiting to be read.
	 *
	 * @var array<int,string|array{bytes:string}>
	 */
	private array $queue = ['* OK ready'];

	/**
	 * Every command written, without its tag.
	 *
	 * @var array<int,string>
	 */
	public array $commands = [];

	/**
	 * Whether close() was called.
	 *
	 * @var bool
	 */
	public bool $closed = false;

	/**
	 * Constructor.
	 *
	 * @param array<string,array<int,string|array{bytes:string}>> $replies Untagged replies per command verb (`UID SEARCH`, `UID FETCH` ...).
	 * @param array<string,string>                                $refuse  Tagged status per verb, default OK.
	 */
	public function __construct(private array $replies = [], private array $refuse = []) {
	}//end __construct()

	/**
	 * {@inheritDoc}
	 */
	public function writeLine(string $line): void {
		[$tag, $command] = explode(' ', $line, 2);
		$this->commands[] = $command;
		$verb = $this->verbOf(command: $command);
		foreach (($this->replies[$verb] ?? []) as $reply) {
			$this->queue[] = $reply;
		}

		$this->queue[] = $tag . ' ' . ($this->refuse[$verb] ?? 'OK done');
	}//end writeLine()

	/**
	 * {@inheritDoc}
	 */
	public function readLine(): string {
		$next = array_shift($this->queue);
		if (is_string($next) === false) {
			throw new \RuntimeException('Script out of step: a line was expected.');
		}

		return $next;
	}//end readLine()

	/**
	 * {@inheritDoc}
	 */
	public function readBytes(int $length): string {
		$next = array_shift($this->queue);
		if (is_array($next) === false || strlen($next['bytes']) !== $length) {
			throw new \RuntimeException('Script out of step: a block of ' . $length . ' bytes was expected.');
		}

		return $next['bytes'];
	}//end readBytes()

	/**
	 * {@inheritDoc}
	 */
	public function close(): void {
		$this->closed = true;
	}//end close()

	/**
	 * The one or two words that name a command.
	 *
	 * @param string $command The command line.
	 *
	 * @return string
	 */
	private function verbOf(string $command): string {
		$words = explode(' ', $command);
		if ($words[0] === 'UID') {
			return $words[0] . ' ' . $words[1];
		}

		return $words[0];
	}//end verbOf()
}//end class
