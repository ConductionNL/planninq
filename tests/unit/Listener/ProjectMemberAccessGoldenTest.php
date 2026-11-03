<?php

/**
 * Golden test for who may write into which project and what the write is stamped with.
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
use OCA\Planninq\Listener\ProjectMemberAccessListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Records refusal, error and stamped lists over schemas, targets, write kinds and actors.
 *
 * @covers \OCA\Planninq\Listener\ProjectMemberAccessListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 * @uses   \OCA\Planninq\Service\ForgeLinkService
 */
class ProjectMemberAccessGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/project-member-access.golden.json';

	private const SCHEMAS = ['task', 'projectStatusReport', 'dependency', 'plannedTimeEntry', 'forgeLink', 'label'];

	private const TARGETS = [
		'a'          => ['project' => 'proj-a'],
		'c'          => ['project' => 'proj-c'],
		'missing'    => ['project' => 'proj-gone'],
		'none'       => [],
		'via-task'   => ['task' => 'task-in-a'],
		'a-stamped'  => ['project' => 'proj-a', 'members' => ['alice', 'bob', 'carol'], 'portfolioReaders' => []],
		'a-forged'   => ['project' => 'proj-a', 'members' => ['mallory'], 'memberGroups' => ['x']],
	];

	private const KINDS = ['create', 'update-same', 'update-from-b', 'update-no-old', 'update-from-none'];

	/**
	 * Actor => [uid, admin, groups].
	 */
	private const ACTORS = [
		'nobody'  => ['', false, []],
		'system'  => ['sys', false, []],
		'admin'   => ['root', true, []],
		'member'  => ['alice', false, []],
		'grouped' => ['gina', false, ['devs']],
		'viewer'  => ['vic', false, ['audit']],
		'outside' => ['eve', false, ['other']],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testAccessMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::SCHEMAS as $schema) {
			foreach (self::TARGETS as $target => $data) {
				foreach (self::KINDS as $kind) {
					foreach (self::ACTORS as $actor => $who) {
						$recorded[$schema . ' / ' . $target . ' / ' . $kind . ' / ' . $actor] = $this->write(schema: $schema, data: $data, kind: $kind, who: $who, system: ($actor === 'system'));
					}
				}
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testAccessMatchesTheRecording()

	/**
	 * One write, as recorded: refused or not, the error, and the modified data.
	 *
	 * @param string                                   $schema The schema slug.
	 * @param array<string,mixed>                      $data   The object data.
	 * @param string                                   $kind   The write kind.
	 * @param array{0:string,1:bool,2:array<int,string>} $who    The actor.
	 * @param bool                                     $system Whether it runs as a system operation.
	 *
	 * @return array<string,mixed>
	 */
	private function write(string $schema, array $data, string $kind, array $who, bool $system): array {
		$this->seedProjects();
		$event = $this->event(schema: $schema, data: $data, kind: $kind);
		$event->setModifiedData(['note' => 'kept']);
		$listener = $this->listener(who: $who);
		if ($system === true) {
			SystemOperationContext::run(static fn () => $listener->handle($event));
		} else {
			$listener->handle($event);
		}

		return [
			'stopped'  => $event->isPropagationStopped(),
			'errors'   => $event->getErrors(),
			'modified' => $event->getModifiedData(),
		];
	}//end run()

	/**
	 * The pre-save event of one write kind.
	 *
	 * @param string              $schema The schema slug.
	 * @param array<string,mixed> $data   The object data.
	 * @param string              $kind   The write kind.
	 *
	 * @return ObjectCreatingEvent|ObjectUpdatingEvent
	 */
	private function event(string $schema, array $data, string $kind): ObjectCreatingEvent|ObjectUpdatingEvent {
		$new = InMemoryObjectService::entity(uuid: 'obj-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
		$old = [
			'update-same'      => $data,
			'update-from-b'    => ['project' => 'proj-b'] + $data,
			'update-from-none' => ['note' => 'none'],
		];
		if ($kind === 'create') {
			return new ObjectCreatingEvent($new);
		}

		if ($kind === 'update-no-old') {
			return new ObjectUpdatingEvent($new, null);
		}

		$oldData = $old[$kind];
		if ($kind === 'update-from-b') {
			unset($oldData['task']);
			$oldData['project'] = 'proj-b';
		}

		return new ObjectUpdatingEvent($new, InMemoryObjectService::entity(uuid: 'obj-1', data: $oldData, register: '1', schema: $this->schemaId(slug: $schema)));
	}//end event()

	/**
	 * Seed the projects and the task the targets name.
	 *
	 * @return void
	 */
	private function seedProjects(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob', 'alice'], 'owner' => 'carol']);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'members' => ['dave', 'alice'], 'owner' => 'dave']);
		$this->objects->seed('project', 'proj-c', ['title' => 'C', 'members' => [], 'owner' => 'carol', 'managers' => ['erin'], 'viewers' => ['vic'], 'memberGroups' => ['devs'], 'ownerGroups' => ['pmo'], 'viewerGroups' => ['audit'], 'portfolioReaders' => ['pat', 'pat', '']]);
		$this->objects->seed('task', 'task-in-a', ['title' => 'T', 'status' => 'open', 'project' => 'proj-a']);
	}//end seedProjects()

	/**
	 * The listener acting as one actor; the group manager answers only for that actor.
	 *
	 * @param array{0:string,1:bool,2:array<int,string>} $who The actor.
	 *
	 * @return ProjectMemberAccessListener
	 */
	private function listener(array $who): ProjectMemberAccessListener {
		[$uid, $admin, $groupIds] = $who;
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => ($admin === true && $id === $uid));
		$groups->method('getUserGroupIds')->willReturnCallback(static fn (IUser $member): array => ($member->getUID() === $uid ? $groupIds : []));

		return new ProjectMemberAccessListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: new NullLogger()
		);
	}//end listener()
}//end class
