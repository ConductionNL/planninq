<?php

/**
 * Unit tests for SettingsService's due-reminder override failure paths.
 *
 * Covers a preference service that cannot be resolved (logged, skipped) and
 * an override write that OpenRegister refuses (logged, reported as not
 * written).
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
use OCA\Planninq\Service\CreationPolicyService;
use OCA\Planninq\Service\DueReminderWindowService;
use OCA\Planninq\Service\ProjectPolicySchemaService;
use OCA\Planninq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for SettingsService::writeDueReminderOverride() when OpenRegister fails.
 *
 * @covers \OCA\Planninq\Service\SettingsService
 * @uses   \OCA\Planninq\Service\CreationPolicyService
 * @uses   \OCA\Planninq\Service\DueReminderWindowService
 * @uses   \OCA\Planninq\Service\ProjectPolicySchemaService
 */
class SettingsServiceOverrideFailuresTest extends TestCase {

	/**
	 * The service with OpenRegister installed.
	 *
	 * @param ContainerInterface $container The container.
	 * @param LoggerInterface    $logger    The logger.
	 *
	 * @return SettingsService
	 */
	private function service(ContainerInterface $container, LoggerInterface $logger): SettingsService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturnMap([['openregister', true]]);
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$userSession = $this->createMock(originalClassName: IUserSession::class);

		return new SettingsService(
			appConfig: $appConfig,
			config: $this->createMock(originalClassName: IConfig::class),
			appManager: $appManager,
			container: $container,
			userSession: $userSession,
			logger: $logger,
			dueReminderWindow: new DueReminderWindowService(appManager: $appManager, container: $container, logger: $logger),
			policySchema: new ProjectPolicySchemaService(appManager: $appManager, container: $container, logger: $logger),
			creationPolicy: new CreationPolicyService(
				appConfig: $appConfig,
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				userSession: $userSession
			),
		);
	}//end service()

	/**
	 * A preference service that cannot be resolved is logged twice and the override is skipped.
	 *
	 * @return void
	 */
	public function testAnUnresolvablePreferenceServiceSkipsTheOverride(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::once())
			->method('get')
			->with('OCA\OpenRegister\Notifications\Service\Notification\NotificationPreferenceService')
			->willThrowException(new \RuntimeException('not registered'));

		$infos = [];
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			static function (string $message, array $context = []) use (&$infos): void {
				$infos[] = [$message, $context];
			}
		);

		$service = $this->service(container: $container, logger: $logger);

		self::assertFalse($service->writeDueReminderOverride(userId: 'ben', override: ['enabled' => false]));
		self::assertSame(
			[
				['Planninq: NotificationPreferenceService unavailable', ['exception' => 'not registered']],
				['Planninq: OpenRegister unavailable, notify_due_reminder override skipped', ['user' => 'ben']],
			],
			$infos
		);
	}//end testAnUnresolvablePreferenceServiceSkipsTheOverride()

	/**
	 * A refused override write is logged and answered as not written.
	 *
	 * @return void
	 */
	public function testARefusedOverrideIsLoggedAndNotWritten(): void {
		$preferences = new class () {
			/** @var array<int,PreferenceWrite> */
			public array $writes = [];

			public function storeOverride(PreferenceWrite $write): void {
				$this->writes[] = $write;
				throw new \RuntimeException('refused');
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($preferences);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with('Planninq: failed to write notify_due_reminder OR override', ['user' => 'ben', 'exception' => 'refused']);

		self::assertFalse($this->service(container: $container, logger: $logger)->writeDueReminderOverride(userId: 'ben', override: null));
		self::assertCount(1, $preferences->writes);
		self::assertSame('ben', $preferences->writes[0]->party);
		self::assertSame('task', $preferences->writes[0]->address->schemaSlug);
		self::assertSame('taskDueSoon', $preferences->writes[0]->address->notificationKey);
		self::assertNull($preferences->writes[0]->value);
	}//end testARefusedOverrideIsLoggedAndNotWritten()
}//end class
