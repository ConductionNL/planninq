<?php

/**
 * Tests for TaskReferenceGuardListener: a task's references and people stay inside its project.
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
use OCA\Planninq\Listener\TaskReferenceGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/specs/tasks/spec.md
 */
class TaskReferenceGuardListenerTest extends TestCase {
	use MembershipFixture;

	private const TASK = ['title' => 'Draft the letter', 'status' => 'open', 'project' => 'proj-a', 'issueType' => 'task'];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob'], 'viewers' => ['vera'], 'memberGroups' => ['team-a']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'dave', 'members' => ['dave']]);
		$this->objects->seed('task', 'epic-a', ['title' => 'Portal', 'status' => 'open', 'project' => 'proj-a', 'issueType' => 'epic']);
		$this->objects->seed('task', 'epic-b', ['title' => 'Secret', 'status' => 'open', 'project' => 'proj-b', 'issueType' => 'epic']);
		$this->objects->seed('task', 'story-a', ['title' => 'Login', 'status' => 'open', 'project' => 'proj-a', 'issueType' => 'story']);
		$this->objects->seed('column', 'col-a', ['title' => 'Todo', 'project' => 'proj-a']);
		$this->objects->seed('column', 'col-b', ['title' => 'Todo', 'project' => 'proj-b']);
		$this->objects->seed('projectRelease', 'rel-b', ['title' => '1.0', 'project' => 'proj-b']);
	}//end setUp()

	/**
	 * The listener acting as the given user.
	 *
	 * @param string $actor The caller, '' for no session.
	 *
	 * @return TaskReferenceGuardListener
	 */
	private function listener(string $actor = 'bob'): TaskReferenceGuardListener {
		$caller = $this->createMock(originalClassName: IUser::class);
		$caller->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $caller);

		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			if (in_array($uid, ['bob', 'carol', 'dave', 'vera', 'gina', 'eve'], true) === false) {
				return null;
			}

			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		});
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturnCallback(static fn (IUser $user): array => ($user->getUID() === 'gina' ? ['team-a'] : []));

		return new TaskReferenceGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			userManager: $users,
			groupManager: $groups
		);
	}//end listener()

	/**
	 * A task object.
	 *
	 * @param array<string,mixed> $data The data.
	 * @param string              $uuid The UUID.
	 *
	 * @return ObjectEntity
	 */
	private function task(array $data, string $uuid = 'task-1'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: 'task'));
	}//end task()

	/**
	 * Run a create and say whether it was refused.
	 *
	 * @param array<string,mixed> $extra Fields on top of the base task.
	 * @param string              $actor The caller.
	 *
	 * @return bool
	 */
	private function refusesCreate(array $extra, string $actor = 'bob'): bool {
		$event = new ObjectCreatingEvent($this->task(data: $extra + self::TASK));
		$this->listener(actor: $actor)->handle($event);
		if ($event->isPropagationStopped() === true) {
			self::assertSame(TaskReferenceGuardListener::ERROR_CODE, $event->getErrors()['code']);
		}

		return $event->isPropagationStopped();
	}//end refusesCreate()

	/**
	 * Another project's column, epic or release is refused; the task's own project's pass.
	 */
	public function testReferencesStayInTheTasksProject(): void {
		self::assertTrue($this->refusesCreate(extra: ['column' => 'col-b']), 'another project\'s column');
		self::assertTrue($this->refusesCreate(extra: ['epic' => 'epic-b']), 'another project\'s epic');
		self::assertTrue($this->refusesCreate(extra: ['release' => 'rel-b']), 'another project\'s release');
		self::assertTrue($this->refusesCreate(extra: ['parent' => 'missing']), 'a parent that does not exist');
		self::assertFalse($this->refusesCreate(extra: ['column' => 'col-a', 'epic' => 'epic-a', 'parent' => 'story-a']));
	}//end testReferencesStayInTheTasksProject()

	/**
	 * Only an epic holds tasks as an epic, and an epic sits in no epic.
	 */
	public function testEpicRules(): void {
		self::assertTrue($this->refusesCreate(extra: ['epic' => 'story-a']), 'a story is not an epic');
		self::assertTrue($this->refusesCreate(extra: ['issueType' => 'epic', 'epic' => 'epic-a']), 'an epic in an epic');
	}//end testEpicRules()

	/**
	 * Responsible and sharing people must work on the project, in person or through a group.
	 */
	public function testPeopleMustWorkOnTheProject(): void {
		self::assertTrue($this->refusesCreate(extra: ['assignedTo' => 'eve']), 'someone outside the project');
		self::assertTrue($this->refusesCreate(extra: ['assignedTo' => 'vera']), 'a viewer does not work on it');
		self::assertTrue($this->refusesCreate(extra: ['sharedWith' => ['bob', 'dave']]), 'another project\'s member');
		self::assertTrue($this->refusesCreate(extra: ['assignedTo' => 'nobody']), 'an unknown user');
		self::assertFalse($this->refusesCreate(extra: ['assignedTo' => 'carol', 'sharedWith' => ['bob', 'gina']]), 'the owner, a member and a member group');
	}//end testPeopleMustWorkOnTheProject()

	/**
	 * An update is held only to what it changes, so older data does not block an edit.
	 */
	public function testAnUpdateChecksOnlyWhatItChanges(): void {
		$stored = ['column' => 'col-b', 'assignedTo' => 'eve'] + self::TASK;

		$rename = new ObjectUpdatingEvent($this->task(data: ['title' => 'Send the letter'] + $stored), $this->task(data: $stored));
		$this->listener()->handle($rename);
		self::assertFalse($rename->isPropagationStopped());

		$move = new ObjectUpdatingEvent($this->task(data: ['column' => 'col-b', 'release' => 'rel-b'] + $stored), $this->task(data: $stored));
		$this->listener()->handle($move);
		self::assertTrue($move->isPropagationStopped());
	}//end testAnUpdateChecksOnlyWhatItChanges()

	/**
	 * occ, cron and repair steps are not held back.
	 */
	public function testNoSessionPasses(): void {
		self::assertFalse($this->refusesCreate(extra: ['column' => 'col-b', 'assignedTo' => 'eve'], actor: ''));
	}//end testNoSessionPasses()
}//end class
