<?php

/**
 * Tests for ProjectActionService: the multi-object actions check the caller
 * once, then write everything as the system, children first.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\PlanningActionService;
use OCA\Planninq\Service\ProjectActionService;
use OCA\Planninq\Service\ProjectActionSupport;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/specs/projects.md
 */
class ProjectActionServiceTest extends TestCase {
	use MembershipFixture;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob'], 'ownerGroups' => ['pmo']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'dave', 'members' => ['dave']]);
		$this->objects->seed('task', 'parent', ['title' => 'Permit', 'status' => 'open', 'project' => 'proj-a', 'reporter' => 'bob', 'release' => 'rel-1', 'checklist' => [['label' => 'Draft', 'done' => true]]]);
		$this->objects->seed('task', 'child-1', ['title' => 'Draft', 'status' => 'done', 'project' => 'proj-a', 'reporter' => 'bob', 'parent' => 'parent', 'release' => 'rel-1']);
		$this->objects->seed('task', 'child-2', ['title' => 'Send', 'status' => 'open', 'project' => 'proj-a', 'reporter' => 'eve', 'parent' => 'parent', 'release' => 'rel-1']);
		$this->objects->seed('column', 'col-1', ['title' => 'Todo', 'project' => 'proj-a']);
		$this->objects->seed('risk', 'risk-1', ['title' => 'Delay', 'project' => 'proj-a']);
		$this->objects->seed('financeLine', 'fin-1', ['amount' => 100, 'project' => 'proj-a']);
		$this->objects->seed('plannedTimeEntry', 'te-1', ['task' => 'parent', 'user' => 'bob', 'duration' => 30]);
		$this->objects->seed('projectRelease', 'rel-1', ['title' => '1.0', 'status' => 'planned', 'project' => 'proj-a']);
		$this->objects->seed('projectRelease', 'rel-2', ['title' => '1.1', 'status' => 'planned', 'project' => 'proj-a']);
		$this->objects->seed('projectRelease', 'rel-b', ['title' => 'B', 'status' => 'planned', 'project' => 'proj-b']);
		$this->objects->seed('task', 'other', ['title' => 'Elsewhere', 'status' => 'open', 'project' => 'proj-b']);
	}//end setUp()

	/**
	 * The service acting as the given user.
	 *
	 * @param string            $actor    The caller.
	 * @param array<int,string> $groupIds The caller's groups.
	 * @param bool              $isAdmin  Whether the caller is an admin.
	 *
	 * @return ProjectActionService
	 */
	private function service(string $actor, array $groupIds = [], bool $isAdmin = false): ProjectActionService {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($isAdmin);
		$groups->method('getUserGroupIds')->willReturn($groupIds);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-10-21T12:00:00+00:00'));
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturn(true);

		$support = new ProjectActionSupport(
			membership: $this->membershipService(),
			container: $this->container(),
			userSession: $session,
			groupManager: $groups,
			appManager: $apps
		);

		return new ProjectActionService(membership: $this->membershipService(), support: $support, timeFactory: $time, l10n: $l10n);
	}//end service()

	/**
	 * The planning service acting as the given user.
	 *
	 * @param string $actor The caller.
	 *
	 * @return PlanningActionService
	 */
	private function planning(string $actor): PlanningActionService {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturn([]);
		$groups->method('isAdmin')->willReturn(false);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturn(true);
		$support = new ProjectActionSupport(
			membership: $this->membershipService(),
			container: $this->container(),
			userSession: $session,
			groupManager: $groups,
			appManager: $apps
		);

		return new PlanningActionService(membership: $this->membershipService(), support: $support);
	}//end planning()

	/**
	 * Assert an action is refused with a code and status.
	 *
	 * @param callable $action The action.
	 * @param string   $code   The expected code.
	 * @param int      $status The expected status.
	 *
	 * @return void
	 */
	private function assertRefused(callable $action, string $code, int $status): void {
		try {
			$action();
			self::fail('expected ' . $code);
		} catch (ProjectActionException $e) {
			self::assertSame($code, $e->getReason());
			self::assertSame($status, $e->getStatus());
		}
	}//end assertRefused()

	/**
	 * Scenario "Delete a project": everything in it goes, the project last; nothing of another project.
	 */
	public function testAProjectIsDeletedWithEverythingInIt(): void {
		$count = $this->service(actor: 'frank', groupIds: ['pmo'])->deleteProject(projectId: 'proj-a');

		self::assertSame(10, $count);
		foreach (['task', 'column', 'risk', 'financeLine', 'plannedTimeEntry', 'projectRelease', 'project'] as $schema) {
			foreach ($this->objects->rows[$schema] ?? [] as $id => $row) {
				self::assertNotSame('proj-a', $row['project'] ?? null, $schema . '/' . $id . ' is left behind');
			}
		}

		self::assertArrayNotHasKey('te-1', $this->objects->rows['plannedTimeEntry'] ?? [], 'a time entry that names only its task');
		self::assertArrayHasKey('other', $this->objects->rows['task']);
		self::assertArrayHasKey('proj-b', $this->objects->rows['project']);
	}//end testAProjectIsDeletedWithEverythingInIt()

	/**
	 * A member who does not own the project cannot delete it, and nothing goes.
	 */
	public function testAMemberCannotDeleteTheProject(): void {
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->deleteProject(projectId: 'proj-a'), code: 'planninq-project-delete-not-allowed', status: 403);
		self::assertArrayHasKey('risk-1', $this->objects->rows['risk']);
	}//end testAMemberCannotDeleteTheProject()

	/**
	 * Shipping moves the unfinished tasks and stamps the server's time.
	 */
	public function testShippingMovesUnfinishedTasksAtTheServersTime(): void {
		$release = $this->service(actor: 'bob')->shipRelease(releaseId: 'rel-1', choice: 'move', targetId: 'rel-2');

		self::assertSame('released', $this->objects->rows['projectRelease']['rel-1']['status']);
		self::assertSame('2026-10-21T12:00:00+00:00', $this->objects->rows['projectRelease']['rel-1']['releasedAt']);
		self::assertSame('rel-2', $this->objects->rows['task']['parent']['release']);
		self::assertSame('rel-2', $this->objects->rows['task']['child-2']['release']);
		self::assertSame('rel-1', $this->objects->rows['task']['child-1']['release'], 'a done task stays in the shipped release');
		self::assertNotSame([], $release);
	}//end testShippingMovesUnfinishedTasksAtTheServersTime()

	/**
	 * Shipping is refused for an outsider, an unknown choice and a target in another project.
	 */
	public function testShippingRefusals(): void {
		$this->assertRefused(action: fn () => $this->service(actor: 'dave')->shipRelease(releaseId: 'rel-1', choice: 'keep', targetId: null), code: 'planninq-not-a-project-member', status: 403);
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->shipRelease(releaseId: 'rel-1', choice: 'drop', targetId: null), code: 'planninq-invalid-choice', status: 400);
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->shipRelease(releaseId: 'rel-1', choice: 'move', targetId: 'rel-b'), code: 'planninq-invalid-target', status: 400);
		self::assertSame('planned', $this->objects->rows['projectRelease']['rel-1']['status']);
	}//end testShippingRefusals()

	/**
	 * A tree with logged time, or with a subtask the caller may not delete, is left whole.
	 */
	public function testATaskTreeIsDeletedWholeOrNotAtAll(): void {
		unset($this->objects->rows['plannedTimeEntry']['te-1']);
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->deleteTaskTree(taskId: 'parent', mode: 'delete'), code: 'planninq-task-delete-not-allowed', status: 403);
		self::assertCount(4, $this->objects->rows['task'], 'nothing was deleted');

		self::assertSame(3, $this->service(actor: 'carol')->deleteTaskTree(taskId: 'parent', mode: 'delete'));
		self::assertSame(['other'], array_keys($this->objects->rows['task']));
	}//end testATaskTreeIsDeletedWholeOrNotAtAll()

	/**
	 * Detaching keeps the subtasks as separate tasks; logged time blocks the delete.
	 */
	public function testDetachKeepsTheSubtasksAndLoggedTimeBlocks(): void {
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->deleteTaskTree(taskId: 'parent', mode: 'detach'), code: 'planninq-task-has-logged-time', status: 409);

		unset($this->objects->rows['plannedTimeEntry']['te-1']);
		self::assertSame(1, $this->service(actor: 'bob')->deleteTaskTree(taskId: 'parent', mode: 'detach'));
		self::assertNull($this->objects->rows['task']['child-2']['parent']);
		self::assertArrayNotHasKey('parent', $this->objects->rows['task']);
	}//end testDetachKeepsTheSubtasksAndLoggedTimeBlocks()

	/**
	 * A copy takes the task and its subtasks, without dates, people or done checklist items.
	 */
	public function testACopyTakesTheSubtasks(): void {
		$copy   = $this->service(actor: 'bob')->duplicateTask(taskId: 'parent');
		$copyId = (string)$copy['id'];

		$stored = $this->objects->rows['task'][$copyId];
		self::assertSame('Copy of Permit', $stored['title']);
		self::assertSame('bob', $stored['reporter']);
		self::assertFalse($stored['checklist'][0]['done']);
		self::assertCount(2, array_filter($this->objects->rows['task'], static fn (array $row): bool => ($row['parent'] ?? null) === $copyId));

		$this->assertRefused(action: fn () => $this->service(actor: 'dave')->duplicateTask(taskId: 'parent'), code: 'planninq-not-a-project-member', status: 403);
	}//end testACopyTakesTheSubtasks()

	/**
	 * Moving a phase renumbers every phase once, also when orders were tied.
	 */
	public function testMovingAPhaseRenumbersThePhases(): void {
		$this->objects->seed('projectPhase', 'ph-a', ['title' => 'Plan', 'project' => 'proj-a', 'order' => 0]);
		$this->objects->seed('projectPhase', 'ph-b', ['title' => 'Build', 'project' => 'proj-a', 'order' => 0]);
		$this->objects->seed('projectPhase', 'ph-c', ['title' => 'Ship', 'project' => 'proj-a', 'order' => 1]);

		$order = $this->planning(actor: 'bob')->movePhase(projectId: 'proj-a', phaseId: 'ph-c', step: -1);

		self::assertSame([['id' => 'ph-a', 'order' => 0], ['id' => 'ph-c', 'order' => 1], ['id' => 'ph-b', 'order' => 2]], $order);
		self::assertSame(1, $this->objects->rows['projectPhase']['ph-c']['order']);
		self::assertSame(2, $this->objects->rows['projectPhase']['ph-b']['order']);

		$this->assertRefused(action: fn () => $this->planning(actor: 'dave')->movePhase(projectId: 'proj-a', phaseId: 'ph-a', step: 1), code: 'planninq-not-a-project-member', status: 403);
		$this->assertRefused(action: fn () => $this->planning(actor: 'bob')->movePhase(projectId: 'proj-a', phaseId: 'other', step: 1), code: 'planninq-invalid-choice', status: 400);
	}//end testMovingAPhaseRenumbersThePhases()

	/**
	 * A run of new dates is written whole, or not at all when it would break a blocking link or leave the project.
	 */
	public function testAScheduleRunIsWrittenWholeOrNotAtAll(): void {
		$this->objects->seed('task', 'design', ['title' => 'Design', 'status' => 'open', 'project' => 'proj-a', 'startDate' => '2026-11-02', 'dueDate' => '2026-11-06']);
		$this->objects->seed('task', 'build', ['title' => 'Build', 'status' => 'open', 'project' => 'proj-a', 'startDate' => '2026-11-09', 'dueDate' => '2026-11-13']);
		$this->objects->seed('dependency', 'edge-1', ['blocker' => 'design', 'blocked' => 'build', 'type' => 'blocks']);
		$this->schemaSlugs['27'] = 'dependency';

		$broken = [['id' => 'design', 'startDate' => '2026-11-02', 'dueDate' => '2026-11-10']];
		$this->assertRefused(action: fn () => $this->planning(actor: 'bob')->schedule(projectId: 'proj-a', moves: $broken), code: 'planninq-schedule-conflict', status: 409);
		self::assertSame('2026-11-06', $this->objects->rows['task']['design']['dueDate'], 'nothing written');

		$elsewhere = [['id' => 'other', 'startDate' => '2026-11-02', 'dueDate' => '2026-11-03']];
		$this->assertRefused(action: fn () => $this->planning(actor: 'bob')->schedule(projectId: 'proj-a', moves: $elsewhere), code: 'planninq-invalid-schedule', status: 400);

		$run = [
			['id' => 'design', 'startDate' => '2026-11-02', 'dueDate' => '2026-11-10'],
			['id' => 'build', 'startDate' => '2026-11-11', 'dueDate' => '2026-11-17'],
		];
		self::assertSame(2, $this->planning(actor: 'bob')->schedule(projectId: 'proj-a', moves: $run));
		self::assertSame('2026-11-17', $this->objects->rows['task']['build']['dueDate']);
		self::assertSame('Build', $this->objects->rows['task']['build']['title'], 'the rest of the task is kept');
	}//end testAScheduleRunIsWrittenWholeOrNotAtAll()

	/**
	 * An action is created in the entry's project and linked from the entry in the same request.
	 */
	public function testAnActionIsCreatedAndLinked(): void {
		$this->objects->seed('projectLogEntry', 'log-1', ['title' => 'Steering group', 'project' => 'proj-a', 'actions' => ['old-task']]);

		$result = $this->planning(actor: 'bob')->addAction(entryId: 'log-1', task: ['title' => 'Send the planning', 'project' => 'proj-b', 'issueType' => 'bug']);

		$taskId = $result['task']['id'];
		self::assertSame('proj-a', $this->objects->rows['task'][$taskId]['project'], 'the entry decides the project');
		self::assertSame('action', $this->objects->rows['task'][$taskId]['issueType']);
		self::assertSame(['old-task', $taskId], $this->objects->rows['projectLogEntry']['log-1']['actions']);
	}//end testAnActionIsCreatedAndLinked()

	/**
	 * When the link cannot be written, the new task is removed again.
	 */
	public function testAFailedLinkRemovesTheAction(): void {
		$this->objects->seed('projectLogEntry', 'log-1', ['title' => 'Steering group', 'project' => 'proj-a']);
		$this->objects->failingSaves = ['log-1'];

		try {
			$this->planning(actor: 'bob')->addAction(entryId: 'log-1', task: ['title' => 'Send the planning']);
			self::fail('the link write fails');
		} catch (\RuntimeException $e) {
			self::assertSame([], array_filter($this->objects->rows['task'], static fn (array $row): bool => ($row['title'] ?? '') === 'Send the planning'));
		}
	}//end testAFailedLinkRemovesTheAction()
}//end class
