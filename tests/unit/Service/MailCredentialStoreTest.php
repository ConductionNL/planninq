<?php

/**
 * Tests for the mail intake credential store.
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
 * @spec openspec/changes/tasks-create-by-email/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\MailCredentialStore;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\MailCredentialStore
 */
class MailCredentialStoreTest extends TestCase {

	/**
	 * A store whose container hands out the given broker.
	 *
	 * @param object|null $broker The broker, or null to make the lookup throw.
	 *
	 * @return MailCredentialStore
	 */
	private function store(?object $broker): MailCredentialStore {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		if ($broker === null) {
			$container->method('get')->willThrowException(new \RuntimeException('OpenRegister is not installed'));
		} else {
			$container->method('get')->willReturn($broker);
		}

		return new MailCredentialStore(container: $container, logger: $this->createMock(originalClassName: LoggerInterface::class));
	}//end store()

	/**
	 * A broker double returning fixed values.
	 *
	 * @param mixed $minted   What mint() returns.
	 * @param mixed $resolved What resolveInjectable() returns.
	 *
	 * @return object
	 */
	private function broker(mixed $minted = null, mixed $resolved = null): object {
		return new class ($minted, $resolved) {
			public function __construct(private mixed $minted, private mixed $resolved) {
			}

			public function mint(string $provider, string $name, string $secret, string $scope, string $credentialId): mixed {
				return $this->minted;
			}

			public function resolveInjectable(string $credentialRef, string $appId): mixed {
				return $this->resolved;
			}
		};
	}//end broker()

	/**
	 * The reference comes back whatever shape the broker returns it in.
	 *
	 * @return void
	 */
	public function testStoreReturnsTheReferenceInEveryShape(): void {
		self::assertSame('ref-1', $this->store($this->broker(minted: 'ref-1'))->store('pw'));
		self::assertSame('ref-2', $this->store($this->broker(minted: ['credentialRef' => 'ref-2']))->store('pw'));
		self::assertSame('ref-3', $this->store($this->broker(minted: ['uuid' => 'ref-3']))->store('pw', 'old'));

		$entity = new class () {
			public function getUuid(): string {
				return 'ref-4';
			}
		};
		self::assertSame('ref-4', $this->store($this->broker(minted: $entity))->store('pw'));
	}//end testStoreReturnsTheReferenceInEveryShape()

	/**
	 * No reference when the broker is missing or hands back nothing usable.
	 *
	 * @return void
	 */
	public function testStoreReturnsNullWhenNothingUsableComesBack(): void {
		self::assertNull($this->store(null)->store('pw'));
		self::assertNull($this->store($this->broker(minted: ''))->store('pw'));
		self::assertNull($this->store($this->broker(minted: ['other' => 'x']))->store('pw'));
	}//end testStoreReturnsNullWhenNothingUsableComesBack()

	/**
	 * A reference resolves to the password, from a string or an array.
	 *
	 * @return void
	 */
	public function testResolveReturnsThePassword(): void {
		self::assertSame('secret', $this->store($this->broker(resolved: 'secret'))->resolve('ref'));
		self::assertSame('s1', $this->store($this->broker(resolved: ['secret' => 's1']))->resolve('ref'));
		self::assertSame('s2', $this->store($this->broker(resolved: ['password' => 's2']))->resolve('ref'));
	}//end testResolveReturnsThePassword()

	/**
	 * Nothing resolves from an empty reference, a missing broker or an empty secret.
	 *
	 * @return void
	 */
	public function testResolveReturnsNullWhenItCannotResolve(): void {
		self::assertNull($this->store($this->broker(resolved: 'secret'))->resolve(''));
		self::assertNull($this->store(null)->resolve('ref'));
		self::assertNull($this->store($this->broker(resolved: ''))->resolve('ref'));
		self::assertNull($this->store($this->broker(resolved: ['other' => 'x']))->resolve('ref'));
	}//end testResolveReturnsNullWhenItCannotResolve()
}//end class
