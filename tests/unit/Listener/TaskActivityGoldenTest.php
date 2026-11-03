<?php

/**
 * Golden test for the task activity listener: which subject, which parameters, for whom.
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

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\TaskActivityListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Records every published activity and log line over task changes, audiences and actors.
 *
 * @covers \OCA\Planninq\Listener\TaskActivityListener
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class TaskActivityGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/task-activity.golden.json';

	/**
	 * Actor => uid, '' for no session.
	 */
	private const ACTORS = [
		'nobody'   => '',
		'member'   => 'alice',
		'assignee' => 'carl',
		'outside'  => 'zed',
	];

	private const TASK = ['title' => 'Draft', 'status' => 'open', 'project' => 'proj', 'assignedTo' => 'carl', 'dueDate' => '2026-11-10', 'sharedWith' => ['dora']];

	/**
	 * Change name => [kind, sent data, stored data or null, schema slug, failing].
	 */
	private const CHANGES = [
		'created'               => ['created', self::TASK, null],
		'deleted'               => ['deleted', self::TASK, null],
		'status'                => ['updated', ['status' => 'done'] + self::TASK, self::TASK],
		'status and assignee'   => ['updated', ['status' => 'done', 'assignedTo' => 'bob'] + self::TASK, self::TASK],
		'assignee'              => ['updated', ['assignedTo' => 'bob'] + self::TASK, self::TASK],
		'assignee cleared'      => ['updated', ['assignedTo' => ''] + self::TASK, self::TASK],
		'assignee and due'      => ['updated', ['assignedTo' => 'bob', 'dueDate' => '2026-12-01'] + self::TASK, self::TASK],
		'due'                   => ['updated', ['dueDate' => '2026-12-01'] + self::TASK, self::TASK],
		'due cleared'           => ['updated', ['dueDate' => null] + self::TASK, self::TASK],
		'title only'            => ['updated', ['title' => 'Final'] + self::TASK, self::TASK],
		'status number'         => ['updated', ['status' => 3] + self::TASK, ['status' => '3'] + self::TASK],
		'status translated'     => ['updated', ['status' => ['nl' => 'klaar', 'en' => 'done']] + self::TASK, self::TASK],
		'status translated gb'  => ['updated', ['status' => ['en_GB' => 'done', 'nl' => 'klaar']] + self::TASK, self::TASK],
		'status translated nl'  => ['updated', ['status' => ['nl_NL' => 'klaar']] + self::TASK, self::TASK],
		'status other language' => ['updated', ['status' => ['de' => 'fertig']] + self::TASK, ['status' => ''] + self::TASK],
		'status nested'         => ['updated', ['status' => ['en' => ['x']]] + self::TASK, ['status' => ''] + self::TASK],
		'status bool'           => ['updated', ['status' => true] + self::TASK, ['status' => '1'] + self::TASK],
		'old status translated' => ['updated', self::TASK, ['status' => ['en' => 'open']] + self::TASK],
		'other event'           => ['creating', self::TASK, null],
		'old without fields'    => ['updated', self::TASK, []],
		'old null'              => ['updated', self::TASK, 'no-old'],
		'title translated'      => ['created', ['title' => ['nl' => 'Concept', 'en_US' => 'Draft US']] + self::TASK, null],
		'title missing'         => ['created', ['status' => 'open', 'project' => 'proj'], null],
		'title number'          => ['created', ['title' => 42] + self::TASK, null],
		'shared many'           => ['created', ['sharedWith' => ['dora', 'alice', '', 'dora', 'bob', 7, ['nl' => 'erik'], null]] + self::TASK, null],
		'shared text'           => ['created', ['sharedWith' => 'erik'] + self::TASK, null],
		'shared keyed'          => ['created', ['sharedWith' => ['x' => 'erik']] + self::TASK, null],
		'no shared'             => ['created', ['title' => 'T', 'project' => 'proj', 'assignedTo' => 'carl'], null],
		'assignee is member'    => ['created', ['assignedTo' => 'bob'] + self::TASK, null],
		'assignee translated'   => ['created', ['assignedTo' => ['en' => 'erik']] + self::TASK, null],
		'no project'            => ['created', ['project' => ''] + self::TASK, null],
		'project ref'           => ['created', ['project' => ['nl' => 'proj']] + self::TASK, null],
		'unknown project'       => ['created', ['project' => 'gone'] + self::TASK, null],
		'project without owner' => ['created', ['project' => 'loose'] + self::TASK, null],
		'project odd members'   => ['created', ['project' => 'odd'] + self::TASK, null],
		'not a task'            => ['created', self::TASK, null, 'label'],
		'other register'        => ['created', self::TASK, null, 'task@9'],
		'publish fails'         => ['created', self::TASK, null, 'task', 'publish'],
		'event fails'           => ['created', self::TASK, null, 'task', 'generate'],
		'subject fails'         => ['updated', ['status' => 'done'] + self::TASK, self::TASK, 'task', 'subject'],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testActivityMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::CHANGES as $change => $spec) {
			foreach (self::ACTORS as $actor => $uid) {
				$recorded[$change . ' / ' . $actor] = $this->change(spec: $spec, uid: $uid);
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
	}//end testActivityMatchesTheRecording()

	/**
	 * A numeric user id gets the entry, and so does everyone after it.
	 *
	 * @return void
	 */
	public function testANumericUserIdDoesNotStopTheOthersEntries(): void {
		$result = $this->change(spec: ['created', ['project' => '', 'assignedTo' => '', 'sharedWith' => ['7', 'erik']] + self::TASK, null], uid: 'alice');

		self::assertSame(expected: [], actual: $result['log']);
		self::assertSame(expected: [['7'], ['erik']], actual: array_column($result['published'], 'setAffectedUser'));
	}//end testANumericUserIdDoesNotStopTheOthersEntries()

	/**
	 * The author is the session user, also when the task names another owner.
	 *
	 * @return void
	 */
	public function testTheAuthorIsTheSessionUserNotTheTaskOwner(): void {
		$result = $this->change(spec: ['created', ['owner' => 'mallory', 'project' => 'proj'] + self::TASK, null], uid: 'alice');

		self::assertNotSame(expected: [], actual: $result['published']);
		foreach ($result['published'] as $entry) {
			self::assertSame(expected: ['alice'], actual: $entry['setAuthor']);
			self::assertNotSame(expected: ['alice'], actual: $entry['setAffectedUser']);
		}
	}//end testTheAuthorIsTheSessionUserNotTheTaskOwner()

	/**
	 * One change: the published activities and the log lines.
	 *
	 * @param array<int,mixed> $spec The change.
	 * @param string           $uid  The actor, '' for no session.
	 *
	 * @return array<string,mixed>
	 */
	private function change(array $spec, string $uid): array {
		[$kind, $data, $old] = $spec;
		[$slug, $register] = [($spec[3] ?? 'task'), '1'];
		if ($slug === 'task@9') {
			[$slug, $register] = ['task', '9'];
		}

		$this->seed();
		$schema = $this->schemaId(slug: $slug);
		$entity = InMemoryObjectService::entity(uuid: 'task-1', data: $data, register: $register, schema: $schema);
		$event  = match ($kind) {
			'created' => new ObjectCreatedEvent($entity),
			'deleted' => new ObjectDeletedEvent($entity),
			'creating' => new ObjectCreatingEvent($entity),
			default   => new ObjectUpdatedEvent($entity, $old === 'no-old' ? null : InMemoryObjectService::entity(uuid: 'task-1', data: (array)$old, register: $register, schema: $schema)),
		};

		$logger    = $this->logger();
		$published = [];
		$this->listener(uid: $uid, logger: $logger, fails: (string)($spec[4] ?? ''), published: $published)->handle($event);

		return ['published' => $published, 'log' => $logger->lines];
	}//end change()

	/**
	 * Seed the projects; find() reads the project schema when none is given, as the resolver sets it.
	 *
	 * @return void
	 */
	private function seed(): void {
		$this->objects = new class extends InMemoryObjectService {
			/**
			 * Find in the project schema when the call names none.
			 *
			 * @param int|string $id            The id.
			 * @param array|null $_extend       Unused.
			 * @param bool       $files         Unused.
			 * @param mixed      $register      The register.
			 * @param mixed      $schema        The schema, project when null.
			 * @param bool       $_rbac         Unused.
			 * @param bool       $_multitenancy Unused.
			 * @param bool       $_render       Unused.
			 * @param bool       $_audit        Unused.
			 *
			 * @return object|null
			 */
			public function find(int|string $id, ?array $_extend=[], bool $files=false, mixed $register=null, mixed $schema=null, bool $_rbac=true, bool $_multitenancy=true, bool $_render=true, bool $_audit=true): ?object {
				return parent::find(id: $id, register: $register, schema: ($schema ?? 'project'));
			}//end find()
		};
		$this->objects->seed('project', 'proj', ['title' => 'P', 'owner' => 'olga', 'members' => ['alice', 'bob']]);
		$this->objects->seed('project', 'loose', ['title' => 'L', 'members' => ['bob'], 'owner' => '']);
		$this->objects->seed('project', 'odd', ['title' => 'O', 'members' => 'alice', 'owner' => 7]);
	}//end seed()

	/**
	 * A logger that keeps every line.
	 *
	 * @return AbstractLogger
	 */
	private function logger(): AbstractLogger {
		return new class extends AbstractLogger {
			/**
			 * The lines, in order.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $lines = [];

			/**
			 * Keep one line.
			 *
			 * @param mixed              $level   The level.
			 * @param string|\Stringable $message The message.
			 * @param array<mixed>       $context The context.
			 *
			 * @return void
			 */
			public function log($level, string|\Stringable $message, array $context=[]): void {
				// A type error names the argument position, file and line; keep only what went wrong.
				if (is_string($context['exception'] ?? null) === true && preg_match('/must be of type \S+, \S+ given/', $context['exception'], $match) === 1) {
					$context['exception'] = $match[0];
				}

				$this->lines[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}//end log()
		};
	}//end logger()

	/**
	 * The listener for one actor; every published activity lands in $published.
	 *
	 * @param string                          $uid       The actor, '' for no session.
	 * @param AbstractLogger                  $logger    The logger.
	 * @param string                          $fails     Which activity call throws: publish, generate, subject or ''.
	 * @param array<int,array<string,mixed>> $published The published activities.
	 *
	 * @return TaskActivityListener
	 */
	private function listener(string $uid, AbstractLogger $logger, string $fails, array &$published): TaskActivityListener {
		$calls = [];
		$event = $this->createMock(originalClassName: IEvent::class);
		foreach (['setApp', 'setType', 'setAuthor', 'setTimestamp', 'setSubject', 'setObject', 'setAffectedUser'] as $setter) {
			$event->method($setter)->willReturnCallback(static function (...$args) use ($event, $setter, $fails, &$calls): IEvent {
				if ($setter === 'setSubject' && $fails === 'subject') {
					throw new \RuntimeException('subject refused');
				}

				$calls[$setter] = ($setter === 'setTimestamp' ? is_int($args[0]) : $args);
				return $event;
			});
		}

		$manager = $this->createMock(originalClassName: IActivityManager::class);
		$manager->method('generateEvent')->willReturnCallback(static function () use ($event, $fails, &$calls): IEvent {
			if ($fails === 'generate') {
				throw new \RuntimeException('no event');
			}

			$calls = [];
			return $event;
		});
		$manager->method('publish')->willReturnCallback(static function () use ($fails, &$calls, &$published): void {
			if ($fails === 'publish' && ($calls['setAffectedUser'][0] ?? '') === 'bob') {
				throw new \RuntimeException('publish refused for bob');
			}

			$published[] = $calls;
		});

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);

		return new TaskActivityListener(
			activityManager: $manager,
			userSession: $session,
			scopeResolver: $this->scopeResolver(),
			logger: $logger
		);
	}//end listener()
}//end class
