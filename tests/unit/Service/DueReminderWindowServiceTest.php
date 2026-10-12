<?php

/**
 * Tests for the OpenRegister preference service lookup.
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
 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\DueReminderWindowService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\DueReminderWindowService
 */
class DueReminderWindowServiceTest extends TestCase {

	/**
	 * A service over the given app state and container.
	 *
	 * @param bool                   $installed Whether OpenRegister is installed.
	 * @param ContainerInterface|null $container The container, or a throwing one.
	 *
	 * @return DueReminderWindowService
	 */
	private function service(bool $installed, ?ContainerInterface $container = null): DueReminderWindowService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn($installed);

		return new DueReminderWindowService(
			appManager: $appManager,
			container: ($container ?? $this->createMock(originalClassName: ContainerInterface::class)),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * Without OpenRegister there is no preference service.
	 *
	 * @return void
	 */
	public function testNoServiceWithoutOpenRegister(): void {
		self::assertNull($this->service(installed: false)->notificationPreferenceService());
	}//end testNoServiceWithoutOpenRegister()

	/**
	 * The service is the one the container resolves.
	 *
	 * @return void
	 */
	public function testReturnsTheResolvedService(): void {
		$resolved  = new \stdClass();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($resolved);

		self::assertSame($resolved, $this->service(installed: true, container: $container)->notificationPreferenceService());
	}//end testReturnsTheResolvedService()

	/**
	 * A container that cannot resolve it yields null, not an error.
	 *
	 * @return void
	 */
	public function testNullWhenTheContainerCannotResolve(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new \RuntimeException('not found'));

		self::assertNull($this->service(installed: true, container: $container)->notificationPreferenceService());
	}//end testNullWhenTheContainerCannotResolve()
}//end class
