<?php

/**
 * Tests for the email recipient resolvers: the assignee gets mail only when
 * they opted in, kept the matching in-app switch on and have an address.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Notification;

use OCA\OpenRegister\Service\Notification\RecipientResolverInterface;
use OCA\Planninq\Notification\AssignmentEmailRecipientResolver;
use OCA\Planninq\Notification\DueSoonEmailRecipientResolver;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
 */
class EmailOptInRecipientResolverTest extends TestCase {

	/**
	 * A resolver over stored user values and accounts.
	 *
	 * @param string                               $class    Resolver class.
	 * @param array<string, array<string, string>> $values   uid => key => value.
	 * @param array<string, string>                $mailOf   uid => email address.
	 *
	 * @return RecipientResolverInterface
	 */
	private function resolver(string $class, array $values, array $mailOf): RecipientResolverInterface {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app, string $key, string $default = ''): string => ($values[$uid][$key] ?? $default)
		);
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(
			function (string $uid) use ($mailOf): ?IUser {
				if (array_key_exists($uid, $mailOf) === false) {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getEMailAddress')->willReturn($mailOf[$uid] === '' ? null : $mailOf[$uid]);
				return $user;
			}
		);

		return new $class(config: $config, userManager: $users);
	}//end resolver()

	/**
	 * @param string|null $assignee The task's assignee.
	 *
	 * @return \OCA\OpenRegister\Db\ObjectEntity
	 */
	private function task(?string $assignee): \OCA\OpenRegister\Db\ObjectEntity {
		return InMemoryObjectService::entity(uuid: 't-1', data: ['title' => 'Export to CSV', 'assignedTo' => $assignee]);
	}//end task()

	/**
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	public function testOptedInAssigneeIsReturned(): void {
		foreach ([AssignmentEmailRecipientResolver::class, DueSoonEmailRecipientResolver::class] as $class) {
			$resolver = $this->resolver(class: $class, values: ['ben' => ['notify_by_email' => 'true']], mailOf: ['ben' => 'ben@example.org']);
			self::assertSame(['ben'], $resolver->resolve($this->task(assignee: 'ben'), []), $class);
		}
	}//end testOptedInAssigneeIsReturned()

	/**
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	public function testOptedOutAssigneeIsNotReturned(): void {
		foreach ([AssignmentEmailRecipientResolver::class, DueSoonEmailRecipientResolver::class] as $class) {
			$never = $this->resolver(class: $class, values: [], mailOf: ['carl' => 'carl@example.org']);
			self::assertSame([], $never->resolve($this->task(assignee: 'carl'), []), $class.': email is off by default');
			$off = $this->resolver(class: $class, values: ['carl' => ['notify_by_email' => 'false']], mailOf: ['carl' => 'carl@example.org']);
			self::assertSame([], $off->resolve($this->task(assignee: 'carl'), []), $class);
			self::assertSame([], $off->resolve($this->task(assignee: null), []), $class.': no assignee, nobody');
		}
	}//end testOptedOutAssigneeIsNotReturned()

	/**
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	public function testUserWithoutEmailIsNotReturned(): void {
		$values = ['ben' => ['notify_by_email' => 'true'], 'gone' => ['notify_by_email' => 'true']];
		$resolver = $this->resolver(class: AssignmentEmailRecipientResolver::class, values: $values, mailOf: ['ben' => '']);
		self::assertSame([], $resolver->resolve($this->task(assignee: 'ben'), []), 'no address');
		self::assertSame([], $resolver->resolve($this->task(assignee: 'gone'), []), 'no account');
	}//end testUserWithoutEmailIsNotReturned()

	/**
	 * @spec openspec/changes/archive/2026-09-30-collaboration-notifications/tasks.md#task-2.2
	 */
	public function testInAppSwitchOffSilencesMatchingMail(): void {
		$mail = ['ben' => 'ben@example.org'];
		$assignedOff = ['ben' => ['notify_by_email' => 'true', 'notify_assigned' => 'false']];
		self::assertSame([], $this->resolver(class: AssignmentEmailRecipientResolver::class, values: $assignedOff, mailOf: $mail)->resolve($this->task(assignee: 'ben'), []));
		self::assertSame(['ben'], $this->resolver(class: DueSoonEmailRecipientResolver::class, values: $assignedOff, mailOf: $mail)->resolve($this->task(assignee: 'ben'), []), 'the other switch does not silence it');

		$dueOff = ['ben' => ['notify_by_email' => 'true', 'notify_due_reminder' => 'false']];
		self::assertSame([], $this->resolver(class: DueSoonEmailRecipientResolver::class, values: $dueOff, mailOf: $mail)->resolve($this->task(assignee: 'ben'), []));
		self::assertSame(['ben'], $this->resolver(class: AssignmentEmailRecipientResolver::class, values: $dueOff, mailOf: $mail)->resolve($this->task(assignee: 'ben'), []));
	}//end testInAppSwitchOffSilencesMatchingMail()
}//end class
