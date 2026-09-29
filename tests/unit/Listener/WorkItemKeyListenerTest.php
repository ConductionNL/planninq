<?php

/**
 * Tests for WorkItemKeyListener and WorkItemKeyService: project keys and readable task keys.
 *
 * Built on the real OpenRegister event classes (stubs with the same API), the
 * real ProjectMembershipService and TaskScopeResolver over an in-memory
 * ObjectService, and a locking provider with the ILockingProvider contract.
 * Every payload the listener writes is validated against the real register
 * schema fragment.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/InMemoryLockingProvider.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\BackgroundJob\NumberProjectTasks;
use OCA\Planninq\Listener\WorkItemKeyListener;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryLockingProvider;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkItemKeyListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const VERG = '11111111-1111-4111-8111-111111111111';
	private const OTHER = '22222222-2222-4222-8222-222222222222';
	private const HAND = '33333333-3333-4333-8333-333333333333';

	private InMemoryLockingProvider $locks;

	/**
	 * Jobs queued: [class, argument].
	 *
	 * @var array<int,array{0:mixed,1:mixed}>
	 */
	private array $queued = [];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->locks   = new InMemoryLockingProvider();
		$this->objects->seed('project', self::VERG, ['title' => 'Vergunningen Centrum', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol', 'bob'], 'key' => 'VERG', 'nextTaskNumber' => 42]);
		$this->objects->seed('project', self::OTHER, ['title' => 'Secret project', 'status' => 'active', 'owner' => 'zoe', 'members' => ['zoe'], 'key' => 'SECR']);
		$this->objects->seed('project', self::HAND, ['title' => 'Handhaving', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol']]);
	}//end setUp()

	private function keys(): WorkItemKeyService {
		return new WorkItemKeyService(
			membership: $this->membershipService(),
			container: $this->container(),
			locking: $this->locks,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end keys()

	private function listener(): WorkItemKeyListener {
		$jobList = $this->createMock(originalClassName: IJobList::class);
		$jobList->method('add')->willReturnCallback(function ($job, $argument = null): void {
			$this->queued[] = [$job, $argument];
		});

		return new WorkItemKeyListener(
			keys: $this->keys(),
			scopeResolver: $this->scopeResolver(),
			jobList: $jobList,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	private function entity(string $slug, string $uuid, array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $slug));
	}//end entity()

	private function stored(string $slug, string $uuid): array {
		return (array)$this->objects->find(id: $uuid, schema: $slug)->getObject();
	}//end stored()

	private function createTask(array $data): ObjectCreatingEvent {
		$event = new ObjectCreatingEvent($this->entity(slug: 'task', uuid: '', data: $data + ['title' => 'Check the zoning plan', 'status' => 'open']));
		$this->listener()->handle($event);

		return $event;
	}//end createTask()

	/**
	 * Scenario "Numbered on create": the next number of the project, and the counter moves on.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testANewTaskGetsTheNextNumberOfItsProject(): void {
		$event = $this->createTask(data: ['project' => self::VERG]);

		self::assertSame('VERG-42', $event->getModifiedData()['key'] ?? null);
		self::assertSame(43, $this->stored(slug: 'project', uuid: self::VERG)['nextTaskNumber']);
		self::assertSame([], $this->locks->held, 'the lock is released');
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: ['title' => 'Check the zoning plan', 'status' => 'open', 'project' => self::VERG] + $event->getModifiedData()));
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $this->stored(slug: 'project', uuid: self::VERG)));

		$save = end($this->objects->saves);
		self::assertFalse($save['_rbac']);
		self::assertTrue($save['silent'], 'the counter write raises no events');
	}//end testANewTaskGetsTheNextNumberOfItsProject()

	/**
	 * A project whose counter was never set starts after its highest key, so an imported VERG-41 is not reissued.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testACounterlessProjectStartsAfterItsHighestKey(): void {
		$this->objects->seed('project', self::VERG, ['title' => 'Vergunningen Centrum', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol'], 'key' => 'VERG']);
		$this->objects->seed('task', 'a', ['title' => 'A', 'project' => self::VERG, 'key' => 'VERG-41']);
		$this->objects->seed('task', 'b', ['title' => 'B', 'project' => self::VERG, 'key' => 'PLX-99']);
		$this->objects->seed('task', 'c', ['title' => 'C', 'project' => self::OTHER, 'key' => 'VERG-500']);

		self::assertSame('VERG-42', $this->createTask(data: ['project' => self::VERG])->getModifiedData()['key'] ?? null);
	}//end testACounterlessProjectStartsAfterItsHighestKey()

	/**
	 * Scenario "An imported key is kept".
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testAnIncomingKeyIsKept(): void {
		$event = $this->createTask(data: ['project' => self::VERG, 'key' => 'PLX-7']);

		self::assertArrayNotHasKey('key', $event->getModifiedData());
		self::assertSame(42, $this->stored(slug: 'project', uuid: self::VERG)['nextTaskNumber'], 'no number used');
	}//end testAnIncomingKeyIsKept()

	/**
	 * A task in a project without a key gets no key.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testAProjectWithoutAKeyNumbersNothing(): void {
		self::assertArrayNotHasKey('key', $this->createTask(data: ['project' => self::HAND])->getModifiedData());
	}//end testAProjectWithoutAKeyNumbersNothing()

	/**
	 * Scenario "Two members create at the same moment": the second waits for the lock and gets the next number.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testTwoCreatesOnOneCounterGetDifferentKeys(): void {
		$first = $this->createTask(data: ['project' => self::VERG]);

		// The second create finds the lock held by the other request twice, then gets it.
		$this->locks->holdFor(path: WorkItemKeyService::LOCK_PREFIX . self::VERG, attempts: 2);
		$second = $this->createTask(data: ['project' => self::VERG]);

		self::assertSame('VERG-42', $first->getModifiedData()['key']);
		self::assertSame('VERG-43', $second->getModifiedData()['key']);
		self::assertSame(44, $this->stored(slug: 'project', uuid: self::VERG)['nextTaskNumber']);
		self::assertCount(4, $this->locks->attempts, 'one attempt, then two refused and one granted');
	}//end testTwoCreatesOnOneCounterGetDifferentKeys()

	/**
	 * A lock that never comes free fails the create rather than risk a duplicate key.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function testALockThatStaysHeldRefusesTheCreate(): void {
		$this->locks->holdFor(path: WorkItemKeyService::LOCK_PREFIX . self::VERG, attempts: -1);
		$event = $this->createTask(data: ['project' => self::VERG]);

		self::assertSame(WorkItemKeyListener::ERROR_BUSY, $event->getErrors()['code'] ?? null);
		self::assertTrue($event->isPropagationStopped());
		self::assertSame(42, $this->stored(slug: 'project', uuid: self::VERG)['nextTaskNumber']);
	}//end testALockThatStaysHeldRefusesTheCreate()

	/**
	 * Scenario "A used key is refused": the message names no project.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testAKeyAnotherProjectUsesIsRefused(): void {
		$event = new ObjectCreatingEvent($this->entity(slug: 'project', uuid: '', data: ['title' => 'Another', 'status' => 'active', 'key' => 'secr']));
		$this->listener()->handle($event);

		self::assertSame(WorkItemKeyListener::ERROR_USED, $event->getErrors()['code'] ?? null);
		self::assertSame('This key is already used by another project.', $event->getErrors()['message']);
		self::assertStringNotContainsString('Secret', json_encode($event->getErrors()));
		self::assertTrue($event->isPropagationStopped());
	}//end testAKeyAnotherProjectUsesIsRefused()

	/**
	 * A key must be 2 to 10 letters and digits starting with a letter; it is stored uppercase.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testTheKeyFormatIsCheckedAndStoredUppercase(): void {
		foreach (['1AB', 'A', 'TOOLONGKEY1', 'VE-RG'] as $bad) {
			$event = new ObjectCreatingEvent($this->entity(slug: 'project', uuid: '', data: ['title' => 'X', 'status' => 'active', 'key' => $bad]));
			$this->listener()->handle($event);
			self::assertSame(WorkItemKeyListener::ERROR_FORMAT, $event->getErrors()['code'] ?? null, $bad);
		}

		$event = new ObjectCreatingEvent($this->entity(slug: 'project', uuid: '', data: ['title' => 'X', 'status' => 'active', 'key' => ' verg2 ']));
		$this->listener()->handle($event);
		self::assertSame([], $event->getErrors());
		self::assertSame('VERG2', $event->getModifiedData()['key']);
	}//end testTheKeyFormatIsCheckedAndStoredUppercase()

	/**
	 * Once tasks carry the key it stays; before that the owner may still change it.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	public function testTheKeyIsFixedOnceTasksCarryIt(): void {
		$old   = $this->stored(slug: 'project', uuid: self::VERG);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['key' => 'VRG'] + $old),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		$this->listener()->handle($event);
		self::assertSame(WorkItemKeyListener::ERROR_FIXED, $event->getErrors()['code'] ?? null);

		$fresh = ['key' => 'NEW'] + $this->stored(slug: 'project', uuid: self::OTHER);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::OTHER, data: ['key' => 'SEC'] + $fresh),
			$this->entity(slug: 'project', uuid: self::OTHER, data: $fresh)
		);
		$this->listener()->handle($event);
		self::assertSame([], $event->getErrors(), 'no task numbered yet, so the key may change');

		$same = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['key' => 'VERG', 'title' => 'Renamed'] + $old),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		$this->listener()->handle($same);
		self::assertSame([], $same->getErrors(), 'keeping the key is not a change, and not a clash with itself');

		$without = $old;
		unset($without['key']);
		$omitted = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['title' => 'Renamed'] + $without),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		$this->listener()->handle($omitted);
		self::assertSame([], $omitted->getErrors(), 'a write that leaves the key out is not a change');
		self::assertSame('VERG', $omitted->getModifiedData()['key'], 'and the stored key is kept');

		$cleared = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['key' => null] + $old),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		$this->listener()->handle($cleared);
		self::assertSame(WorkItemKeyListener::ERROR_FIXED, $cleared->getErrors()['code'] ?? null, 'clearing a used key is a change too');
	}//end testTheKeyIsFixedOnceTasksCarryIt()

	/**
	 * A client cannot rewind the counter; only the system write moves it.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.1
	 */
	public function testAClientCannotWriteTheCounter(): void {
		$old   = $this->stored(slug: 'project', uuid: self::VERG);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['nextTaskNumber' => 1] + $old),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		$this->listener()->handle($event);
		self::assertSame(42, $event->getModifiedData()['nextTaskNumber']);

		$create = new ObjectCreatingEvent($this->entity(slug: 'project', uuid: '', data: ['title' => 'X', 'status' => 'active', 'nextTaskNumber' => 99]));
		$this->listener()->handle($create);
		self::assertNull($create->getModifiedData()['nextTaskNumber']);

		$system = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::VERG, data: ['nextTaskNumber' => 50] + $old),
			$this->entity(slug: 'project', uuid: self::VERG, data: $old)
		);
		SystemOperationContext::run(fn () => $this->listener()->handle($system));
		self::assertSame([], $system->getModifiedData());
	}//end testAClientCannotWriteTheCounter()

	/**
	 * Scenario "Number an existing project": setting the first key queues the numbering job.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
	 */
	public function testTheFirstKeyQueuesTheNumberingOfExistingTasks(): void {
		$old   = $this->stored(slug: 'project', uuid: self::HAND);
		$event = new ObjectUpdatingEvent(
			$this->entity(slug: 'project', uuid: self::HAND, data: ['key' => 'HAND'] + $old),
			$this->entity(slug: 'project', uuid: self::HAND, data: $old)
		);
		$this->listener()->handle($event);

		self::assertSame([], $event->getErrors());
		self::assertSame([[NumberProjectTasks::class, ['project' => self::HAND]]], $this->queued);
	}//end testTheFirstKeyQueuesTheNumberingOfExistingTasks()

	/**
	 * Scenario "Number an existing project": the job numbers keyless tasks in creation order and leaves keyed ones.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
	 */
	public function testTheJobNumbersTheKeylessTasksInOrder(): void {
		$this->objects->seed('project', self::HAND, ['title' => 'Handhaving', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol'], 'key' => 'HAND']);
		$this->objects->seed('task', 't1', ['title' => 'First', 'status' => 'open', 'project' => self::HAND]);
		$this->objects->seed('task', 't2', ['title' => 'Second', 'status' => 'open', 'project' => self::HAND, 'key' => 'OLD-3']);
		$this->objects->seed('task', 't3', ['title' => 'Third', 'status' => 'open', 'project' => self::HAND]);
		$this->objects->seed('task', 't4', ['title' => 'Fourth', 'status' => 'done', 'project' => self::HAND]);
		$this->objects->seed('task', 'x', ['title' => 'Elsewhere', 'status' => 'open', 'project' => self::VERG]);

		$job = new NumberProjectTasks(time: $this->createMock(originalClassName: ITimeFactory::class), keys: $this->keys());
		$job->numberProject(argument: ['project' => self::HAND]);

		self::assertSame('HAND-1', $this->stored(slug: 'task', uuid: 't1')['key']);
		self::assertSame('OLD-3', $this->stored(slug: 'task', uuid: 't2')['key']);
		self::assertSame('HAND-2', $this->stored(slug: 'task', uuid: 't3')['key']);
		self::assertSame('HAND-3', $this->stored(slug: 'task', uuid: 't4')['key']);
		self::assertArrayNotHasKey('key', $this->stored(slug: 'task', uuid: 'x'));
		self::assertSame(4, $this->stored(slug: 'project', uuid: self::HAND)['nextTaskNumber']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: $this->stored(slug: 'task', uuid: 't1')));

		$job->numberProject(argument: ['project' => self::HAND]);
		self::assertSame('HAND-1', $this->stored(slug: 'task', uuid: 't1')['key'], 'a second run renumbers nothing');
		self::assertSame(4, $this->stored(slug: 'project', uuid: self::HAND)['nextTaskNumber']);
	}//end testTheJobNumbersTheKeylessTasksInOrder()
}//end class
