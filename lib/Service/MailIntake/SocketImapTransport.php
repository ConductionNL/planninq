<?php

/**
 * Planninq Socket IMAP Transport
 *
 * An IMAP connection over a PHP stream socket.
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

use RuntimeException;

/**
 * A TCP or TLS stream to the mail server. TLS always verifies the server's
 * certificate and name; there is no switch to turn that off.
 *
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
 */
final class SocketImapTransport implements ImapTransport {

	/**
	 * The open stream, or null once closed.
	 *
	 * @var resource|null
	 */
	private $stream;

	/**
	 * Open a connection.
	 *
	 * @param string $host    The server's host name.
	 * @param int    $port    The port.
	 * @param bool   $tls     Whether to speak TLS from the first byte (port 993).
	 * @param int    $timeout Seconds to wait for the connection and for each read.
	 *
	 * @throws RuntimeException When the server cannot be reached.
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function __construct(
		private readonly string $host,
		int $port,
		bool $tls,
		int $timeout = 30,
	) {
		$scheme = 'tcp://';
		if ($tls === true) {
			$scheme = 'ssl://';
		}

		$this->stream = $this->connect(address: $scheme.$host.':'.$port, timeout: $timeout);
	}//end __construct()

	/**
	 * Open the socket to the server, verifying its certificate for the host.
	 *
	 * @param string $address The scheme, host and port.
	 * @param int    $timeout Seconds to wait for the server.
	 *
	 * @return resource
	 *
	 * @throws RuntimeException When the server cannot be reached.
	 */
	private function connect(string $address, int $timeout) {
		$context = stream_context_create(['ssl' => self::sslOptions(host: $this->host)]);
		$errno   = 0;
		$errstr  = '';
		$stream  = self::quietly(
			call: static function () use ($address, &$errno, &$errstr, $timeout, $context) {
				return stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
			}
		);
		if ($stream === false) {
			throw new RuntimeException('The mail server could not be reached.');
		}

		stream_set_timeout($stream, $timeout);
		return $stream;
	}//end connect()

	/**
	 * Send bytes as they are.
	 *
	 * @param string $data The bytes.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function write(#[\SensitiveParameter] string $data): void {
		$stream = $this->open();
		$offset = 0;
		$length = strlen($data);
		while ($offset < $length) {
			$chunk   = substr($data, $offset);
			$written = self::quietly(call: static fn () => fwrite($stream, $chunk));
			if ($written === false || $written === 0) {
				throw new RuntimeException('The mail server closed the connection.');
			}

			$offset += $written;
		}
	}//end write()

	/**
	 * Read one line, its CRLF included.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function readLine(): string {
		$stream = $this->open();
		$line   = self::quietly(call: static fn () => fgets($stream));
		if ($line === false) {
			throw new RuntimeException('The mail server did not answer.');
		}

		return $line;
	}//end readLine()

	/**
	 * Read exactly this many bytes.
	 *
	 * @param int $length How many bytes.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function read(int $length): string {
		$stream = $this->open();
		$data   = '';
		while (strlen($data) < $length) {
			$size  = min(8192, ($length - strlen($data)));
			$chunk = self::quietly(call: static fn () => fread($stream, $size));
			if ($chunk === false || $chunk === '') {
				throw new RuntimeException('The mail server did not answer.');
			}

			$data .= $chunk;
		}

		return $data;
	}//end read()

	/**
	 * Switch the connection to TLS, verifying the server.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function startTls(): void {
		$stream = $this->open();
		foreach (self::sslOptions(host: $this->host) as $option => $value) {
			stream_context_set_option($stream, 'ssl', $option, $value);
		}

		$done = self::quietly(call: static fn () => stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT));
		if ($done !== true) {
			throw new RuntimeException('The mail server refused a secure connection.');
		}
	}//end startTls()

	/**
	 * Close the connection.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-2.1
	 */
	public function close(): void {
		if ($this->stream !== null) {
			$stream = $this->stream;
			self::quietly(call: static fn () => fclose($stream));
			$this->stream = null;
		}
	}//end close()

	/**
	 * The open stream.
	 *
	 * @return resource
	 */
	private function open() {
		if ($this->stream === null) {
			throw new RuntimeException('The mail connection is closed.');
		}

		return $this->stream;
	}//end open()

	/**
	 * Run a stream call without letting its PHP warning escape: the caller turns
	 * the call's false result into a RuntimeException that says what failed.
	 *
	 * @param callable $call The stream call.
	 *
	 * @return mixed What the call returned.
	 */
	private static function quietly(callable $call): mixed {
		set_error_handler(static fn (): bool => true);
		try {
			return $call();
		} finally {
			restore_error_handler();
		}
	}//end quietly()

	/**
	 * TLS options that verify the server's certificate and name.
	 *
	 * @param string $host The server's host name.
	 *
	 * @return array<string,bool|string>
	 */
	private static function sslOptions(string $host): array {
		return [
			'verify_peer'       => true,
			'verify_peer_name'  => true,
			'peer_name'         => $host,
			'allow_self_signed' => false,
		];
	}//end sslOptions()
}//end class
