<?php

/**
 * Unit tests for WorkflowColumnSyncListener, over the real events, sync service and planner.
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\WorkflowColumnSyncListener;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\WorkflowColumnPlanner;
use OCA\Planninq\Service\WorkflowSyncService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Listener\WorkflowColumnSyncListener
 * @covers \OCA\Planninq\Service\WorkflowSyncService
 * @covers \OCA\Planninq\Service\WorkflowColumnPlanner
 * @uses \OCA\Planninq\Listener\TaskScopeResolver
 * @uses \OCA\Planninq\Service\DependencyRepository
 * @uses \OCA\Planninq\Service\ProjectMembershipService
 */
class WorkflowColumnSyncListenerTest extends TestCase {
	use MembershipFixture;

	private const FLOW = 'f0000000-0000-4000-8000-000000000001';
	private const P1 = 'a0000000-0000-4000-8000-000000000001';
	private const P2 = 'a0000000-0000-4000-8000-000000000002';

	/**
	 * The workflow columns "Intake", "Beoordeling", "Besluit".
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function flowColumns(): array {
		return [
			['key' => 'intake', 'title' => 'Intake', 'type' => 'active'],
			['key' => 'review', 'title' => 'Beoordeling', 'type' => 'active', 'wipLimit' => 3],
			['key' => 'decision', 'title' => 'Besluit', 'type' => 'done'],
		];
	}//end flowColumns()

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('workflow', self::FLOW, ['title' => 'Vergunningverlening', 'columns' => $this->flowColumns(), 'estimateScale' => 'none']);
		foreach ([self::P1 => 'p1', self::P2 => 'p2'] as $project => $short) {
			$this->objects->seed('project', $project, ['title' => 'Project ' . $short, 'owner' => 'olga', 'members' => ['olga'], 'workflow' => self::FLOW]);
			foreach ($this->flowColumns() as $order => $column) {
				$this->objects->seed(
					'column',
					$short . '-' . $column['key'],
					['title' => $column['title'], 'project' => $project, 'order' => $order, 'type' => $column['type'], 'wipLimit' => ($column['wipLimit'] ?? null), 'color' => null, 'workflowKey' => $column['key']]
				);
			}
		}
	}//end setUp()

	private function listener(): WorkflowColumnSyncListener {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new WorkflowColumnSyncListener(
			sync: new WorkflowSyncService(
				membership: $this->membershipService(),
				repository: new DependencyRepository(container: $this->container(), logger: $logger, appManager: $appManager),
				planner: new WorkflowColumnPlanner(),
				logger: $logger
			),
			scopeResolver: $this->scopeResolver(),
			logger: $logger
		);
	}//end listener()

	private function entity(string $slug, string $uuid, array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $slug));
	}//end entity()

	/**
	 * The stored columns of a project, by order.
	 *
	 * @param string $project The project uuid.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function columnsOf(string $project): array {
		$columns = array_values(array_filter($this->objects->rows['column'], static fn (array $c): bool => $c['project'] === $project));
		usort($columns, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
		return $columns;
	}//end columnsOf()

	/**
	 * Scenario "One change reaches every project on the workflow": a new last column in both projects.
	 */
	public function testAddingAColumnReachesEveryProject(): void {
		$old   = $this->objects->rows['workflow'][self::FLOW];
		$new   = $old;
		$new['columns'][] = ['key' => 'appeal', 'title' => 'Bezwaar', 'type' => 'active'];
		$this->objects->rows['workflow'][self::FLOW] = $new;

		$this->listener()->handle(new ObjectUpdatedEvent($this->entity('workflow', self::FLOW, $new), $this->entity('workflow', self::FLOW, $old)));

		foreach ([self::P1, self::P2] as $project) {
			$columns = $this->columnsOf($project);
			self::assertSame(['Intake', 'Beoordeling', 'Besluit', 'Bezwaar'], array_column($columns, 'title'));
			self::assertSame($project, $columns[3]['project']);
			self::assertSame('appeal', $columns[3]['workflowKey']);
		}

		self::assertCount(8, $this->objects->rows['column'], 'each project has its own column object');
	}//end testAddingAColumnReachesEveryProject()

	/**
	 * Rename, retype and reorder reach the projects' columns and keep their ids.
	 */
	public function testRenameRetypeAndReorder(): void {
		$old = $this->objects->rows['workflow'][self::FLOW];
		$new = $old;
		$new['columns'] = [
			['key' => 'review', 'title' => 'Toetsing', 'type' => 'active', 'wipLimit' => 5],
			['key' => 'intake', 'title' => 'Intake', 'type' => 'done'],
			['key' => 'decision', 'title' => 'Besluit', 'type' => 'done'],
		];
		$this->objects->rows['workflow'][self::FLOW] = $new;

		$this->listener()->handle(new ObjectUpdatedEvent($this->entity('workflow', self::FLOW, $new), $this->entity('workflow', self::FLOW, $old)));

		$columns = $this->columnsOf(self::P1);
		self::assertSame(['Toetsing', 'Intake', 'Besluit'], array_column($columns, 'title'));
		self::assertSame([5, null, null], array_column($columns, 'wipLimit'));
		self::assertSame(['active', 'done', 'done'], array_column($columns, 'type'));
		self::assertSame(['Toetsing', 'review'], [$this->objects->rows['column']['p1-review']['title'], $this->objects->rows['column']['p1-review']['workflowKey']], 'renamed in place');
		self::assertSame(1, $this->objects->rows['column']['p1-intake']['order']);
	}//end testRenameRetypeAndReorder()

	/**
	 * A removed workflow column that holds tasks: the tasks move to the first column, then the column goes.
	 */
	public function testRemovedColumnWithTasksMovesThemFirst(): void {
		$this->objects->seed('task', 't1', ['title' => 'A', 'project' => self::P1, 'column' => 'p1-decision']);
		$this->objects->seed('task', 't2', ['title' => 'B', 'project' => self::P1, 'column' => 'p1-review']);
		$this->objects->seed('task', 't3', ['title' => 'C', 'project' => self::P1]);
		$old = $this->objects->rows['workflow'][self::FLOW];
		$new = $old;
		array_pop($new['columns']);
		$this->objects->rows['workflow'][self::FLOW] = $new;

		$this->listener()->handle(new ObjectUpdatedEvent($this->entity('workflow', self::FLOW, $new), $this->entity('workflow', self::FLOW, $old)));

		self::assertArrayNotHasKey('p1-decision', $this->objects->rows['column']);
		self::assertSame('p1-intake', $this->objects->rows['task']['t1']['column'], 'the task of the removed column moved to the first column');
		self::assertSame('p1-review', $this->objects->rows['task']['t2']['column']);
		self::assertArrayNotHasKey('column', $this->objects->rows['task']['t3'], 'a backlog task stays in the backlog');
	}//end testRemovedColumnWithTasksMovesThemFirst()

	/**
	 * Scenario "Moving a project onto a workflow": columns match by title, the other tasks go to the first column.
	 */
	public function testPuttingAProjectOnAWorkflowMapsColumnsByTitle(): void {
		$project = 'a0000000-0000-4000-8000-000000000009';
		$this->objects->seed('project', $project, ['title' => 'Los', 'owner' => 'olga', 'members' => ['olga'], 'workflow' => self::FLOW]);
		foreach (['todo' => 'To Do', 'intake' => 'Intake', 'done' => 'Done'] as $id => $title) {
			$this->objects->seed('column', 'x-' . $id, ['title' => $title, 'project' => $project, 'order' => count($this->objects->rows['column']), 'type' => 'active']);
		}
		foreach ([['todo', 2], ['intake', 5], ['done', 2]] as [$column, $count]) {
			for ($i = 0; $i < $count; $i++) {
				$this->objects->seed('task', $column . $i, ['title' => $column . $i, 'project' => $project, 'column' => 'x-' . $column]);
			}
		}

		$old = $this->objects->rows['project'][$project];
		unset($old['workflow']);
		$this->listener()->handle(new ObjectUpdatedEvent($this->entity('project', $project, $this->objects->rows['project'][$project]), $this->entity('project', $project, $old)));

		$columns = $this->columnsOf($project);
		self::assertSame(['Intake', 'Beoordeling', 'Besluit'], array_column($columns, 'title'));
		self::assertArrayHasKey('x-intake', $this->objects->rows['column'], 'the column with the same title is kept and adopted');
		$moved = 0;
		foreach ($this->objects->rows['task'] as $id => $task) {
			if (str_starts_with($id, 'todo') === true || str_starts_with($id, 'done') === true) {
				self::assertSame('x-intake', $task['column']);
				$moved++;
			} else {
				self::assertSame('x-intake', $task['column']);
			}
		}

		self::assertSame(4, $moved);
	}//end testPuttingAProjectOnAWorkflowMapsColumnsByTitle()

	/**
	 * A new project created on a workflow gets its columns.
	 */
	public function testCreatingAProjectOnAWorkflowBuildsItsColumns(): void {
		$project = 'a0000000-0000-4000-8000-00000000000a';
		$data    = ['title' => 'Nieuw', 'owner' => 'olga', 'members' => ['olga'], 'workflow' => self::FLOW];
		$this->objects->seed('project', $project, $data);

		$this->listener()->handle(new ObjectCreatedEvent($this->entity('project', $project, $data)));

		self::assertSame(['Intake', 'Beoordeling', 'Besluit'], array_column($this->columnsOf($project), 'title'));
	}//end testCreatingAProjectOnAWorkflowBuildsItsColumns()

	/**
	 * Deleting a workflow that projects follow is refused with the count; an unused one is not.
	 */
	public function testAWorkflowInUseCannotBeDeleted(): void {
		$event = new ObjectDeletingEvent($this->entity('workflow', self::FLOW, $this->objects->rows['workflow'][self::FLOW]));
		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(WorkflowColumnSyncListener::ERROR_IN_USE, $event->getErrors()['code']);
		self::assertSame(2, $event->getErrors()['count']);

		$this->objects->seed('workflow', 'f0000000-0000-4000-8000-000000000002', ['title' => 'Ongebruikt', 'columns' => $this->flowColumns()]);
		$free = new ObjectDeletingEvent($this->entity('workflow', 'f0000000-0000-4000-8000-000000000002', ['title' => 'Ongebruikt']));
		$this->listener()->handle($free);
		self::assertFalse($free->isPropagationStopped());
	}//end testAWorkflowInUseCannotBeDeleted()
}//end class
