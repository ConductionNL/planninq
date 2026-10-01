<?php

/**
 * Tests for ProjectMemberAccessListener (planninq#681).
 *
 * The listener copies a project's members onto every task, column, phase and
 * planned time entry as it is written, so OpenRegister can scope them with
 * `{"members": {"$contains": "$userId"}}`. On a create, and on a move to another
 * project, it also refuses a caller who is not a member of the target project:
 * OpenRegister checks `create` before it has the object, so a `match` on the
 * create rule cannot do that job.
 *
 * Every event here is the REAL OpenRegister class (tests/stubs mirror their
 * exact public methods, see OpenRegisterEventStubDriftTest). A hand-made event
 * double is how learniq#984 called `getObject()` on `ObjectUpdatingEvent`,
 * which has none, and answered 500 on every update.
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
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ProjectMemberAccessListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Listener\ProjectMemberAccessListener
 * @covers \OCA\Planninq\Service\ProjectMembershipService
 */
class ProjectMemberAccessListenerTest extends TestCase {
	use MembershipFixture;

	/**
	 * Seed two projects: A with alice and bob (owner carol), B with dave only.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob', 'alice'], 'owner' => 'carol']);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'members' => ['dave'], 'owner' => 'dave']);
		$this->objects->seed('task', 'task-in-a', ['title' => 'T', 'status' => 'open', 'project' => 'proj-a']);

	}//end setUp()

	/**
	 * Build the listener acting as the given user.
	 *
	 * @param string $actor The acting uid, or '' for no session (cron, occ, repair).
	 * @param bool $isAdmin Whether IGroupManager calls the actor an admin.
	 * @param array<int,string> $groupIds The actor's Nextcloud groups.
	 *
	 * @return ProjectMemberAccessListener
	 */
	private function listener(string $actor, bool $isAdmin = false, array $groupIds = []): ProjectMemberAccessListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));
		$groups->method('getUserGroupIds')->willReturn($groupIds);

		return new ProjectMemberAccessListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	/**
	 * A planninq object of the given schema.
	 *
	 * @param string $schema The schema slug.
	 * @param array<string,mixed> $data The object data.
	 * @param string $uuid The uuid.
	 *
	 * @return \OCA\OpenRegister\Db\ObjectEntity
	 */
	private function object(string $schema, array $data, string $uuid = 'new-uuid'): \OCA\OpenRegister\Db\ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end object()

	/**
	 * A member creating a task gets the project's members, owner included, stamped on it.
	 *
	 * @return void
	 */
	public function testCreateByMemberStampsTheProjectMembers(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'New', 'project' => 'proj-a']));

		$this->listener(actor: 'alice')->handle($event);

		self::assertFalse($event->isPropagationStopped(), 'a member may create a task in their project');
		self::assertSame(['members' => ['alice', 'bob', 'carol']], $event->getModifiedData());
	}//end testCreateByMemberStampsTheProjectMembers()

	/**
	 * Each of the four project-scoped schemas is stamped.
	 *
	 * @return void
	 */
	public function testColumnPhaseAndTimeEntryAreStampedToo(): void {
		foreach (['column', 'projectPhase', 'plannedTimeEntry'] as $schema) {
			$event = new ObjectCreatingEvent($this->object(schema: $schema, data: ['title' => 'x', 'project' => 'proj-a']));

			$this->listener(actor: 'bob')->handle($event);

			self::assertFalse($event->isPropagationStopped(), $schema . ' create by a member');
			self::assertSame(['alice', 'bob', 'carol'], ($event->getModifiedData()['members'] ?? null), $schema);
		}
	}//end testColumnPhaseAndTimeEntryAreStampedToo()

	/**
	 * A planned time entry that names only its task takes the members of the task's project.
	 *
	 * @return void
	 */
	public function testTimeEntryWithoutProjectResolvesThroughItsTask(): void {
		$event = new ObjectCreatingEvent(
			$this->object(schema: 'plannedTimeEntry', data: ['task' => 'task-in-a', 'user' => 'bob', 'duration' => 30])
		);

		$this->listener(actor: 'bob')->handle($event);

		self::assertSame(['alice', 'bob', 'carol'], ($event->getModifiedData()['members'] ?? null));
	}//end testTimeEntryWithoutProjectResolvesThroughItsTask()

	/**
	 * A non-member cannot create a task in someone else's project.
	 *
	 * @return void
	 */
	public function testCreateByNonMemberIsRefused(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'Sneaky', 'project' => 'proj-b']));

		$this->listener(actor: 'alice')->handle($event);

		self::assertTrue($event->isPropagationStopped(), 'alice is not on project B');
		self::assertSame(ProjectMemberAccessListener::ERROR_CODE, ($event->getErrors()['code'] ?? null));
		self::assertNotSame('', (string)($event->getErrors()['message'] ?? ''));
	}//end testCreateByNonMemberIsRefused()

	/**
	 * A project log entry is stamped for a member and refused for an outsider.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.1
	 */
	public function testProjectLogEntryIsStampedAndGated(): void {
		$byMember = new ObjectCreatingEvent($this->object(schema: 'projectLogEntry', data: ['title' => 'Kick-off', 'type' => 'meeting', 'project' => 'proj-a']));
		$this->listener(actor: 'bob')->handle($byMember);
		self::assertFalse($byMember->isPropagationStopped(), 'bob is on project A');
		self::assertSame(['alice', 'bob', 'carol'], ($byMember->getModifiedData()['members'] ?? null));

		$byOutsider = new ObjectCreatingEvent($this->object(schema: 'projectLogEntry', data: ['title' => 'Sneaky', 'type' => 'issue', 'project' => 'proj-b']));
		$this->listener(actor: 'alice')->handle($byOutsider);
		self::assertTrue($byOutsider->isPropagationStopped(), 'alice is not on project B');
		self::assertSame(ProjectMemberAccessListener::ERROR_CODE, ($byOutsider->getErrors()['code'] ?? null));
	}//end testProjectLogEntryIsStampedAndGated()

	/**
	 * A risk is stamped for a member and refused for an outsider.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.1
	 */
	public function testRiskIsStampedAndGated(): void {
		$byMember = new ObjectCreatingEvent($this->object(schema: 'risk', data: ['title' => 'Supplier late', 'likelihood' => 4, 'impact' => 3, 'project' => 'proj-a']));
		$this->listener(actor: 'alice')->handle($byMember);
		self::assertFalse($byMember->isPropagationStopped(), 'alice is on project A');
		self::assertSame(['alice', 'bob', 'carol'], ($byMember->getModifiedData()['members'] ?? null));

		$byOutsider = new ObjectCreatingEvent($this->object(schema: 'risk', data: ['title' => 'Sneaky', 'likelihood' => 1, 'impact' => 1, 'project' => 'proj-a']));
		$this->listener(actor: 'dave')->handle($byOutsider);
		self::assertTrue($byOutsider->isPropagationStopped(), 'dave is not on project A');
	}//end testRiskIsStampedAndGated()

	/**
	 * A release is stamped for a member and refused for an outsider.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-1.1
	 */
	public function testReleaseIsStampedAndGated(): void {
		$byMember = new ObjectCreatingEvent($this->object(schema: 'projectRelease', data: ['title' => 'Version 2.0', 'project' => 'proj-a']));
		$this->listener(actor: 'alice')->handle($byMember);
		self::assertFalse($byMember->isPropagationStopped(), 'alice is on project A');
		self::assertSame(['alice', 'bob', 'carol'], ($byMember->getModifiedData()['members'] ?? null));

		$byOutsider = new ObjectCreatingEvent($this->object(schema: 'projectRelease', data: ['title' => 'Version 9', 'project' => 'proj-a']));
		$this->listener(actor: 'dave')->handle($byOutsider);
		self::assertTrue($byOutsider->isPropagationStopped(), 'dave is not on project A');
	}//end testReleaseIsStampedAndGated()

	/**
	 * A task that names no project, or a project that does not exist, is refused for a non-admin.
	 *
	 * @return void
	 */
	public function testCreateWithoutAResolvableProjectIsRefused(): void {
		foreach ([['title' => 'No project'], ['title' => 'Ghost', 'project' => 'proj-gone']] as $data) {
			$event = new ObjectCreatingEvent($this->object(schema: 'task', data: $data));

			$this->listener(actor: 'alice')->handle($event);

			self::assertTrue($event->isPropagationStopped(), json_encode($data));
		}
	}//end testCreateWithoutAResolvableProjectIsRefused()

	/**
	 * An admin who is not on the project may still create in it, and the members are still stamped.
	 *
	 * @return void
	 */
	public function testAdminIsNotRefusedAndIsStillStamped(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'column', data: ['title' => 'Done', 'project' => 'proj-b']));

		$this->listener(actor: 'root', isAdmin: true)->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['members' => ['dave']], $event->getModifiedData());
	}//end testAdminIsNotRefusedAndIsStillStamped()

	/**
	 * A write without a session (occ, cron, a repair step) is stamped and never refused.
	 *
	 * @return void
	 */
	public function testSessionlessWriteIsStampedAndNotRefused(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'Seed', 'project' => 'proj-b']));

		$this->listener(actor: '')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['members' => ['dave']], $event->getModifiedData());
	}//end testSessionlessWriteIsStampedAndNotRefused()

	/**
	 * The client cannot choose the members list: an update overwrites what it sent.
	 *
	 * Uses getNewObject()/getOldObject(), the only accessors ObjectUpdatingEvent has.
	 *
	 * @return void
	 */
	public function testUpdateOverwritesClientSuppliedMembers(): void {
		$old = $this->object(schema: 'task', data: ['title' => 'T', 'project' => 'proj-a', 'members' => ['alice', 'bob', 'carol']], uuid: 'task-in-a');
		$new = $this->object(schema: 'task', data: ['title' => 'T2', 'project' => 'proj-a', 'members' => ['alice', 'mallory']], uuid: 'task-in-a');
		$event = new ObjectUpdatingEvent($new, $old);

		$this->listener(actor: 'alice')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['members' => ['alice', 'bob', 'carol']], $event->getModifiedData());
	}//end testUpdateOverwritesClientSuppliedMembers()

	/**
	 * An update whose members list is already in step leaves the data untouched.
	 *
	 * @return void
	 */
	public function testUpdateAlreadyInStepModifiesNothing(): void {
		$stored = ['title' => 'T', 'project' => 'proj-a', 'members' => ['carol', 'alice', 'bob']];
		$event = new ObjectUpdatingEvent(
			$this->object(schema: 'task', data: ['status' => 'done'] + $stored, uuid: 'task-in-a'),
			$this->object(schema: 'task', data: $stored, uuid: 'task-in-a')
		);

		$this->listener(actor: 'alice')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame([], $event->getModifiedData());
	}//end testUpdateAlreadyInStepModifiesNothing()

	/**
	 * Moving a task into a project the caller is not on is refused.
	 *
	 * @return void
	 */
	public function testMoveIntoAProjectTheCallerIsNotOnIsRefused(): void {
		$old = $this->object(schema: 'task', data: ['title' => 'T', 'project' => 'proj-a'], uuid: 'task-in-a');
		$new = $this->object(schema: 'task', data: ['title' => 'T', 'project' => 'proj-b'], uuid: 'task-in-a');
		$event = new ObjectUpdatingEvent($new, $old);

		$this->listener(actor: 'alice')->handle($event);

		self::assertTrue($event->isPropagationStopped());
	}//end testMoveIntoAProjectTheCallerIsNotOnIsRefused()

	/**
	 * Moving a task into another project the caller is on re-stamps it with that project's members.
	 *
	 * @return void
	 */
	public function testMoveIntoAnotherOwnProjectRestamps(): void {
		$this->objects->seed('project', 'proj-c', ['title' => 'C', 'members' => ['alice', 'erin'], 'owner' => 'alice']);
		$old = $this->object(schema: 'task', data: ['title' => 'T', 'project' => 'proj-a'], uuid: 'task-in-a');
		$new = $this->object(schema: 'task', data: ['title' => 'T', 'project' => 'proj-c'], uuid: 'task-in-a');
		$event = new ObjectUpdatingEvent($new, $old);

		$this->listener(actor: 'alice')->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(['members' => ['alice', 'erin']], $event->getModifiedData());
	}//end testMoveIntoAnotherOwnProjectRestamps()

	/**
	 * Data another listener already modified survives; only `members` is added.
	 *
	 * @return void
	 */
	public function testKeepsModifiedDataFromOtherListeners(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'New', 'project' => 'proj-a']));
		$event->setModifiedData(['key' => 'PQ-7']);

		$this->listener(actor: 'alice')->handle($event);

		self::assertSame(['key' => 'PQ-7', 'members' => ['alice', 'bob', 'carol']], $event->getModifiedData());
	}//end testKeepsModifiedDataFromOtherListeners()

	/**
	 * Objects of other schemas and other apps' registers are left alone.
	 *
	 * @return void
	 */
	public function testLeavesOtherSchemasAndRegistersAlone(): void {
		$label = new ObjectCreatingEvent($this->object(schema: 'label', data: ['name' => 'bug', 'project' => 'proj-b']));
		$foreign = new ObjectCreatingEvent(
			InMemoryObjectService::entity(uuid: 'x', data: ['project' => 'proj-b'], register: '9', schema: '10')
		);

		$this->listener(actor: 'alice')->handle($label);
		$this->listener(actor: 'alice')->handle($foreign);

		foreach ([$label, $foreign] as $event) {
			self::assertFalse($event->isPropagationStopped());
			self::assertSame([], $event->getModifiedData());
		}
	}//end testLeavesOtherSchemasAndRegistersAlone()

	/**
	 * A post event is not this listener's business and does nothing.
	 *
	 * @return void
	 */
	public function testIgnoresPostEvents(): void {
		$this->listener(actor: 'alice')->handle(
			new ObjectCreatedEvent($this->object(schema: 'task', data: ['project' => 'proj-b']))
		);

		self::assertSame([], $this->objects->saves, 'nothing is written from a post event');
	}//end testIgnoresPostEvents()

	/**
	 * Projects are read without RBAC: a membership check must not depend on the caller's own read rights.
	 *
	 * @return void
	 */
	public function testReadsTheProjectAsTheSystem(): void {
		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'New', 'project' => 'proj-a']));

		$this->listener(actor: 'alice')->handle($event);

		$projectReads = array_values(array_filter($this->objects->finds, static fn (array $find): bool => $find['schema'] === 'project'));
		self::assertNotSame([], $projectReads);
		self::assertFalse($projectReads[0]['_rbac']);
		self::assertSame('planninq', $projectReads[0]['register']);
	}//end testReadsTheProjectAsTheSystem()

	/**
	 * Someone in a member group writes a task and it carries every role list;
	 * a viewer is refused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function testAGroupMemberWritesAndAViewerIsRefused(): void {
		$this->objects->seed('project', 'proj-c', ['title' => 'C', 'members' => [], 'owner' => 'carol', 'managers' => ['erin'], 'viewers' => ['vic'], 'memberGroups' => ['devs'], 'ownerGroups' => ['pmo'], 'viewerGroups' => ['audit']]);

		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'New', 'project' => 'proj-c']));
		$this->listener(actor: 'gina', groupIds: ['devs'])->handle($event);
		self::assertFalse($event->isPropagationStopped(), 'a member group may write');
		self::assertSame(
			['members' => ['carol', 'erin'], 'viewers' => ['vic'], 'memberGroups' => ['devs', 'pmo'], 'viewerGroups' => ['audit']],
			$event->getModifiedData()
		);

		$event = new ObjectCreatingEvent($this->object(schema: 'task', data: ['title' => 'New', 'project' => 'proj-c']));
		$this->listener(actor: 'vic', groupIds: ['audit'])->handle($event);
		self::assertTrue($event->isPropagationStopped(), 'a viewer, alone or through a viewer group, may not write');
	}//end testAGroupMemberWritesAndAViewerIsRefused()
}//end class
