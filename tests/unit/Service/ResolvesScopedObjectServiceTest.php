<?php

/**
 * Tests for ResolvesScopedObjectService: the ObjectService comes back cleared, or the lookup fails loudly.
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
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ResolvesScopedObjectService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\ResolvesScopedObjectService
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */
class ResolvesScopedObjectServiceTest extends TestCase {

	/**
	 * A class using the trait over the given container.
	 *
	 * @param ContainerInterface $container The container.
	 *
	 * @return object
	 */
	private function resolver(ContainerInterface $container): object {
		return new class($container) {
			use ResolvesScopedObjectService;

			/**
			 * The ObjectService's container id.
			 */
			private const OR_OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

			/**
			 * Constructor.
			 *
			 * @param ContainerInterface $container The container.
			 */
			public function __construct(private ContainerInterface $container) {
			}//end __construct()

			/**
			 * The resolved service.
			 *
			 * @return object
			 */
			public function resolve(): object {
				return $this->objectService();
			}//end resolve()
		};
	}//end resolver()

	/**
	 * The ObjectService is looked up by its id and cleared before it is handed out.
	 *
	 * @return void
	 */
	public function testTheServiceIsClearedBeforeItIsHandedOut(): void {
		$service = new class {
			/**
			 * How often the scope was cleared.
			 *
			 * @var integer
			 */
			public int $cleared = 0;

			/**
			 * Clear the scope.
			 *
			 * @return void
			 */
			public function clearCurrents(): void {
				$this->cleared++;
			}//end clearCurrents()
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->expects($this->once())->method('get')
			->with('OCA\\OpenRegister\\Service\\ObjectService')
			->willReturn($service);

		$this->assertSame($service, $this->resolver($container)->resolve());
		$this->assertSame(1, $service->cleared);
	}//end testTheServiceIsClearedBeforeItIsHandedOut()

	/**
	 * A service without clearCurrents() is handed out as it is.
	 *
	 * @return void
	 */
	public function testAServiceWithoutAScopeIsHandedOutAsItIs(): void {
		$service   = new \stdClass();
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($service);

		$this->assertSame($service, $this->resolver($container)->resolve());
	}//end testAServiceWithoutAScopeIsHandedOutAsItIs()

	/**
	 * A failed lookup becomes a RuntimeException that keeps the cause.
	 *
	 * @return void
	 */
	public function testAFailedLookupSaysOpenRegisterIsNotAvailable(): void {
		$cause     = new \RuntimeException('no such service');
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willThrowException($cause);

		try {
			$this->resolver($container)->resolve();
			$this->fail('Expected a RuntimeException.');
		} catch (RuntimeException $e) {
			$this->assertSame('OpenRegister is not available.', $e->getMessage());
			$this->assertSame($cause, $e->getPrevious());
		}
	}//end testAFailedLookupSaysOpenRegisterIsNotAvailable()
}//end class
