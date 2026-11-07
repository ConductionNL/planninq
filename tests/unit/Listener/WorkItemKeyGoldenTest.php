<?php

/**
 * Golden test for the work item key listener: task numbers, kept task keys, project key changes.
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
require_once __DIR__ . '/../Support/InMemoryLockingProvider.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\WorkItemKeyListener;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryLockingProvider;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Records errors, stops, modified data, writes, queued jobs and log lines over writes and callers.
 *
 * @covers \OCA\Planninq\Listener\WorkItemKeyListener
 * @uses   \OCA\Planninq\Service\WorkItemKeyService
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class WorkItemKeyGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/work-item-key.golden.json';

	/**
	 * Caller => uid, '' for no session.
	 */
	private const CALLERS = [
		'nobody' => '',
		'system' => 'sys',
		'person' => 'carol',
	];

	private const NUMBERED = ['title' => 'V', 'key' => 'VERG', 'nextTaskNumber' => 42];

	private const FRESH = ['title' => 'F', 'key' => 'FRSH', 'nextTaskNumber' => 1];

	private const KEYLESS = ['title' => 'H'];

	/**
	 * Write name => [kind, schema slug, uuid, sent data, stored data or null].
	 */
	private const WRITES = [
		'task create'             => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'verg'], null],
		'task create ref'         => ['create', 'task', 't-new', ['title' => 'T', 'project' => ['id' => 'verg']], null],
		'task create ref number'  => ['create', 'task', 't-new', ['title' => 'T', 'project' => ['id' => 5]], null],
		'task create number'      => ['create', 'task', 't-new', ['title' => 'T', 'project' => 5], null],
		'task create keyless'     => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'hand'], null],
		'task create missing'     => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'missing'], null],
		'task create no project'  => ['create', 'task', 't-new', ['title' => 'T'], null],
		'task create counter 0'   => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'zero'], null],
		'task create sent key'    => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'verg', 'key' => ' OWN-1 '], null],
		'task create sent blank'  => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'verg', 'key' => '  '], null],
		'task create sent nokey'  => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'hand', 'key' => 'OWN-1'], null],
		'task create sent noproj' => ['create', 'task', 't-new', ['title' => 'T', 'key' => 'OWN-1'], null],
		'task create locked'      => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'locked'], null],
		'task create locked sent' => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'locked', 'key' => 'OWN-1'], null],
		'task create blank nokey' => ['create', 'task', 't-new', ['title' => 'T', 'project' => 'hand', 'key' => '  '], null],
		'task update same'        => ['update', 'task', 't-1', ['title' => 'T2', 'key' => 'VERG-3'], ['title' => 'T', 'key' => 'VERG-3']],
		'task update changed'     => ['update', 'task', 't-1', ['title' => 'T', 'key' => 'VERG-99'], ['title' => 'T', 'key' => 'VERG-3']],
		'task update dropped'     => ['update', 'task', 't-1', ['title' => 'T'], ['title' => 'T', 'key' => 'VERG-3']],
		'task update blank'       => ['update', 'task', 't-1', ['title' => 'T', 'key' => ''], ['title' => 'T']],
		'task update set'         => ['update', 'task', 't-1', ['title' => 'T', 'key' => 'X-1'], ['title' => 'T']],
		'task update no old'      => ['update', 'task', 't-1', ['title' => 'T', 'key' => 'X-1'], 'no-old'],
		'task delete'             => ['delete', 'task', 't-1', ['title' => 'T', 'key' => 'X-1'], null],
		'project create'          => ['create', 'project', 'p-new', ['title' => 'N', 'key' => 'NEW'], null],
		'project create lower'    => ['create', 'project', 'p-new', ['title' => 'N', 'key' => ' new2 '], null],
		'project create bad'      => ['create', 'project', 'p-new', ['title' => 'N', 'key' => '1AB'], null],
		'project create long'     => ['create', 'project', 'p-new', ['title' => 'N', 'key' => 'ABCDEFGHIJK'], null],
		'project create taken'    => ['create', 'project', 'p-new', ['title' => 'N', 'key' => 'verg'], null],
		'project create no key'   => ['create', 'project', 'p-new', ['title' => 'N'], null],
		'project create null key' => ['create', 'project', 'p-new', ['title' => 'N', 'key' => null], null],
		'project create blank'    => ['create', 'project', 'p-new', ['title' => 'N', 'key' => ''], null],
		'project create counter'  => ['create', 'project', 'p-new', ['title' => 'N', 'key' => 'NEW', 'nextTaskNumber' => 99], null],
		'project create no uuid'  => ['create', 'project', '', ['title' => 'N', 'key' => 'VERG'], null],
		'update key left out'     => ['update', 'project', 'verg', ['title' => 'V2'], self::NUMBERED],
		'update key left out new' => ['update', 'project', 'hand', ['title' => 'H2'], self::KEYLESS],
		'update key cleared'      => ['update', 'project', 'verg', ['title' => 'V', 'key' => null] + self::NUMBERED, self::NUMBERED],
		'update key blanked'      => ['update', 'project', 'verg', ['key' => ' '] + self::NUMBERED, self::NUMBERED],
		'update key cleared fresh' => ['update', 'project', 'frsh', ['key' => null] + self::FRESH, self::FRESH],
		'update key same'         => ['update', 'project', 'verg', ['title' => 'V2'] + self::NUMBERED, self::NUMBERED],
		'update key same lower'   => ['update', 'project', 'verg', ['key' => 'verg'] + self::NUMBERED, self::NUMBERED],
		'update key fixed'        => ['update', 'project', 'verg', ['key' => 'VNEW'] + self::NUMBERED, self::NUMBERED],
		'update key fresh change' => ['update', 'project', 'frsh', ['key' => 'FNEW'] + self::FRESH, self::FRESH],
		'update key fresh bad'    => ['update', 'project', 'frsh', ['key' => 'F'] + self::FRESH, self::FRESH],
		'update key fresh taken'  => ['update', 'project', 'frsh', ['key' => 'VERG'] + self::FRESH, self::FRESH],
		'update key first'        => ['update', 'project', 'hand', ['key' => 'HAND'] + self::KEYLESS, self::KEYLESS],
		'update key first lower'  => ['update', 'project', 'hand', ['key' => 'hand'] + self::KEYLESS, self::KEYLESS],
		'update key first numbered' => ['update', 'project', 'hand', ['key' => 'HNEW', 'nextTaskNumber' => 5, 'title' => 'H'], ['title' => 'H', 'nextTaskNumber' => 5]],
		'update key to own stored' => ['update', 'project', 'verg', ['key' => 'VERG', 'nextTaskNumber' => 1, 'title' => 'V'], ['key' => 'VOLD', 'nextTaskNumber' => 1, 'title' => 'V']],
		'update key first taken'  => ['update', 'project', 'hand', ['key' => 'FRSH'] + self::KEYLESS, self::KEYLESS],
		'update key first no uuid' => ['update', 'project', '', ['key' => 'HAND'] + self::KEYLESS, self::KEYLESS],
		'update key no old'       => ['update', 'project', 'hand', ['key' => 'HAND', 'title' => 'H'], 'no-old'],
		'update counter tampered' => ['update', 'project', 'verg', ['nextTaskNumber' => 1] + self::NUMBERED, self::NUMBERED],
		'update counter dropped'  => ['update', 'project', 'verg', ['title' => 'V', 'key' => 'VERG'], self::NUMBERED],
		'update counter string'   => ['update', 'project', 'verg', ['nextTaskNumber' => '42'] + self::NUMBERED, self::NUMBERED],
		'update stored key lower' => ['update', 'project', 'low', ['title' => 'L'], ['title' => 'L', 'key' => 'low', 'nextTaskNumber' => 3]],
		'update stored key blank' => ['update', 'project', 'low', ['title' => 'L'], ['title' => 'L', 'key' => '  ', 'nextTaskNumber' => 3]],
		'project delete'          => ['delete', 'project', 'verg', self::NUMBERED, null],
		'label write'             => ['create', 'label', 'l-1', ['title' => 'L', 'key' => 'bad key', 'project' => 'verg'], null],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testKeysMatchTheRecording(): void {
		$recorded = [];
		foreach (self::WRITES as $write => $spec) {
			foreach (self::CALLERS as $caller => $uid) {
				$recorded[$write . ' / ' . $caller] = $this->write(spec: $spec, uid: $uid, system: ($caller === 'system'));
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
	}//end testKeysMatchTheRecording()

	/**
	 * One write: what the listener did.
	 *
	 * @param array<int,mixed> $spec   The write.
	 * @param string           $uid    The caller, '' for no session.
	 * @param bool             $system Whether it runs as a system operation.
	 *
	 * @return array<string,mixed>
	 */
	private function write(array $spec, string $uid, bool $system): array {
		[$kind, $slug, $uuid, $data, $old] = $spec;
		$this->seed();
		$entity = InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $slug));
		$event  = match ($kind) {
			'create' => new ObjectCreatingEvent($entity),
			'delete' => new ObjectDeletingEvent($entity),
			default  => new ObjectUpdatingEvent($entity, $old === 'no-old' ? null : InMemoryObjectService::entity(uuid: $uuid, data: (array)$old, register: '1', schema: $this->schemaId(slug: $slug))),
		};
		$event->setModifiedData(['note' => 'kept']);

		$logger   = $this->logger();
		$queued   = [];
		$listener = $this->listener(uid: $uid, logger: $logger, queued: $queued);
		if ($system === true) {
			SystemOperationContext::run(static fn () => $listener->handle($event));
		} else {
			$listener->handle($event);
		}

		return [
			'errors'   => $event->getErrors(),
			'stopped'  => $event->isPropagationStopped(),
			'modified' => $event->getModifiedData(),
			'saves'    => $this->objects->saves,
			'queued'   => $queued,
			'locks'    => $this->locks->attempts,
			'log'      => $logger->lines,
		];
	}//end write()

	/**
	 * Seed projects with and without numbered tasks, and one whose counter is held.
	 *
	 * @return void
	 */
	private function seed(): void {
		$this->objects = new InMemoryObjectService();
		$this->locks   = new InMemoryLockingProvider();
		$this->objects->seed('project', 'verg', self::NUMBERED);
		$this->objects->seed('project', 'frsh', self::FRESH);
		$this->objects->seed('project', 'hand', self::KEYLESS);
		$this->objects->seed('project', 'zero', ['title' => 'Z', 'key' => 'ZERO']);
		$this->objects->seed('project', 'locked', ['title' => 'K', 'key' => 'LOCK', 'nextTaskNumber' => 7]);
		$this->objects->seed('task', 'z-1', ['title' => 'Z1', 'project' => 'zero', 'key' => 'ZERO-12']);
		$this->locks->holdFor(path: WorkItemKeyService::LOCK_PREFIX . 'locked', attempts: -1);
	}//end seed()

	/**
	 * The lock provider.
	 *
	 * @var InMemoryLockingProvider
	 */
	private InMemoryLockingProvider $locks;

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
				$this->lines[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}//end log()
		};
	}//end logger()

	/**
	 * The listener for one caller; queued jobs land in $queued.
	 *
	 * @param string                    $uid    The caller, '' for no session.
	 * @param AbstractLogger            $logger The logger.
	 * @param array<int,array<int,mixed>> $queued The queued jobs.
	 *
	 * @return WorkItemKeyListener
	 */
	private function listener(string $uid, AbstractLogger $logger, array &$queued): WorkItemKeyListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);
		$jobList = $this->createMock(originalClassName: IJobList::class);
		$jobList->method('add')->willReturnCallback(static function ($job, $argument=null) use (&$queued): void {
			$queued[] = [$job, $argument];
		});

		return new WorkItemKeyListener(
			keys: new WorkItemKeyService(membership: $this->membershipService(), container: $this->container(), locking: $this->locks, logger: $logger),
			scopeResolver: $this->scopeResolver(),
			jobList: $jobList,
			logger: $logger,
			userSession: $session
		);
	}//end listener()
}//end class
