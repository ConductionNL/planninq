<?php

/**
 * Tests for TimeEntryGuardListener: a time entry's author, project, rate and
 * billable flag are set by the server.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Listener
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

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\TimeEntryGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/specs/time-tracking/spec.md
 */
class TimeEntryGuardListenerTest extends TestCase {
	use MembershipFixture;

	private const ENTRY = ['task' => 'task-a', 'user' => 'bob', 'duration' => 90, 'date' => '2026-10-21', 'project' => 'proj-a', 'hourlyRate' => 0, 'billable' => null];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob', 'dave'], 'managers' => ['erin'], 'managerGroups' => ['pmo']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'bob', 'members' => ['bob']]);
		$this->objects->seed('task', 'task-a', ['title' => 'Draft', 'status' => 'open', 'project' => 'proj-a']);
	}//end setUp()

	/**
	 * The listener acting as the given user.
	 *
	 * @param string            $actor    The caller, '' for no session.
	 * @param bool              $isAdmin  Whether the caller is an admin.
	 * @param array<int,string> $groupIds The caller's groups.
	 *
	 * @return TimeEntryGuardListener
	 */
	private function listener(string $actor, bool $isAdmin = false, array $groupIds = []): TimeEntryGuardListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));
		$groups->method('getUserGroupIds')->willReturn($groupIds);

		return new TimeEntryGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups
		);
	}//end listener()

	/**
	 * A time entry object.
	 *
	 * @param array<string,mixed> $data   The data.
	 * @param string              $schema The schema slug.
	 *
	 * @return ObjectEntity
	 */
	private function entry(array $data, string $schema = 'plannedTimeEntry'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'te-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end entry()

	/**
	 * A member books time: the author is the caller, the project is the task's, and the rate terms are left to the project.
	 */
	public function testACreateIsStampedForTheCaller(): void {
		$sent  = ['user' => 'dave', 'project' => 'proj-b', 'hourlyRate' => 250, 'billable' => true] + self::ENTRY;
		$event = new ObjectCreatingEvent($this->entry(data: $sent));

		$this->listener(actor: 'bob')->handle($event);

		self::assertSame(['user' => 'bob', 'project' => 'proj-a', 'hourlyRate' => 0, 'billable' => null], $event->getModifiedData());
	}//end testACreateIsStampedForTheCaller()

	/**
	 * The owner, a manager and a manager group member may set the rate and billable flag.
	 */
	public function testAProjectManagerSetsTheRateTerms(): void {
		foreach ([['carol', []], ['erin', []], ['frank', ['pmo']]] as [$actor, $groups]) {
			$event = new ObjectCreatingEvent($this->entry(data: ['user' => $actor, 'hourlyRate' => 120, 'billable' => true] + self::ENTRY));
			$this->listener(actor: $actor, groupIds: $groups)->handle($event);
			self::assertSame([], $event->getModifiedData(), $actor);
		}
	}//end testAProjectManagerSetsTheRateTerms()

	/**
	 * An admin may book time for someone else.
	 */
	public function testAnAdminBooksForSomeoneElse(): void {
		$event = new ObjectCreatingEvent($this->entry(data: ['user' => 'dave', 'hourlyRate' => 99] + self::ENTRY));

		$this->listener(actor: 'root', isAdmin: true)->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testAnAdminBooksForSomeoneElse()

	/**
	 * An update keeps the stored author, rate and billable flag, whatever was sent or left out.
	 */
	public function testAnUpdateKeepsTheStoredAuthorAndTerms(): void {
		$stored = ['hourlyRate' => 80, 'billable' => true] + self::ENTRY;
		$cases  = [
			'handed over' => ['user' => 'dave', 'hourlyRate' => 500, 'billable' => false] + self::ENTRY,
			'PUT nulled'  => ['task' => 'task-a', 'duration' => 30, 'date' => '2026-10-21', 'user' => null, 'project' => null],
		];
		foreach ($cases as $case => $sent) {
			$event = new ObjectUpdatingEvent($this->entry(data: $sent), $this->entry(data: $stored));
			$this->listener(actor: 'bob')->handle($event);
			$changes = $event->getModifiedData();
			self::assertSame('bob', $changes['user'] ?? null, $case);
			self::assertSame(80, $changes['hourlyRate'] ?? null, $case);
			self::assertTrue($changes['billable'] ?? null, $case);
		}

		self::assertSame('proj-a', $changes['project'] ?? null, 'a nulled project is restored from the task');
	}//end testAnUpdateKeepsTheStoredAuthorAndTerms()

	/**
	 * An update that changes only the duration changes nothing else.
	 */
	public function testAPlainEditIsLeftAlone(): void {
		$event = new ObjectUpdatingEvent($this->entry(data: ['duration' => 30] + self::ENTRY), $this->entry(data: self::ENTRY));

		$this->listener(actor: 'bob')->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testAPlainEditIsLeftAlone()

	/**
	 * No session, and objects of other schemas, pass untouched.
	 */
	public function testSessionlessWritesAndOtherSchemasPass(): void {
		$sessionless = new ObjectCreatingEvent($this->entry(data: ['user' => 'dave', 'hourlyRate' => 250] + self::ENTRY));
		$this->listener(actor: '')->handle($sessionless);
		self::assertSame([], $sessionless->getModifiedData());

		$task = new ObjectCreatingEvent($this->entry(data: ['user' => 'dave', 'hourlyRate' => 250] + self::ENTRY, schema: 'task'));
		$this->listener(actor: 'bob')->handle($task);
		self::assertSame([], $task->getModifiedData());
	}//end testSessionlessWritesAndOtherSchemasPass()
}//end class
