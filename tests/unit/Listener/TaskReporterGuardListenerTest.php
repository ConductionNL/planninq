<?php

/**
 * Tests for TaskReporterGuardListener: the reporter is the creator, and only
 * the reporter, the project owner or an admin deletes a task without logged time.
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
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\TaskReporterGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-5.1
 */
class TaskReporterGuardListenerTest extends TestCase {
	use MembershipFixture;

	private const TASK = ['title' => 'Draft the permit letter', 'status' => 'open', 'project' => 'proj-a', 'reporter' => 'dave', 'members' => ['bob', 'carol', 'dave']];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'Vergunningen', 'members' => ['bob', 'dave'], 'owner' => 'carol']);
	}//end setUp()

	private function listener(string $actor, bool $isAdmin = false): TaskReporterGuardListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));

		return new TaskReporterGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	private function task(array $data, string $schema = 'task'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'task-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end task()

	/**
	 * Scenario "Create a task from the board header": the member is the reporter, whatever was sent.
	 */
	public function testACreatedTaskRecordsTheCreatorAsReporter(): void {
		$event = new ObjectCreatingEvent($this->task(data: ['reporter' => 'carol'] + self::TASK));

		$this->listener(actor: 'bob')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame('bob', $event->getModifiedData()['reporter'] ?? null);
	}//end testACreatedTaskRecordsTheCreatorAsReporter()

	/**
	 * A member cannot make themselves the reporter of someone else's task, and a PUT that leaves it out keeps it.
	 */
	public function testAnUpdateKeepsTheStoredReporter(): void {
		foreach (['claimed' => ['reporter' => 'bob'] + self::TASK, 'nulled' => ['reporter' => null] + self::TASK] as $case => $new) {
			$event = new ObjectUpdatingEvent($this->task(data: $new), $this->task(data: self::TASK));
			$this->listener(actor: 'bob')->handle($event);
			self::assertSame('dave', $event->getModifiedData()['reporter'] ?? null, $case);
		}

		$unchanged = new ObjectUpdatingEvent($this->task(data: ['title' => 'Draft and send the permit letter'] + self::TASK), $this->task(data: self::TASK));
		$this->listener(actor: 'bob')->handle($unchanged);
		self::assertArrayNotHasKey('reporter', $unchanged->getModifiedData(), 'nothing to restore');
	}//end testAnUpdateKeepsTheStoredReporter()

	/**
	 * Scenario "A plain member cannot delete someone else's task".
	 */
	public function testAPlainMemberCannotDeleteSomeoneElsesTask(): void {
		$event = new ObjectDeletingEvent($this->task(data: self::TASK));

		$this->listener(actor: 'bob')->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(TaskReporterGuardListener::ERROR_NOT_ALLOWED, $event->getErrors()['code']);
		self::assertSame(403, $event->getErrors()['status'], 'OpenRegister answers 403');
	}//end testAPlainMemberCannotDeleteSomeoneElsesTask()

	/**
	 * Scenario "Delete a task without logged time": the reporter, the owner and an admin may.
	 */
	public function testTheReporterTheOwnerAndAnAdminMayDelete(): void {
		foreach ([['dave', false], ['carol', false], ['root', true]] as [$actor, $admin]) {
			$event = new ObjectDeletingEvent($this->task(data: self::TASK));
			$this->listener(actor: $actor, isAdmin: $admin)->handle($event);
			self::assertFalse($event->isPropagationStopped(), $actor);
		}
	}//end testTheReporterTheOwnerAndAnAdminMayDelete()

	/**
	 * Scenario "A task with logged time is cancelled instead": the server refuses the delete too.
	 */
	public function testATaskWithLoggedTimeIsNotDeleted(): void {
		$this->objects->seed('plannedTimeEntry', 'te-1', ['task' => 'task-1', 'user' => 'bob', 'duration' => 120, 'date' => '2026-09-28', 'project' => 'proj-a']);
		$event = new ObjectDeletingEvent($this->task(data: self::TASK));

		$this->listener(actor: 'carol')->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(TaskReporterGuardListener::ERROR_HAS_TIME, $event->getErrors()['code']);
		self::assertSame(409, $event->getErrors()['status']);
	}//end testATaskWithLoggedTimeIsNotDeleted()

	/**
	 * A task imported without a reporter: the owner deletes it, a member does not.
	 */
	public function testAReporterlessTaskIsTheOwnersToDelete(): void {
		$task = ['reporter' => null] + self::TASK;

		$member = new ObjectDeletingEvent($this->task(data: $task));
		$this->listener(actor: 'bob')->handle($member);
		self::assertTrue($member->isPropagationStopped(), 'member');

		$owner = new ObjectDeletingEvent($this->task(data: $task));
		$this->listener(actor: 'carol')->handle($owner);
		self::assertFalse($owner->isPropagationStopped(), 'owner');
	}//end testAReporterlessTaskIsTheOwnersToDelete()

	public function testNoSessionAndOtherSchemasPass(): void {
		$event = new ObjectDeletingEvent($this->task(data: self::TASK));
		$this->listener(actor: '')->handle($event);
		self::assertFalse($event->isPropagationStopped(), 'a repair step or occ has no session');

		$column = new ObjectDeletingEvent($this->task(data: self::TASK, schema: 'column'));
		$this->listener(actor: 'bob')->handle($column);
		self::assertFalse($column->isPropagationStopped(), 'not a task');
	}//end testNoSessionAndOtherSchemasPass()
}//end class
