<?php

/**
 * Golden test for the finance line guard: who may write a line, imported lines, duplicates, kept copies.
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
use OCA\Planninq\Listener\FinanceLineListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Records errors, stops, modified data and log lines over finance line writes and actors.
 *
 * @covers \OCA\Planninq\Listener\FinanceLineListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Service\FinanceLineService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 */
class FinanceLineGuardGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/finance-line-guard.golden.json';

	/**
	 * Actor => [uid, admin, groups].
	 */
	private const ACTORS = [
		'nobody'   => ['', false, []],
		'system'   => ['sys', false, []],
		'admin'    => ['root', true, []],
		'owner a'  => ['carol', false, []],
		'owner b'  => ['bob', false, []],
		'importer' => ['gus', false, ['planninq-finance-import']],
		'outside'  => ['eve', false, ['staff']],
	];

	private const MANUAL = ['project' => 'proj-a', 'category' => 'Materials', 'kind' => 'actual', 'amount' => 1500, 'source' => 'manual'];

	private const IMPORTED = ['projectKey' => 'OMG', 'kind' => 'actual', 'amount' => 1200, 'externalRef' => 'FIN-2', 'source' => 'import'];

	private const KEPT_A = ['financeReaders' => ['carol', 'mia'], 'projectOwner' => 'carol', 'portfolio' => 'pf-1'];

	/**
	 * Write name => [kind, schema slug, uuid, sent data, stored data or null].
	 */
	private const WRITES = [
		'create manual a'          => ['create', 'financeLine', 'new', self::MANUAL, null],
		'create manual b'          => ['create', 'financeLine', 'new', ['project' => 'proj-b'] + self::MANUAL, null],
		'create manual no project' => ['create', 'financeLine', 'new', ['project' => ''] + self::MANUAL, null],
		'create manual unknown'    => ['create', 'financeLine', 'new', ['project' => 'missing'] + self::MANUAL, null],
		'create manual no owner'   => ['create', 'financeLine', 'new', ['project' => 'proj-c'] + self::MANUAL, null],
		'create manual in step'    => ['create', 'financeLine', 'new', self::KEPT_A + self::MANUAL, null],
		'create manual half step'  => ['create', 'financeLine', 'new', ['financeReaders' => ['mia', 'carol'], 'projectOwner' => 'carol'] + self::MANUAL, null],
		'create no source'         => ['create', 'financeLine', 'new', ['project' => 'proj-a', 'amount' => 3], null],
		'create imported'          => ['create', 'financeLine', 'new', self::IMPORTED, null],
		'create imported dup'      => ['create', 'financeLine', 'new', ['externalRef' => 'FIN-1'] + self::IMPORTED, null],
		'create imported dup trim' => ['create', 'financeLine', 'new', ['externalRef' => ' FIN-1 '] + self::IMPORTED, null],
		'create imported no ref'   => ['create', 'financeLine', 'new', ['externalRef' => ''] + self::IMPORTED, null],
		'create imported ref num'  => ['create', 'financeLine', 'new', ['externalRef' => 7] + self::IMPORTED, null],
		'create imported key dup'  => ['create', 'financeLine', 'new', ['projectKey' => 'DUP'] + self::IMPORTED, null],
		'create imported key none' => ['create', 'financeLine', 'new', ['projectKey' => ' '] + self::IMPORTED, null],
		'create imported key trim' => ['create', 'financeLine', 'new', ['projectKey' => ' BRG '] + self::IMPORTED, null],
		'create imported has proj' => ['create', 'financeLine', 'new', ['project' => 'proj-b', 'externalRef' => 'FIN-1'] + self::IMPORTED, null],
		'create imported own uuid' => ['create', 'financeLine', 'line-imp', ['externalRef' => 'FIN-1'] + self::IMPORTED, null],
		'create only kept'         => ['create', 'financeLine', 'new', ['financeReaders' => ['eve'], 'id' => 'x'], null],
		'create manual with ref'   => ['create', 'financeLine', 'new', ['externalRef' => 'FIN-1'] + self::MANUAL, null],
		'create manual sent clash' => ['create', 'financeLine', 'new', ['conflictingObject' => 'forged'] + self::MANUAL, null],
		'create import sent clash' => ['create', 'financeLine', 'new', ['externalRef' => 'FIN-1', 'conflictingObject' => 'forged'] + self::IMPORTED, null],
		'create import null clash' => ['create', 'financeLine', 'new', ['externalRef' => 'FIN-1', 'conflictingObject' => null] + self::IMPORTED, null],
		'update manual amount'     => ['update', 'financeLine', 'line-a', ['amount' => 99] + self::MANUAL, self::MANUAL],
		'update manual a to b'     => ['update', 'financeLine', 'line-a', ['project' => 'proj-b'] + self::MANUAL, self::MANUAL],
		'update manual b to a'     => ['update', 'financeLine', 'line-b', self::MANUAL, ['project' => 'proj-b'] + self::MANUAL],
		'update to no project'     => ['update', 'financeLine', 'line-a', ['project' => ''] + self::MANUAL, self::MANUAL],
		'update only kept'         => ['update', 'financeLine', 'line-a', ['financeReaders' => ['eve'], 'projectOwner' => 'eve', 'portfolio' => 'pf-9'] + self::MANUAL, self::KEPT_A + self::MANUAL],
		'update kept and self'     => ['update', 'financeLine', 'line-a', ['@self' => ['id' => 'x'], 'id' => 'x', 'financeReaders' => ['eve']] + self::MANUAL, ['id' => 'line-a'] + self::KEPT_A + self::MANUAL],
		'update kept in step'      => ['update', 'financeLine', 'line-a', self::KEPT_A + self::MANUAL, ['financeReaders' => ['eve']] + self::MANUAL],
		'update key order only'    => ['update', 'financeLine', 'line-a', ['source' => 'manual', 'amount' => 1500, 'kind' => 'actual', 'category' => 'Materials', 'project' => 'proj-a'], self::MANUAL],
		'update no old'            => ['update', 'financeLine', 'line-a', self::MANUAL, 'no-old'],
		'update no old b'          => ['update', 'financeLine', 'line-b', ['project' => 'proj-b'] + self::MANUAL, 'no-old'],
		'update imported own'      => ['update', 'financeLine', 'line-imp', ['externalRef' => 'FIN-1', 'amount' => 5] + self::IMPORTED, ['externalRef' => 'FIN-1', 'project' => 'proj-a'] + self::IMPORTED],
		'update imported to dup'   => ['update', 'financeLine', 'line-imp2', ['externalRef' => 'FIN-1'] + self::IMPORTED, ['externalRef' => 'FIN-3'] + self::IMPORTED],
		'update manual to import'  => ['update', 'financeLine', 'line-a', ['source' => 'import', 'externalRef' => 'FIN-4'] + self::MANUAL, self::MANUAL],
		'update import to manual'  => ['update', 'financeLine', 'line-imp', self::MANUAL, ['externalRef' => 'FIN-1', 'project' => 'proj-a'] + self::IMPORTED],
		'update imported kept'     => ['update', 'financeLine', 'line-imp', ['externalRef' => 'FIN-1', 'project' => 'proj-a', 'financeReaders' => ['eve']] + self::IMPORTED, ['externalRef' => 'FIN-1', 'project' => 'proj-a'] + self::IMPORTED],
		'delete manual a'          => ['delete', 'financeLine', 'line-a', self::MANUAL, null],
		'delete manual b'          => ['delete', 'financeLine', 'line-b', ['project' => 'proj-b'] + self::MANUAL, null],
		'delete no project'        => ['delete', 'financeLine', 'line-x', ['project' => ''] + self::MANUAL, null],
		'delete imported'          => ['delete', 'financeLine', 'line-imp', ['externalRef' => 'FIN-1', 'project' => 'proj-a'] + self::IMPORTED, null],
		'delete imported dup'      => ['delete', 'financeLine', 'line-imp2', ['externalRef' => 'FIN-1'] + self::IMPORTED, null],
		'task write'               => ['create', 'task', 'task-1', self::MANUAL, null],
		'other register'           => ['create', 'financeLine@9', 'new', self::MANUAL, null],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testGuardMatchesTheRecording(): void {
		$recorded = [];
		foreach (self::WRITES as $write => $spec) {
			foreach (self::ACTORS as $actor => $who) {
				$recorded[$write . ' / ' . $actor] = $this->guard(spec: $spec, who: $who, system: ($actor === 'system'));
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
	 * One write: what the guard did.
	 *
	 * @param array<int,mixed>                           $spec   The write.
	 * @param array{0:string,1:bool,2:array<int,string>} $who    The actor.
	 * @param bool                                       $system Whether it runs as a system operation.
	 *
	 * @return array<string,mixed>
	 */
	private function guard(array $spec, array $who, bool $system): array {
		[$kind, $slug, $uuid, $data, $old] = $spec;
		$this->seed();

		$register = '1';
		if (str_ends_with($slug, '@9') === true) {
			[$slug, $register] = ['financeLine', '9'];
		}

		$entity = InMemoryObjectService::entity(uuid: $uuid, data: $data, register: $register, schema: $this->schemaId(slug: $slug));
		$event  = match ($kind) {
			'create' => new ObjectCreatingEvent($entity),
			'delete' => new ObjectDeletingEvent($entity),
			default  => new ObjectUpdatingEvent($entity, $old === 'no-old' ? null : InMemoryObjectService::entity(uuid: $uuid, data: (array)$old, register: $register, schema: $this->schemaId(slug: $slug))),
		};
		$event->setModifiedData(['note' => 'kept']);

		$logger   = $this->logger();
		$listener = $this->listener(who: $who, logger: $logger);
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
			'log'      => $logger->lines,
		];
	}//end guard()

	/**
	 * Seed projects (one key used twice) and imported and manual lines.
	 *
	 * @return void
	 */
	private function seed(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob'], 'key' => 'OMG', 'portfolio' => 'pf-1', 'portfolioReaders' => ['mia']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'bob', 'key' => 'BRG']);
		$this->objects->seed('project', 'proj-c', ['title' => 'C', 'key' => 'DUP']);
		$this->objects->seed('project', 'proj-d', ['title' => 'D', 'owner' => 'dora', 'key' => 'DUP']);
		$this->objects->seed('financeLine', 'line-imp', ['externalRef' => 'FIN-1', 'project' => 'proj-a'] + self::IMPORTED);
		$this->objects->seed('financeLine', 'line-imp2', ['externalRef' => 'FIN-3', 'project' => 'proj-a'] + self::IMPORTED);
		$this->objects->seed('financeLine', 'line-man', ['externalRef' => 'FIN-5'] + self::MANUAL);
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
				$this->lines[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}//end log()
		};
	}//end logger()

	/**
	 * The listener acting as one actor; the group manager answers only for that actor.
	 *
	 * @param array{0:string,1:bool,2:array<int,string>} $who    The actor.
	 * @param AbstractLogger                             $logger The logger.
	 *
	 * @return FinanceLineListener
	 */
	private function listener(array $who, AbstractLogger $logger): FinanceLineListener {
		[$uid, $admin, $groupIds] = $who;
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($uid === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => ($admin === true && $id === $uid));
		$groups->method('isInGroup')->willReturnCallback(static fn (string $id, string $group): bool => ($id === $uid && in_array($group, $groupIds, true) === true));

		$membership = $this->membershipService();

		return new FinanceLineListener(
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			membership: $membership,
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: $logger
		);
	}//end listener()
}//end class
