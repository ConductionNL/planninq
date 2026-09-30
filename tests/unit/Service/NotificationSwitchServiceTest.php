<?php

/**
 * Tests for NotificationSwitchService: the assignment notification switch and
 * its write-through to OpenRegister's per-user override of both rules.
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
 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\NotificationSwitchService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
 */
class NotificationSwitchServiceTest extends TestCase {

	/**
	 * The service over a user-value store, with or without OpenRegister.
	 *
	 * @param array<string,string>          $stored      Written user values, by reference.
	 * @param SwitchPreferenceSpy|null $preferences OpenRegister's preference service, null without OpenRegister.
	 *
	 * @return NotificationSwitchService
	 */
	private function service(array &$stored, ?SwitchPreferenceSpy $preferences): NotificationSwitchService {
		$config = $this->createMock(IConfig::class);
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $value) use (&$stored): void {
				self::assertSame(Application::APP_ID, $app);
				$stored[$uid.'/'.$key] = $value;
			}
		);
		$config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $default = '') use (&$stored): string {
				return ($stored[$uid.'/'.$key] ?? $default);
			}
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('isInstalled')->with('openregister')->willReturn($preferences !== null);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($preferences);

		return new NotificationSwitchService(config: $config, appManager: $apps, container: $container, logger: $this->createMock(LoggerInterface::class));
	}//end service()

	/**
	 * Task 1.2: off stores false and writes `{"enabled": false}` for both assignment rules.
	 *
	 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
	 */
	public function testNotifyAssignedOffWritesOverrideForBothRules(): void {
		$stored = [];
		$spy    = new SwitchPreferenceSpy();
		$this->service(stored: $stored, preferences: $spy)->apply(userId: 'ben', data: ['notify_assigned' => 'false']);

		self::assertSame(['ben/notify_assigned' => 'false'], $stored);
		self::assertSame(
			[['ben', 'task', 'taskAssignedOnCreate', ['enabled' => false]], ['ben', 'task', 'taskAssigned', ['enabled' => false]]],
			array_map(static fn (array $call): array => [$call['userId'], $call['schemaSlug'], $call['notificationKey'], $call['override']], $spy->calls)
		);
	}//end testNotifyAssignedOffWritesOverrideForBothRules()

	/**
	 * Task 1.2: on stores true and clears both overrides; it is on by default.
	 *
	 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
	 */
	public function testNotifyAssignedOnClearsOverride(): void {
		$stored  = [];
		$spy     = new SwitchPreferenceSpy();
		$service = $this->service(stored: $stored, preferences: $spy);
		self::assertSame(['notify_assigned' => true], $service->values(userId: 'carl'), 'on by default');

		$service->apply(userId: 'carl', data: ['notify_assigned' => true]);

		self::assertSame(['carl/notify_assigned' => 'true'], $stored);
		self::assertSame([null, null], array_column($spy->calls, 'override'));
		self::assertSame(NotificationSwitchService::ASSIGNED_RULES, array_column($spy->calls, 'notificationKey'));
	}//end testNotifyAssignedOnClearsOverride()

	/**
	 * Without OpenRegister the value is stored and nothing else happens; a
	 * save without the key changes nothing.
	 *
	 * @spec openspec/changes/collaboration-notifications/tasks.md#task-1.2
	 */
	public function testWithoutOpenRegisterOnlyTheValueIsStored(): void {
		$stored  = [];
		$service = $this->service(stored: $stored, preferences: null);
		$service->apply(userId: 'dave', data: ['notify_due_reminder' => false]);
		self::assertSame([], $stored);

		$service->apply(userId: 'dave', data: ['notify_assigned' => false]);
		self::assertSame(['dave/notify_assigned' => 'false'], $stored);
		self::assertSame(['notify_assigned' => false], $service->values(userId: 'dave'));
	}//end testWithoutOpenRegisterOnlyTheValueIsStored()
}//end class

/**
 * Records OpenRegister override writes, with NotificationPreferenceService::setOverride's signature.
 */
class SwitchPreferenceSpy {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public array $calls = [];

	/**
	 * @param string                    $userId          The user.
	 * @param string                    $schemaSlug      The schema.
	 * @param string                    $notificationKey The rule.
	 * @param array<string,mixed>|null  $override        The override, null to clear.
	 *
	 * @return void
	 */
	public function setOverride(string $userId, string $schemaSlug, string $notificationKey, ?array $override): void {
		$this->calls[] = ['userId' => $userId, 'schemaSlug' => $schemaSlug, 'notificationKey' => $notificationKey, 'override' => $override];
	}//end setOverride()
}//end class
