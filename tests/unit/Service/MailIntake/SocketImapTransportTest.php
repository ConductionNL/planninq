<?php

/**
 * Unit tests for SocketImapTransport, against a loopback socket in the same process.
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

use OCA\Planninq\Service\MailIntake\SocketImapTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\MailIntake\SocketImapTransport
 */
class SocketImapTransportTest extends TestCase {

	/**
	 * The listening loopback socket.
	 *
	 * @var resource
	 */
	private $listener;

	/**
	 * Its port.
	 *
	 * @var int
	 */
	private int $port;

	/**
	 * Listen on a free loopback port.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$this->assertNotFalse($listener, 'a loopback socket can be opened');
		$this->listener = $listener;
		$this->port     = (int)substr((string)strrchr((string)stream_socket_get_name($listener, false), ':'), 1);
	}//end setUp()

	/**
	 * Stop listening.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		fclose($this->listener);
	}//end tearDown()

	/**
	 * Lines and counted bytes come in as the server sent them; writes reach the server whole.
	 *
	 * @return void
	 */
	public function testReadsLinesAndLiteralsAndWrites(): void {
		$transport = new SocketImapTransport(host: '127.0.0.1', port: $this->port, tls: false, timeout: 5);
		$server    = stream_socket_accept($this->listener, 5);
		$this->assertNotFalse($server);

		fwrite($server, "* OK ready\r\n{5}\r\nhello there\r\n");
		$this->assertSame("* OK ready\r\n", $transport->readLine());
		$this->assertSame("{5}\r\n", $transport->readLine());
		$this->assertSame('hello', $transport->read(5));
		$this->assertSame(" there\r\n", $transport->readLine());

		$transport->write("P0001 NOOP\r\n");
		$this->assertSame("P0001 NOOP\r\n", fgets($server));

		fclose($server);
		$transport->close();
	}//end testReadsLinesAndLiteralsAndWrites()

	/**
	 * A connection the server closed reads as an error, not as an empty answer.
	 *
	 * @return void
	 */
	public function testAClosedServerIsAnError(): void {
		$transport = new SocketImapTransport(host: '127.0.0.1', port: $this->port, tls: false, timeout: 5);
		$server    = stream_socket_accept($this->listener, 5);
		$this->assertNotFalse($server);
		fclose($server);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The mail server did not answer.');
		$transport->readLine();
	}//end testAClosedServerIsAnError()

	/**
	 * After close, every use is refused, and closing again does nothing.
	 *
	 * @return void
	 */
	public function testAClosedTransportRefusesUse(): void {
		$transport = new SocketImapTransport(host: '127.0.0.1', port: $this->port, tls: false, timeout: 5);
		$transport->close();
		$transport->close();

		foreach ([static fn () => $transport->readLine(), static fn () => $transport->read(1), static fn () => $transport->write('x'), static fn () => $transport->startTls()] as $use) {
			try {
				$use();
				$this->fail('A closed transport must refuse use.');
			} catch (RuntimeException $e) {
				$this->assertSame('The mail connection is closed.', $e->getMessage());
			}
		}
	}//end testAClosedTransportRefusesUse()

	/**
	 * A server that cannot be reached is a RuntimeException naming no internals.
	 *
	 * @return void
	 */
	public function testAnUnreachableServerThrows(): void {
		$port = $this->port;
		fclose($this->listener);
		$this->listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The mail server could not be reached.');
		new SocketImapTransport(host: '127.0.0.1', port: $port, tls: false, timeout: 2);
	}//end testAnUnreachableServerThrows()
}//end class
