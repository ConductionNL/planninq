<?php

/**
 * Tests for ProjectMembershipSyncListener (planninq#681).
 *
 * When a project's members or owner change, every task, column, phase and
 * planned time entry of that project gets the new list, in the same request:
 * the member spec says an added member "MUST immediately be able to access the
 * project board and tasks", and a removed one must lose them.
 *
 * The event is the REAL OpenRegister ObjectUpdatedEvent (see
 * OpenRegisterEventStubDriftTest), constructed with a new and an old object.
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

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ProjectMembershipSyncListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Listener\ProjectMembershipSyncListener
 * @covers \OCA\Planninq\Service\ProjectMembershipService
 */
class ProjectMembershipSyncListenerTest extends TestCase {
	use MembershipFixture;

	/**
	 * Project A with alice and bob, its children in step; project B untouched.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$inStep = ['alice', 'bob'];
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => $inStep, 'owner' => 'alice']);
		$this->objects->seed('task', 't1', ['title' => 'T1', 'status' => 'open', 'project' => 'proj-a', 'members' => $inStep]);
		$this->objects->seed('task', 't2', ['title' => 'T2', 'status' => 'open', 'project' => 'proj-a', 'members' => $inStep]);
		$this->objects->seed('column', 'c1', ['title' => 'Todo', 'project' => 'proj-a', 'order' => 0, 'members' => $inStep]);
		$this->objects->seed('projectPhase', 'ph1', ['title' => 'Build', 'project' => 'proj-a', 'members' => $inStep]);
		$this->objects->seed('plannedTimeEntry', 'te-by-project', ['project' => 'proj-a', 'task' => 't1', 'user' => 'bob', 'members' => $inStep]);
		$this->objects->seed('plannedTimeEntry', 'te-by-task', ['task' => 't2', 'user' => 'alice', 'members' => $inStep]);
		$this->objects->seed('task', 'other', ['title' => 'Elsewhere', 'status' => 'open', 'project' => 'proj-b', 'members' => ['dave']]);

	}//end setUp()

	/**
	 * The listener under test.
	 *
	 * @return ProjectMembershipSyncListener
	 */
	private function listener(): ProjectMembershipSyncListener {
		$membership = $this->membershipService();
		$logger     = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectMembershipSyncListener(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			scopeResolver: $this->scopeResolver(),
			logger: $logger
		);
	}//end listener()

	/**
	 * A project update event from old to new data.
	 *
	 * @param array<string,mixed> $old The stored project.
	 * @param array<string,mixed> $new The saved project.
	 *
	 * @return ObjectUpdatedEvent
	 */
	private function projectUpdate(array $old, array $new): ObjectUpdatedEvent {
		return new ObjectUpdatedEvent(
			InMemoryObjectService::entity(uuid: 'proj-a', data: $new, register: '1', schema: $this->schemaId(slug: 'project')),
			InMemoryObjectService::entity(uuid: 'proj-a', data: $old, register: '1', schema: $this->schemaId(slug: 'project'))
		);
	}//end projectUpdate()

	/**
	 * Adding a member writes the new list to every child of the project, and to nothing else.
	 *
	 * @return void
	 */
	public function testAddingAMemberUpdatesEveryChild(): void {
		$old = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'];
		$new = ['title' => 'A', 'members' => ['alice', 'bob', 'carol'], 'owner' => 'alice'];

		$this->listener()->handle($this->projectUpdate(old: $old, new: $new));

		$written = [];
		foreach ($this->objects->saves as $save) {
			$written[$save['schema'] . '/' . $save['uuid']] = $save['object']['members'];
		}

		ksort($written);
		self::assertCount(6, $this->objects->saves, 'each child written once, the entry found both ways included');
		self::assertSame(
			[
				'column/c1' => ['alice', 'bob', 'carol'],
				'plannedTimeEntry/te-by-project' => ['alice', 'bob', 'carol'],
				'plannedTimeEntry/te-by-task' => ['alice', 'bob', 'carol'],
				'projectPhase/ph1' => ['alice', 'bob', 'carol'],
				'task/t1' => ['alice', 'bob', 'carol'],
				'task/t2' => ['alice', 'bob', 'carol'],
			],
			$written
		);
		self::assertSame(['dave'], $this->objects->rows['task']['other']['members'], 'another project is untouched');
	}//end testAddingAMemberUpdatesEveryChild()

	/**
	 * Removing a member takes them off every child, and the rest of each row is kept.
	 *
	 * @return void
	 */
	public function testRemovingAMemberTakesThemOffAndKeepsTheRow(): void {
		$old = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'];
		$new = ['title' => 'A', 'members' => ['alice'], 'owner' => 'alice'];

		$this->listener()->handle($this->projectUpdate(old: $old, new: $new));

		self::assertSame(
			['title' => 'T1', 'status' => 'open', 'project' => 'proj-a', 'members' => ['alice']],
			$this->objects->rows['task']['t1']
		);
		self::assertSame(['alice'], $this->objects->rows['plannedTimeEntry']['te-by-task']['members']);
	}//end testRemovingAMemberTakesThemOffAndKeepsTheRow()

	/**
	 * The child writes bypass RBAC and validation and stay silent.
	 *
	 * The acting user changed the project, not the tasks; the write is the
	 * system keeping a copy in step. Silent keeps a member change from firing
	 * one activity and notification per task.
	 *
	 * @return void
	 */
	public function testChildWritesAreSystemWrites(): void {
		$this->listener()->handle(
			$this->projectUpdate(
				old: ['members' => ['alice', 'bob'], 'owner' => 'alice'],
				new: ['members' => ['alice', 'bob', 'zoe'], 'owner' => 'alice']
			)
		);

		self::assertNotSame([], $this->objects->saves);
		foreach ($this->objects->saves as $save) {
			self::assertSame('planninq', $save['register']);
			self::assertFalse($save['_rbac']);
			self::assertFalse($save['_multitenancy']);
			self::assertTrue($save['silent']);
			self::assertFalse($save['_validation']);
			self::assertNotNull($save['uuid'], 'an update, never a create');
		}
	}//end testChildWritesAreSystemWrites()

	/**
	 * An owner change counts: the owner is on the copied list.
	 *
	 * @return void
	 */
	public function testOwnerChangeSyncs(): void {
		$this->listener()->handle(
			$this->projectUpdate(
				old: ['members' => ['alice', 'bob'], 'owner' => 'alice'],
				new: ['members' => ['alice', 'bob'], 'owner' => 'olga']
			)
		);

		self::assertSame(['alice', 'bob', 'olga'], $this->objects->rows['task']['t2']['members']);
	}//end testOwnerChangeSyncs()

	/**
	 * An update that leaves members and owner alone writes nothing.
	 *
	 * @return void
	 */
	public function testUnchangedMembershipWritesNothing(): void {
		$this->listener()->handle(
			$this->projectUpdate(
				old: ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'],
				new: ['title' => 'A renamed', 'members' => ['bob', 'alice'], 'owner' => 'alice']
			)
		);

		self::assertSame([], $this->objects->saves);
		self::assertSame([], $this->objects->searches, 'not even a search');
	}//end testUnchangedMembershipWritesNothing()

	/**
	 * A child already in step is not written again.
	 *
	 * @return void
	 */
	public function testChildrenAlreadyInStepAreSkipped(): void {
		$this->objects->rows['task']['t1']['members'] = ['alice', 'bob', 'carol'];

		$this->listener()->handle(
			$this->projectUpdate(
				old: ['members' => ['alice', 'bob'], 'owner' => 'alice'],
				new: ['members' => ['alice', 'bob', 'carol'], 'owner' => 'alice']
			)
		);

		$uuids = array_column($this->objects->saves, 'uuid');
		self::assertNotContains('t1', $uuids);
		self::assertContains('t2', $uuids);
	}//end testChildrenAlreadyInStepAreSkipped()

	/**
	 * One child that refuses the write does not stop the others.
	 *
	 * @return void
	 */
	public function testOneFailingChildDoesNotStopTheRest(): void {
		$this->objects->failingSaves = ['t1'];

		$this->listener()->handle(
			$this->projectUpdate(
				old: ['members' => ['alice', 'bob'], 'owner' => 'alice'],
				new: ['members' => ['alice'], 'owner' => 'alice']
			)
		);

		self::assertSame(['alice'], $this->objects->rows['task']['t2']['members']);
		self::assertSame(['alice'], $this->objects->rows['column']['c1']['members']);
	}//end testOneFailingChildDoesNotStopTheRest()

	/**
	 * Updates of other schemas, and the pre-update event, do nothing.
	 *
	 * @return void
	 */
	public function testIgnoresOtherSchemasAndThePreEvent(): void {
		$task = InMemoryObjectService::entity(uuid: 't1', data: ['members' => ['x']], register: '1', schema: $this->schemaId(slug: 'task'));
		$this->listener()->handle(new ObjectUpdatedEvent($task, $task));

		$project = InMemoryObjectService::entity(uuid: 'proj-a', data: ['members' => ['x']], register: '1', schema: $this->schemaId(slug: 'project'));
		$this->listener()->handle(new ObjectUpdatingEvent($project, null));

		self::assertSame([], $this->objects->saves);
	}//end testIgnoresOtherSchemasAndThePreEvent()

	/**
	 * Task 3.3: a new owner, or a project that moves portfolio, is copied onto its finance lines.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	public function testANewOwnerOrPortfolioReachesTheFinanceLines(): void {
		$this->objects->seed('financeLine', 'fl-1', ['project' => 'proj-a', 'kind' => 'budget', 'amount' => 5, 'financeReaders' => ['alice'], 'projectOwner' => 'alice', 'portfolio' => null]);
		$old = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'];
		$new = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'bob', 'portfolio' => 'pf-1', 'portfolioReaders' => ['mia']];
		$this->objects->seed('project', 'proj-a', $new);

		$this->listener()->handle($this->projectUpdate(old: $old, new: $new));

		$line = $this->objects->rows['financeLine']['fl-1'];
		self::assertSame(['bob', 'mia'], $line['financeReaders']);
		self::assertSame('bob', $line['projectOwner']);
		self::assertSame('pf-1', $line['portfolio']);
		self::assertSame(['alice', 'bob'], $this->objects->rows['task']['t1']['members'], 'the finance copies do not touch members');
	}//end testANewOwnerOrPortfolioReachesTheFinanceLines()

	/**
	 * A manager, a viewer and three groups reach every child as the lists
	 * OpenRegister evaluates: managers write (in `members`), viewers only
	 * read, and the owning, manager and member groups write as one list.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function testRolesAndGroupsReachEveryChild(): void {
		$old = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'];
		$new = $old + ['managers' => ['erin'], 'viewers' => ['vic'], 'managerGroups' => ['leads'], 'memberGroups' => ['devs'], 'ownerGroups' => ['pmo'], 'viewerGroups' => ['audit']];

		$this->listener()->handle($this->projectUpdate(old: $old, new: $new));

		$task = $this->objects->rows['task']['t1'];
		self::assertSame(['alice', 'bob', 'erin'], $task['members']);
		self::assertSame(['vic'], $task['viewers']);
		self::assertSame(['devs', 'leads', 'pmo'], $task['memberGroups']);
		self::assertSame(['audit'], $task['viewerGroups']);
		self::assertSame(['audit'], $this->objects->rows['plannedTimeEntry']['te-by-task']['viewerGroups'], 'an entry found through its task too');
		self::assertArrayNotHasKey('viewers', $this->objects->rows['task']['other'], 'another project is untouched');
	}//end testRolesAndGroupsReachEveryChild()
}//end class
