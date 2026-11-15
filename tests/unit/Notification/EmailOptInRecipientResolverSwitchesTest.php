<?php

/**
 * Tests for which switches the email recipient resolvers read, and what they
 * leave alone: the exact user values asked for, the values that do and do not
 * count as "on", and an assignee that is no user id.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Notification
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Notification;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\Planninq\Notification\AssignmentEmailRecipientResolver;
use OCA\Planninq\Notification\DueSoonEmailRecipientResolver;
use OCA\Planninq\Notification\EmailOptInRecipientResolver;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */
#[CoversClass(EmailOptInRecipientResolver::class)]
#[CoversClass(AssignmentEmailRecipientResolver::class)]
#[CoversClass(DueSoonEmailRecipientResolver::class)]
class EmailOptInRecipientResolverSwitchesTest extends TestCase {

	/**
	 * Each resolver with the in-app switch its mail follows.
	 *
	 * @return array<string, array{0: class-string<EmailOptInRecipientResolver>, 1: string}>
	 */
	public static function resolvers(): array {
		return [
			'assignment' => [AssignmentEmailRecipientResolver::class, 'notify_assigned'],
			'due soon'   => [DueSoonEmailRecipientResolver::class, 'notify_due_reminder'],
		];
	}//end resolvers()

	/**
	 * The resolver asks the email switch (default off) and its own in-app
	 * switch (default on) of the assignee in planninq, then their account once.
	 *
	 * @param class-string<EmailOptInRecipientResolver> $class  The resolver.
	 * @param string                                    $switch Its in-app switch.
	 *
	 * @return void
	 */
	#[DataProvider('resolvers')]
	public function testItReadsTheAssigneesOwnSwitches(string $class, string $switch): void {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->exactly(2))->method('getUserValue')->willReturnCallback(
			static function (string $uid, string $app, string $key, string $default) use ($switch): string {
				self::assertSame('ben', $uid);
				self::assertSame('planninq', $app);
				self::assertContains([$key, $default], [['notify_by_email', 'false'], [$switch, 'true']]);
				return $default === 'false' ? 'true' : $default;
			}
		);
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('ben@example.org');
		$users = $this->createMock(IUserManager::class);
		$users->expects($this->once())->method('get')->with('ben')->willReturn($user);

		$resolver = new $class(config: $config, userManager: $users);

		$this->assertSame(['ben'], $resolver->resolve($this->task(assignee: 'ben'), ['trigger' => 'ignored']));
	}//end testItReadsTheAssigneesOwnSwitches()

	/**
	 * Only the literal "true" turns mail on; "1" or "yes" leave it off, and
	 * the account is not even looked up.
	 *
	 * @param class-string<EmailOptInRecipientResolver> $class The resolver.
	 *
	 * @return void
	 */
	#[DataProvider('resolvers')]
	public function testOnlyTheWordTrueTurnsMailOn(string $class): void {
		foreach (['1', 'yes', 'TRUE', ''] as $value) {
			$users = $this->createMock(IUserManager::class);
			$users->expects($this->never())->method('get');
			$resolver = new $class(config: $this->config(values: ['notify_by_email' => $value]), userManager: $users);

			$this->assertSame([], $resolver->resolve($this->task(assignee: 'ben'), []), 'email switch "' . $value . '"');
		}
	}//end testOnlyTheWordTrueTurnsMailOn()

	/**
	 * Only the literal "false" silences the in-app switch; any other value keeps the mail.
	 *
	 * @param class-string<EmailOptInRecipientResolver> $class  The resolver.
	 * @param string                                    $switch Its in-app switch.
	 *
	 * @return void
	 */
	#[DataProvider('resolvers')]
	public function testOnlyTheWordFalseSilencesTheInAppSwitch(string $class, string $switch): void {
		foreach (['0' => ['ben'], 'no' => ['ben'], 'true' => ['ben'], 'false' => []] as $value => $expected) {
			$resolver = new $class(config: $this->config(values: ['notify_by_email' => 'true', $switch => (string)$value]), userManager: $this->users());

			$this->assertSame($expected, $resolver->resolve($this->task(assignee: 'ben'), []), 'in-app switch "' . $value . '"');
		}
	}//end testOnlyTheWordFalseSilencesTheInAppSwitch()

	/**
	 * An assignee that is no user id (a number, a list, an empty string)
	 * reaches nobody, and no switch is read for it.
	 *
	 * @param class-string<EmailOptInRecipientResolver> $class The resolver.
	 *
	 * @return void
	 */
	#[DataProvider('resolvers')]
	public function testAnAssigneeThatIsNoUserIdReachesNobody(string $class): void {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->never())->method('getUserValue');
		$users = $this->createMock(IUserManager::class);
		$users->expects($this->never())->method('get');
		$resolver = new $class(config: $config, userManager: $users);

		foreach ([42, ['ben'], ''] as $assignee) {
			$this->assertSame([], $resolver->resolve(InMemoryObjectService::entity(uuid: 't-1', data: ['assignedTo' => $assignee]), []));
		}

		$this->assertSame([], $resolver->resolve(InMemoryObjectService::entity(uuid: 't-2', data: ['title' => 'No assignee']), []));
	}//end testAnAssigneeThatIsNoUserIdReachesNobody()

	/**
	 * Stored switches for the assignee "ben"; an unstored key answers its default.
	 *
	 * @param array<string, string> $values key => value.
	 *
	 * @return IConfig
	 */
	private function config(array $values): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app, string $key, string $default = ''): string => ($values[$key] ?? $default)
		);

		return $config;
	}//end config()

	/**
	 * A user manager that knows "ben" with an address.
	 *
	 * @return IUserManager
	 */
	private function users(): IUserManager {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('ben@example.org');
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturn($user);

		return $users;
	}//end users()

	/**
	 * A task assigned to someone.
	 *
	 * @param string $assignee The assignee's uid.
	 *
	 * @return ObjectEntity
	 */
	private function task(string $assignee): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 't-1', data: ['title' => 'Export to CSV', 'assignedTo' => $assignee]);
	}//end task()
}//end class
