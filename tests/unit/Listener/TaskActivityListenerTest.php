<?php

/**
 * Unit tests for the planix TaskActivityListener.
 *
 * Covers the observable behaviours of the listener: subject selection by diff
 * (created / status / assignee / due date / deleted), scoping (non-task and
 * foreign-register events publish nothing), actor exclusion from the audience,
 * and resilience (a malformed / unresolvable event logs and skips rather than
 * throwing out of OpenRegister's dispatch).
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration.md
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Listener;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planix\Listener\TaskActivityListener;
use OCA\Planix\Listener\TaskScopeResolver;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for TaskActivityListener.
 */
class TaskActivityListenerTest extends TestCase {
	/**
	 * Published activity events recorded across a single handle() call.
	 *
	 * @var array<int, array{type:string, subject:string, author:string, affected:string, params:array}>
	 */
	private array $published = [];

	/**
	 * Build a listener wired to recording / stub collaborators.
	 *
	 * @param string $actor The current-user uid (or '' for none).
	 * @param array<string, array<int,string>|string> $project The project data returned for member resolution.
	 * @param string $registerSlug The register slug the mapper resolves to.
	 * @param string $schemaSlug The task schema slug the mapper resolves to.
	 *
	 * @return TaskActivityListener The configured listener.
	 */
	private function makeListener(
		string $actor = 'alice',
		array $project = ['members' => ['alice', 'bob'], 'owner' => 'alice'],
		string $registerSlug = 'planix',
		string $schemaSlug = 'task',
	): TaskActivityListener {
		$this->published = [];

		$activityEvent = $this->createMock(IEvent::class);
		$captured = ['type' => '', 'subject' => '', 'author' => '', 'affected' => '', 'params' => []];

		$activityEvent->method('setApp')->willReturn($activityEvent);
		$activityEvent->method('setType')->willReturnCallback(function ($t) use ($activityEvent, &$captured) {
			$captured['type'] = $t;
			return $activityEvent;
		});
		$activityEvent->method('setAuthor')->willReturnCallback(function ($a) use ($activityEvent, &$captured) {
			$captured['author'] = $a;
			return $activityEvent;
		});
		$activityEvent->method('setTimestamp')->willReturn($activityEvent);
		$activityEvent->method('setSubject')->willReturnCallback(function ($s, $p = []) use ($activityEvent, &$captured) {
			$captured['subject'] = $s;
			$captured['params'] = $p;
			return $activityEvent;
		});
		$activityEvent->method('setObject')->willReturn($activityEvent);
		$activityEvent->method('setAffectedUser')->willReturnCallback(function ($u) use ($activityEvent, &$captured) {
			$captured['affected'] = $u;
			return $activityEvent;
		});

		$manager = $this->createMock(IActivityManager::class);
		$manager->method('generateEvent')->willReturn($activityEvent);
		$manager->method('publish')->willReturnCallback(function () use (&$captured) {
			// Snapshot the captured state at publish time.
			$this->published[] = $captured;
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);

		// Mapper stub: find()->getSlug() returns the configured slug.
		$registerEntity = new class($registerSlug) {
			public function __construct(
				private string $slug,
			) {
			}
			public function getSlug(): string {
				return $this->slug;
			}
		};
		$schemaEntity = new class($schemaSlug) {
			public function __construct(
				private string $slug,
			) {
			}
			public function getSlug(): string {
				return $this->slug;
			}
		};
		$registerMapper = new class($registerEntity) {
			public function __construct(
				private object $e,
			) {
			}
			public function find($id): object {
				return $this->e;
			}
		};
		$schemaMapper = new class($schemaEntity) {
			public function __construct(
				private object $e,
			) {
			}
			public function find($id): object {
				return $this->e;
			}
		};

		// ObjectService stub: find() returns the project entity-like array.
		$objectService = new class($project) {
			public function __construct(
				private array $project,
			) {
			}
			public function setRegister($r): self {
				return $this;
			}
			public function setSchema($s): self {
				return $this;
			}
			public function find($id): array {
				return $this->project;
			}
		};

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(function (string $id) use ($registerMapper, $schemaMapper, $objectService) {
			return match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registerMapper,
				'OCA\\OpenRegister\\Db\\SchemaMapper' => $schemaMapper,
				'OCA\\OpenRegister\\Service\\ObjectService' => $objectService,
				default => throw new \RuntimeException('unexpected: ' . $id),
			};
		});

		$resolver = new TaskScopeResolver($container, $this->createMock(LoggerInterface::class));

		return new TaskActivityListener(
			$manager,
			$session,
			$resolver,
			$this->createMock(LoggerInterface::class)
		);
	}//end makeListener()

	/**
	 * Build a mock ObjectEntity for a planix task.
	 *
	 * @param array $data The object data.
	 * @param string $register The register id (default '1' = planix).
	 * @param string $schema The schema id (default '2' = task).
	 *
	 * @return ObjectEntity The mock entity.
	 */
	private function taskEntity(?array $data, string $register = '1', string $schema = '2'): ObjectEntity {
		// ObjectEntity's getRegister/getSchema/getUuid/getObject are magic
		// (__call) accessors, so they cannot be configured on a PHPUnit mock.
		// A concrete subclass overriding them with real methods is used instead.
		return new class($data ?? [], $register, $schema) extends ObjectEntity {
			// phpcs:disable
			public function __construct(
				private array $d,
				private string $reg,
				private string $sch,
			) {
			}
			public function getObject(): array {
				return $this->d;
			}
			public function getRegister(): ?string {
				return $this->reg;
			}
			public function getSchema(): ?string {
				return $this->sch;
			}
			public function getUuid(): ?string {
				return 'task-uuid-1';
			}
			// phpcs:enable
		};
	}//end taskEntity()

	/**
	 * A create event publishes task_created to project members except the actor.
	 *
	 * @return void
	 */
	public function testCreatePublishesToMembersExceptActor(): void {
		$listener = $this->makeListener(actor: 'alice');
		$entity = $this->taskEntity(['title' => 'New task', 'project' => 'p1', 'status' => 'open']);

		$listener->handle(new ObjectCreatedEvent($entity));

		$this->assertCount(1, $this->published, 'only bob (not alice the actor) receives the entry');
		$this->assertSame('task_created', $this->published[0]['subject']);
		$this->assertSame('bob', $this->published[0]['affected']);
		$this->assertSame('alice', $this->published[0]['author']);
		$this->assertSame('planix_task', $this->published[0]['type']);
	}//end testCreatePublishesToMembersExceptActor()

	/**
	 * A status change publishes task_status_changed.
	 *
	 * @return void
	 */
	public function testStatusChangePublished(): void {
		$listener = $this->makeListener(actor: 'alice');
		$new = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'done']);
		$old = ['title' => 'T', 'project' => 'p1', 'status' => 'open'];
		$oldEntity = $this->taskEntity($old);

		$listener->handle(new ObjectUpdatedEvent($new, $oldEntity));

		$this->assertCount(1, $this->published);
		$this->assertSame('task_status_changed', $this->published[0]['subject']);
		$this->assertSame('done', $this->published[0]['params']['status']);
	}//end testStatusChangePublished()

	/**
	 * An assignee change publishes task_assigned_activity.
	 *
	 * @return void
	 */
	public function testAssigneeChangePublished(): void {
		$listener = $this->makeListener(actor: 'alice');
		$new = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'assignedTo' => 'bob']);
		$oldEntity = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'assignedTo' => '']);

		$listener->handle(new ObjectUpdatedEvent($new, $oldEntity));

		$this->assertCount(1, $this->published);
		$this->assertSame('task_assigned_activity', $this->published[0]['subject']);
		$this->assertSame('bob', $this->published[0]['params']['assignee']);
	}//end testAssigneeChangePublished()

	/**
	 * A due date change publishes task_due_date_changed.
	 *
	 * @return void
	 */
	public function testDueDateChangePublished(): void {
		$listener = $this->makeListener(actor: 'alice');
		$new = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'dueDate' => '2026-07-01']);
		$oldEntity = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'dueDate' => '']);

		$listener->handle(new ObjectUpdatedEvent($new, $oldEntity));

		$this->assertCount(1, $this->published);
		$this->assertSame('task_due_date_changed', $this->published[0]['subject']);
	}//end testDueDateChangePublished()

	/**
	 * An update touching none of the tracked fields publishes nothing.
	 *
	 * @return void
	 */
	public function testNoTrackedChangePublishesNothing(): void {
		$listener = $this->makeListener(actor: 'alice');
		$new = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'description' => 'edited']);
		$oldEntity = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open', 'description' => 'orig']);

		$listener->handle(new ObjectUpdatedEvent($new, $oldEntity));

		$this->assertCount(0, $this->published);
	}//end testNoTrackedChangePublishesNothing()

	/**
	 * A delete publishes task_deleted.
	 *
	 * @return void
	 */
	public function testDeletePublished(): void {
		$listener = $this->makeListener(actor: 'alice');
		$entity = $this->taskEntity(['title' => 'Gone', 'project' => 'p1', 'status' => 'open']);

		$listener->handle(new ObjectDeletedEvent($entity));

		$this->assertCount(1, $this->published);
		$this->assertSame('task_deleted', $this->published[0]['subject']);
	}//end testDeletePublished()

	/**
	 * A foreign-register object publishes nothing (scoping).
	 *
	 * @return void
	 */
	public function testForeignRegisterIgnored(): void {
		$listener = $this->makeListener(actor: 'alice', registerSlug: 'pipelinq');
		$entity = $this->taskEntity(['title' => 'T', 'project' => 'p1', 'status' => 'open']);

		$listener->handle(new ObjectCreatedEvent($entity));

		$this->assertCount(0, $this->published);
	}//end testForeignRegisterIgnored()

	/**
	 * A non-task schema in the planix register publishes nothing (scoping).
	 *
	 * @return void
	 */
	public function testNonTaskSchemaIgnored(): void {
		$listener = $this->makeListener(actor: 'alice', schemaSlug: 'project');
		$entity = $this->taskEntity(['title' => 'T', 'status' => 'active']);

		$listener->handle(new ObjectCreatedEvent($entity));

		$this->assertCount(0, $this->published);
	}//end testNonTaskSchemaIgnored()

	/**
	 * A task whose project reference cannot be resolved (project lookup throws)
	 * does not break dispatch and publishes nothing (no resolvable audience).
	 *
	 * @return void
	 */
	public function testUnresolvableProjectDoesNotThrow(): void {
		// Project resolution throws → audience is empty → nothing published, no throw.
		$listener = $this->makeListenerWithFailingProjectLookup(actor: 'alice');
		$entity = $this->taskEntity(['title' => 'T', 'project' => 'missing', 'status' => 'open']);

		$listener->handle(new ObjectCreatedEvent($entity));

		$this->assertCount(0, $this->published);
	}//end testUnresolvableProjectDoesNotThrow()

	/**
	 * Build a listener whose ObjectService::find() throws, simulating an
	 * unresolvable project reference.
	 *
	 * @param string $actor The current-user uid.
	 *
	 * @return TaskActivityListener The configured listener.
	 */
	private function makeListenerWithFailingProjectLookup(string $actor): TaskActivityListener {
		$this->published = [];

		$manager = $this->createMock(IActivityManager::class);
		$manager->method('publish')->willReturnCallback(function () {
			$this->published[] = [];
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$registerEntity = new class {
			public function getSlug(): string {
				return 'planix';
			}
		};
		$schemaEntity = new class {
			public function getSlug(): string {
				return 'task';
			}
		};
		$registerMapper = new class($registerEntity) {
			public function __construct(private object $e) {
			}
			public function find($id): object {
				return $this->e;
			}
		};
		$schemaMapper = new class($schemaEntity) {
			public function __construct(private object $e) {
			}
			public function find($id): object {
				return $this->e;
			}
		};
		$objectService = new class {
			public function setRegister($r): self {
				return $this;
			}
			public function setSchema($s): self {
				return $this;
			}
			public function find($id): array {
				throw new \RuntimeException('not found');
			}
		};

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(function (string $id) use ($registerMapper, $schemaMapper, $objectService) {
			return match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registerMapper,
				'OCA\\OpenRegister\\Db\\SchemaMapper' => $schemaMapper,
				'OCA\\OpenRegister\\Service\\ObjectService' => $objectService,
				default => throw new \RuntimeException('unexpected: ' . $id),
			};
		});

		$resolver = new TaskScopeResolver($container, $this->createMock(LoggerInterface::class));

		return new TaskActivityListener(
			$manager,
			$session,
			$resolver,
			$this->createMock(LoggerInterface::class)
		);
	}//end makeListenerWithFailingProjectLookup()
}//end class
