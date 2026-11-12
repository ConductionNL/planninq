<?php

/**
 * Unit tests for OpenRegisterObjects.
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
 * @spec openspec/specs/projects/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\OpenRegisterObjects;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @covers \OCA\Planninq\Service\OpenRegisterObjects
 */
final class OpenRegisterObjectsTest extends TestCase {

	/**
	 * With OpenRegister installed, the container's ObjectService comes back.
	 *
	 * @return void
	 */
	public function testReturnsTheObjectServiceWhenOpenRegisterIsInstalled(): void {
		$service = new \stdClass();
		$objects = new OpenRegisterObjects(appManager: $this->apps(installed: true), container: $this->container(service: $service));

		self::assertSame(expected: $service, actual: $objects->objectService());
	}//end testReturnsTheObjectServiceWhenOpenRegisterIsInstalled()

	/**
	 * Without OpenRegister installed, null comes back and the container is not asked.
	 *
	 * @return void
	 */
	public function testReturnsNullWhenOpenRegisterIsMissing(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects($this->never())->method('get');
		$objects = new OpenRegisterObjects(appManager: $this->apps(installed: false), container: $container);

		self::assertNull(actual: $objects->objectService());
	}//end testReturnsNullWhenOpenRegisterIsMissing()

	/**
	 * An app manager that answers for the openregister app only.
	 *
	 * @param bool $installed Whether OpenRegister is installed.
	 *
	 * @return IAppManager
	 */
	private function apps(bool $installed): IAppManager {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturnMap([['openregister', $installed]]);
		return $apps;
	}//end apps()

	/**
	 * A container that serves the given object as OpenRegister's ObjectService.
	 *
	 * @param object $service The ObjectService stand-in.
	 *
	 * @return ContainerInterface
	 */
	private function container(object $service): ContainerInterface {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnMap([['OCA\\OpenRegister\\Service\\ObjectService', $service]]);
		return $container;
	}//end container()
}//end class
