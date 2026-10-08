<?php

/**
 * Planninq SocketImapTransport
 *
 * An ImapTransport over a TLS or plain TCP stream socket (implicit TLS only; STARTTLS is not supported).
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\Mail;

use RuntimeException;

/**
 * Socket transport; `ssl` connects with TLS, `none` stays plain.
 */
class SocketImapTransport implements ImapTransport {

	/**
	 * The open stream.
	 *
	 * @var resource
	 */
	private $stream;

	/**
	 * Connect.
	 *
	 * @param string $host       The server.
	 * @param int    $port       The port.
	 * @param string $encryption `ssl` or `none`.
	 * @param int    $timeout    Seconds to wait for the connection and each read.
	 *
	 * @throws RuntimeException When the server cannot be reached.
	 */
	public function __construct(string $host, int $port, string $encryption, int $timeout = 20) {
		$scheme = 'tcp';
		if ($encryption === 'ssl') {
			$scheme = 'ssl';
		}

		$stream = @stream_socket_client($scheme . '://' . $host . ':' . $port, $code, $message, $timeout);
		if ($stream === false) {
			throw new RuntimeException('Could not connect to the mail server: ' . $message);
		}

		stream_set_timeout($stream, $timeout);
		$this->stream = $stream;
	}//end __construct()

	/**
	 * Read one line, without its line ending.
	 *
	 * @return string
	 */
	public function readLine(): string {
		$line = fgets($this->stream);
		if ($line === false) {
			throw new RuntimeException('The mail server closed the connection.');
		}

		return rtrim($line, "\r\n");
	}//end readLine()

	/**
	 * Read exactly $length bytes.
	 *
	 * @param int $length The byte count.
	 *
	 * @return string
	 */
	public function readBytes(int $length): string {
		$data = '';
		while (strlen($data) < $length) {
			$chunk = fread($this->stream, ($length - strlen($data)));
			if ($chunk === false || $chunk === '') {
				throw new RuntimeException('The mail server closed the connection.');
			}

			$data .= $chunk;
		}

		return $data;
	}//end readBytes()

	/**
	 * Write one line with its line ending.
	 *
	 * @param string $line The line.
	 *
	 * @return void
	 */
	public function writeLine(string $line): void {
		fwrite($this->stream, $line . "\r\n");
	}//end writeLine()

	/**
	 * Close the connection.
	 *
	 * @return void
	 */
	public function close(): void {
		/** @psalm-suppress InvalidPropertyAssignmentValue The handle is not used after close. */
		fclose($this->stream);
	}//end close()
}//end class
