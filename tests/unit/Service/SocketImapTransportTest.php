<?php

/**
 * Tests for the socket IMAP transport, against a local server socket.
 *
 * @category Tests
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

use OCA\Planninq\Service\Mail\SocketImapTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\Mail\SocketImapTransport
 */
class SocketImapTransportTest extends TestCase {

	/**
	 * Lines and byte blocks travel both ways over a plain connection.
	 *
	 * @return void
	 */
	public function testReadsAndWritesOverAPlainConnection(): void {
		$server = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
		self::assertNotFalse($server, (string)$message);
		$port = (int)substr(strrchr((string)stream_socket_get_name($server, false), ':'), 1);

		$transport = new SocketImapTransport(host: '127.0.0.1', port: $port, encryption: 'none', timeout: 5);
		$peer      = stream_socket_accept($server, 5);
		self::assertNotFalse($peer);

		fwrite($peer, "* OK ready\r\nHELLOWORLD");
		self::assertSame('* OK ready', $transport->readLine());
		self::assertSame('HELLOWORLD', $transport->readBytes(10));

		$transport->writeLine('A1 NOOP');
		self::assertSame("A1 NOOP\r\n", fgets($peer));

		fclose($peer);
		$this->expectException(RuntimeException::class);
		try {
			$transport->readLine();
		} finally {
			$transport->close();
			fclose($server);
		}
	}//end testReadsAndWritesOverAPlainConnection()

	/**
	 * A server that hangs up mid-read is an exception, not an endless loop.
	 *
	 * @return void
	 */
	public function testReadBytesFailsWhenTheServerCloses(): void {
		$server = stream_socket_server('tcp://127.0.0.1:0');
		$port   = (int)substr(strrchr((string)stream_socket_get_name($server, false), ':'), 1);

		$transport = new SocketImapTransport(host: '127.0.0.1', port: $port, encryption: 'none', timeout: 5);
		$peer      = stream_socket_accept($server, 5);
		fwrite($peer, 'abc');
		fclose($peer);

		$this->expectException(RuntimeException::class);
		try {
			$transport->readBytes(10);
		} finally {
			$transport->close();
			fclose($server);
		}
	}//end testReadBytesFailsWhenTheServerCloses()

	/**
	 * An unreachable server is an exception that names the failure.
	 *
	 * @return void
	 */
	public function testConnectFailureThrows(): void {
		$server = stream_socket_server('tcp://127.0.0.1:0');
		$port   = (int)substr(strrchr((string)stream_socket_get_name($server, false), ':'), 1);
		fclose($server);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not connect');
		new SocketImapTransport(host: '127.0.0.1', port: $port, encryption: 'ssl', timeout: 2);
	}//end testConnectFailureThrows()
}//end class
