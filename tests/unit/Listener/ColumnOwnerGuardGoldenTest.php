<?php

/**
 * Golden test for who may change or remove a board column.
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
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\ColumnOwnerGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Records refusal and error over column states, write kinds and actors.
 *
 * @covers \OCA\Planninq\Listener\ColumnOwnerGuardListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class ColumnOwnerGuardGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/column-owner-guard.golden.json';

	private const COLUMN = ['title' => 'Doing', 'project' => 'proj-a', 'order' => 1, 'members' => ['bob', 'carol']];

	/**
	 * State => [the column's data, the column's uuid, extra rows [schema, uuid, data]].
	 */
	private const STATES = [
		'plain'            => [self::COLUMN, 'col-1', []],
		'with cards'       => [self::COLUMN, 'col-1', [['task', 't-1', ['project' => 'proj-a', 'column' => 'col-1']]]],
		'last done, tasks' => [['type' => 'done'] + self::COLUMN, 'col-1', [['task', 't-1', ['project' => 'proj-a', 'column' => 'col-0']]]],
		'last done, empty' => [['type' => 'done'] + self::COLUMN, 'col-1', []],
		'other done'       => [['type' => 'done'] + self::COLUMN, 'col-1', [['column', 'col-2', ['type' => 'done', 'project' => 'proj-a']], ['task', 't-1', ['project' => 'proj-a', 'column' => 'col-0']]]],
		'done elsewhere'   => [['type' => 'done'] + self::COLUMN, 'col-1', [['column', 'col-9', ['type' => 'done', 'project' => 'proj-b']], ['task', 't-1', ['project' => 'proj-a', 'column' => 'col-0']]]],
		'self listed done' => [['type' => 'done'] + self::COLUMN, 'col-1', [['column', 'col-1', ['type' => 'done', 'project' => 'proj-a']], ['task', 't-1', ['project' => 'proj-a', 'column' => 'col-0']]]],
		'plain, tasks'     => [self::COLUMN, 'col-1', [['task', 't-1', ['project' => 'proj-a', 'column' => 'col-0']]]],
		'last done, cards' => [['type' => 'done'] + self::COLUMN, 'col-1', [['task', 't-1', ['project' => 'proj-a', 'column' => 'col-1']]]],
		'no uuid, cards'   => [self::COLUMN, '', [['task', 't-1', ['project' => 'proj-a', 'column' => '']]]],
		'no project'       => [['project' => ''] + self::COLUMN, 'col-1', [['task', 't-1', ['project' => '', 'column' => 'col-1']]]],
		'other project'    => [['project' => 'proj-b'] + self::COLUMN, 'col-1', [['task', 't-1', ['project' => 'proj-b', 'column' => 'col-1']]]],
	];

	private const KINDS = ['create', 'update', 'update members only', 'update no old', 'delete', 'other schema'];

	/**
	 * Actor => [uid, admin].
	 */
	private const ACTORS = [
		'nobody' => ['', false],
		'system' => ['sys', false],
		'admin'  => ['root', true],
		'owner'  => ['carol', false],
		'member' => ['bob', false],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testGuardMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::STATES as $state => $column) {
			foreach (self::KINDS as $kind) {
				foreach (self::ACTORS as $actor => $who) {
					$recorded[$state . ' / ' . $kind . ' / ' . $actor] = $this->attempt(column: $column, kind: $kind, who: $who, system: ($actor === 'system'));
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
	}//end testGuardMatchesTheRecording()

	/**
	 * One write: refused or not, and the error.
	 *
	 * @param array{0:array<string,mixed>,1:string,2:array<int,array{0:string,1:string,2:array<string,mixed>}>} $column The column state.
	 * @param string                                                                                         $kind   The write kind.
	 * @param array{0:string,1:bool}                                                                          $who    The actor.
	 * @param bool                                                                                            $system Whether it runs as a system operation.
	 *
	 * @return array<string,mixed>
	 */
	private function attempt(array $column, string $kind, array $who, bool $system): array {
		[$data, $uuid, $rows] = $column;
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob'], 'owner' => 'carol']);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'members' => ['bob'], 'owner' => 'bob']);
		foreach ($rows as [$schema, $id, $row]) {
			$this->objects->seed($schema, $id, $row);
		}

		$schema = $this->schemaId(slug: ($kind === 'other schema' ? 'label' : 'column'));
		$entity = InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $schema);
		$event  = match ($kind) {
			'create', 'other schema' => new ObjectCreatingEvent($entity),
			'update'                 => new ObjectUpdatingEvent($entity, InMemoryObjectService::entity(uuid: $uuid, data: ['title' => 'Old'] + $data, register: '1', schema: $schema)),
			'update members only'    => new ObjectUpdatingEvent($entity, InMemoryObjectService::entity(uuid: $uuid, data: ['members' => ['zed']] + $data, register: '1', schema: $schema)),
			'update no old'          => new ObjectUpdatingEvent($entity, null),
			'delete'                 => new ObjectDeletingEvent($entity),
		};

		$listener = $this->listener(who: $who);
		if ($system === true) {
			SystemOperationContext::run(static fn () => $listener->handle($event));
		} else {
			$listener->handle($event);
		}

		return ['stopped' => $event->isPropagationStopped(), 'errors' => $event->getErrors()];
	}//end attempt()

	/**
	 * The listener acting as one actor; the group manager answers only for that actor.
	 *
	 * @param array{0:string,1:bool} $who The actor.
	 *
	 * @return ColumnOwnerGuardListener
	 */
	private function listener(array $who): ColumnOwnerGuardListener {
		[$uid, $admin] = $who;
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => ($admin === true && $id === $uid));

		return new ColumnOwnerGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: new NullLogger()
		);
	}//end listener()
}//end class
