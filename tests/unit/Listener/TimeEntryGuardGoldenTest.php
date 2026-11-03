<?php

/**
 * Golden test for how a time entry's author, project and rate terms are restamped.
 *
 * @category Test
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

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\TimeEntryGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Records the modified data over write kinds, sent data and actors.
 *
 * @covers \OCA\Planninq\Listener\TimeEntryGuardListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class TimeEntryGuardGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/time-entry-guard.golden.json';

	private const SENT = [
		'plain'        => ['task' => 'task-a', 'hours' => 2],
		'own'          => ['task' => 'task-a', 'user' => 'dave', 'project' => 'proj-a'],
		'forged user'  => ['task' => 'task-a', 'user' => 'mallory'],
		'blank user'   => ['task' => 'task-a', 'user' => ''],
		'number user'  => ['task' => 'task-a', 'user' => 7],
		'rates'        => ['task' => 'task-a', 'hourlyRate' => 120, 'billable' => true],
		'rates b'      => ['project' => 'proj-b', 'hourlyRate' => 90, 'billable' => false, 'user' => 'bob'],
		'wrong proj'   => ['task' => 'task-a', 'project' => 'proj-b'],
		'no project'   => ['hours' => 1],
		'stamped'      => ['task' => 'task-a', 'user' => 'dave', 'project' => 'proj-a', 'hourlyRate' => 0, 'billable' => null],
	];

	private const STORED = [
		'create'       => null,
		'stored dave'  => ['user' => 'dave', 'hourlyRate' => 80, 'billable' => true],
		'stored blank' => ['user' => '', 'hourlyRate' => 50],
		'stored none'  => [],
		'no old'       => 'no-old',
	];

	/**
	 * Actor => [uid, admin, groups].
	 */
	private const ACTORS = [
		'nobody'  => ['', false, []],
		'system'  => ['sys', false, []],
		'admin'   => ['root', true, []],
		'admin 2' => ['ada', true, []],
		'root'    => ['root', false, []],
		'owner'   => ['carol', false, []],
		'manager' => ['erin', false, []],
		'pmo'     => ['gwen', false, ['pmo']],
		'member'  => ['dave', false, ['staff']],
		'outside' => ['eve', false, []],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testRestampMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::SENT as $sent => $data) {
			foreach (self::STORED as $stored => $old) {
				foreach (self::ACTORS as $actor => $who) {
					$recorded[$sent . ' / ' . $stored . ' / ' . $actor] = $this->restamp(data: $data, old: $old, who: $who, system: ($actor === 'system'));
				}
			}
		}

		$this->seedProjects();
		$label = InMemoryObjectService::entity(uuid: 'x', data: ['task' => 'task-a'], register: '1', schema: $this->schemaId(slug: 'label'));
		$event = new ObjectCreatingEvent($label);
		$this->listener(who: self::ACTORS['member'])->handle($event);
		$recorded['another schema'] = $event->getModifiedData();

		$this->seedProjects();
		$event = new ObjectUpdatingEvent($label, $label);
		$this->listener(who: self::ACTORS['member'])->handle($event);
		$recorded['another schema, update'] = $event->getModifiedData();

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testRestampMatchesTheRecording()

	/**
	 * One write: the modified data it ends with.
	 *
	 * @param array<string,mixed>                        $data   The sent data.
	 * @param array<string,mixed>|string|null            $old    The stored data, null on a create, 'no-old' for an update without it.
	 * @param array{0:string,1:bool,2:array<int,string>} $who    The actor.
	 * @param bool                                       $system Whether it runs as a system operation.
	 *
	 * @return array<string,mixed>
	 */
	private function restamp(array $data, array|string|null $old, array $who, bool $system): array {
		$this->seedProjects();
		$schema = $this->schemaId(slug: 'plannedTimeEntry');
		$new    = InMemoryObjectService::entity(uuid: 'te-1', data: $data, register: '1', schema: $schema);
		$event  = new ObjectCreatingEvent($new);
		if ($old === 'no-old') {
			$event = new ObjectUpdatingEvent($new, null);
		} elseif (is_array($old) === true) {
			$event = new ObjectUpdatingEvent($new, InMemoryObjectService::entity(uuid: 'te-1', data: $old + ['task' => 'task-a'], register: '1', schema: $schema));
		}

		$event->setModifiedData(['note' => 'kept']);
		$listener = $this->listener(who: $who);
		if ($system === true) {
			SystemOperationContext::run(static fn () => $listener->handle($event));
		} else {
			$listener->handle($event);
		}

		return $event->getModifiedData();
	}//end restamp()

	/**
	 * Seed two projects and a task.
	 *
	 * @return void
	 */
	private function seedProjects(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob', 'dave'], 'managers' => ['erin'], 'managerGroups' => ['pmo']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'bob', 'members' => ['bob'], 'ownerGroups' => ['staff']]);
		$this->objects->seed('task', 'task-a', ['title' => 'Draft', 'status' => 'open', 'project' => 'proj-a']);
	}//end seedProjects()

	/**
	 * The listener acting as one actor; the group manager answers only for that actor.
	 *
	 * @param array{0:string,1:bool,2:array<int,string>} $who The actor.
	 *
	 * @return TimeEntryGuardListener
	 */
	private function listener(array $who): TimeEntryGuardListener {
		[$uid, $admin, $groupIds] = $who;
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => ($admin === true && $id === $uid));
		$groups->method('getUserGroupIds')->willReturnCallback(static fn (IUser $member): array => ($member->getUID() === $uid ? $groupIds : []));

		return new TimeEntryGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups
		);
	}//end listener()
}//end class
