<?php

/**
 * Unit tests for NotificationSwitchService's failure paths.
 *
 * Covers an override write that OpenRegister refuses (logged per rule, the
 * other rule still written) and a preference service that cannot be resolved
 * (the switch is stored and the write-through skipped).
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\OpenRegister\Kernel\Contracts\PreferenceWrite;
use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\NotificationSwitchService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for NotificationSwitchService when OpenRegister fails.
 *
 * @covers \OCA\Planninq\Service\NotificationSwitchService
 */
class NotificationSwitchFailuresTest extends TestCase {

	/**
	 * The service with OpenRegister installed and the given container and logger.
	 *
	 * @param ContainerInterface $container The container.
	 * @param LoggerInterface    $logger    The logger.
	 * @param IConfig            $config    The config.
	 *
	 * @return NotificationSwitchService
	 */
	private function service(ContainerInterface $container, LoggerInterface $logger, IConfig $config): NotificationSwitchService {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->with('openregister')->willReturn(true);

		return new NotificationSwitchService(
			config: $config,
			appManager: $apps,
			container: $container,
			logger: $logger,
			users: $this->createMock(originalClassName: IUserManager::class)
		);
	}//end service()

	/**
	 * A refused override write is logged for that rule and the next rule is still written.
	 *
	 * @return void
	 */
	public function testARefusedOverrideIsLoggedPerRule(): void {
		$preferences = new class () {
			/** @var array<int,string> */
			public array $attempts = [];

			public function storeOverride(PreferenceWrite $write): void {
				$this->attempts[] = $write->address->notificationKey;
				if ($write->address->notificationKey === 'taskAssignedOnCreate') {
					throw new \RuntimeException('refused');
				}
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($preferences);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with(
				'Planninq: failed to write the notify_assigned override',
				['user' => 'ben', 'rule' => 'taskAssignedOnCreate', 'exception' => 'refused']
			);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->expects(self::once())->method('setUserValue')->with('ben', Application::APP_ID, 'notify_assigned', 'false');

		$this->service(container: $container, logger: $logger, config: $config)->setAssigned(userId: 'ben', enabled: false);

		self::assertSame(['taskAssignedOnCreate', 'taskAssigned'], $preferences->attempts);
	}//end testARefusedOverrideIsLoggedPerRule()

	/**
	 * A preference service that cannot be resolved is logged; the switch is still stored and nothing else is written.
	 *
	 * @return void
	 */
	public function testAnUnresolvablePreferenceServiceSkipsTheWriteThrough(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::once())
			->method('get')
			->with('OCA\OpenRegister\Notifications\Service\Notification\NotificationPreferenceService')
			->willThrowException(new \RuntimeException('not registered'));

		$infos = [];
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::never())->method('warning');
		$logger->method('info')->willReturnCallback(
			static function (string $message, array $context = []) use (&$infos): void {
				$infos[] = [$message, $context];
			}
		);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->expects(self::once())->method('setUserValue')->with('carl', Application::APP_ID, 'notify_assigned', 'true');

		$this->service(container: $container, logger: $logger, config: $config)->setAssigned(userId: 'carl', enabled: true);

		self::assertSame(
			[
				['Planninq: NotificationPreferenceService unavailable', ['exception' => 'not registered']],
				['Planninq: OpenRegister unavailable, notify_assigned override skipped', ['user' => 'carl']],
			],
			$infos
		);
	}//end testAnUnresolvablePreferenceServiceSkipsTheWriteThrough()
}//end class
