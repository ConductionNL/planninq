<?php

/**
 * Tests for WorkflowColumnSync: one change to a workflow reaches every project on it.
 *
 * @category Test
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\WorkflowColumnSyncListener;
use OCA\Planninq\Service\WorkflowColumnSync;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\WorkflowColumnSync
 * @covers \OCA\Planninq\Listener\WorkflowColumnSyncListener
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
class WorkflowColumnSyncTest extends TestCase {
	use MembershipFixture;

	private const WORKFLOW = 'wf-vergunning';


	private WorkflowColumnSync $sync;

	/**
	 * The workflow "Vergunningverlening" and two projects that follow it.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->schemaSlugs['26'] = 'workflow';
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('workflow', self::WORKFLOW, ['title' => 'Vergunningverlening', 'columns' => self::columns(['intake' => 'Intake', 'beoordeling' => 'Beoordeling', 'besluit' => 'Besluit'])]);
		foreach (['p-1', 'p-2'] as $project) {
			$this->objects->seed('project', $project, ['title' => $project, 'owner' => 'anne', 'members' => ['bob'], 'workflow' => self::WORKFLOW]);
		}

		$this->objects->seed('project', 'p-own', ['title' => 'Own columns', 'owner' => 'anne']);
		$this->sync = new WorkflowColumnSync(
			membership: $this->membershipService(),
			container: $this->container(),
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Workflow columns from key => title, the last one done.
	 *
	 * @param array<string,string> $titles Key => title.
	 *
	 * @return array<int, array<string,string>>
	 */
	private static function columns(array $titles): array {
		$columns = [];
		foreach ($titles as $key => $title) {
			$columns[] = ['key' => $key, 'title' => $title, 'type' => 'active'];
		}

		$columns[count($columns) - 1]['type'] = 'done';

		return $columns;
	}//end columns()

	/**
	 * A project's columns as title => data, in board order.
	 *
	 * @param string $project The project.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	private function boardOf(string $project): array {
		$board = [];
		foreach (($this->objects->rows['column'] ?? []) as $data) {
			if (($data['project'] ?? null) === $project) {
				$board[$data['order']] = $data;
			}
		}

		ksort($board);

		return array_column($board, null, 'title');
	}//end boardOf()

	/**
	 * Adding a column after Besluit gives both projects their own Bezwaar column, last, with project set.
	 *
	 * @return void
	 */
	public function testOneChangeReachesEveryProjectOnTheWorkflow(): void {
		$this->assertSame(['projects' => 2, 'failed' => 0, 'moved' => 0], $this->sync->syncWorkflow(workflowId: self::WORKFLOW));
		$this->objects->seed('workflow', self::WORKFLOW, ['title' => 'Vergunningverlening', 'columns' => self::columns(['intake' => 'Intake', 'beoordeling' => 'Beoordeling', 'besluit' => 'Besluit', 'bezwaar' => 'Bezwaar'])]);

		$this->sync->syncWorkflow(workflowId: self::WORKFLOW);

		foreach (['p-1', 'p-2'] as $project) {
			$board = $this->boardOf(project: $project);
			$this->assertSame(['Intake', 'Beoordeling', 'Besluit', 'Bezwaar'], array_keys($board));
			$this->assertSame('bezwaar', $board['Bezwaar']['workflowKey']);
			$this->assertSame('done', $board['Bezwaar']['type']);
			$this->assertSame('active', $board['Besluit']['type']);
			$this->assertContains('bob', $board['Bezwaar']['members'], 'a new column carries the project\'s access list');
		}

		$this->assertArrayNotHasKey('Intake', $this->boardOf(project: 'p-own'), 'a project off the workflow is untouched');
	}//end testOneChangeReachesEveryProjectOnTheWorkflow()

	/**
	 * A rename keeps the column (same key), and a removed column's tasks move to the first column first.
	 *
	 * @return void
	 */
	public function testARenameKeepsTheColumnAndARemovalMovesItsTasks(): void {
		$this->sync->syncWorkflow(workflowId: self::WORKFLOW);
		$board    = $this->boardOf(project: 'p-1');
		$intakeId = array_search($board['Intake'], $this->objects->rows['column'], true);
		$besluit  = array_search($board['Besluit'], $this->objects->rows['column'], true);
		$this->objects->seed('task', 't-1', ['title' => 'Aanvraag', 'project' => 'p-1', 'column' => $besluit]);

		$this->objects->seed('workflow', self::WORKFLOW, ['title' => 'Vergunningverlening', 'columns' => self::columns(['intake' => 'Ontvangst', 'beoordeling' => 'Beoordeling'])]);
		$counts = $this->sync->syncWorkflow(workflowId: self::WORKFLOW);

		$this->assertSame(1, $counts['moved']);
		$board = $this->boardOf(project: 'p-1');
		$this->assertSame(['Ontvangst', 'Beoordeling'], array_keys($board));
		$this->assertSame($intakeId, array_search($board['Ontvangst'], $this->objects->rows['column'], true), 'the renamed column is the same object');
		$this->assertSame($intakeId, $this->objects->rows['task']['t-1']['column']);
		$this->assertArrayNotHasKey($besluit, $this->objects->rows['column']);
	}//end testARenameKeepsTheColumnAndARemovalMovesItsTasks()

	/**
	 * Moving a project onto a workflow matches columns by title and sends the rest's tasks to the first column, as the preview says.
	 *
	 * @return void
	 */
	public function testMovingAProjectOntoAWorkflow(): void {
		foreach (['c-todo' => ['To Do', 0], 'c-intake' => ['Intake', 1], 'c-done' => ['Done', 2]] as $id => [$title, $order]) {
			$this->objects->seed('column', $id, ['title' => $title, 'project' => 'p-own', 'order' => $order]);
		}

		foreach (['c-todo' => 2, 'c-intake' => 5, 'c-done' => 2] as $column => $count) {
			for ($i = 0; $i < $count; $i++) {
				$this->objects->seed('task', $column . '-' . $i, ['title' => 'x', 'project' => 'p-own', 'column' => $column]);
			}
		}

		$workflow = $this->objects->rows['workflow'][self::WORKFLOW];
		$this->assertSame(['moving' => 4, 'target' => 'Intake'], $this->sync->preview(projectId: 'p-own', workflow: $workflow));

		$this->assertSame(4, $this->sync->syncProject(projectId: 'p-own', workflow: $workflow));

		$this->assertSame(['Intake', 'Beoordeling', 'Besluit'], array_keys($this->boardOf(project: 'p-own')));
		$this->assertSame('intake', $this->objects->rows['column']['c-intake']['workflowKey'], 'the Intake column is adopted, not replaced');
		$inIntake = array_filter($this->objects->rows['task'], static fn (array $t): bool => $t['column'] === 'c-intake');
		$this->assertCount(9, $inIntake, 'every task is placed');
	}//end testMovingAProjectOntoAWorkflow()

	/**
	 * Workflow columns are cleaned: no key or title is skipped, a duplicate key counts once, unknown types are active.
	 *
	 * @return void
	 */
	public function testWorkflowColumnsAreCleaned(): void {
		$this->assertSame(
			[['key' => 'a', 'title' => 'A', 'type' => 'active', 'wipLimit' => 3, 'color' => null]],
			WorkflowColumnSync::workflowColumns(workflow: ['columns' => [['key' => 'a', 'title' => 'A', 'type' => 'odd', 'wipLimit' => 3], ['key' => 'a', 'title' => 'B'], ['title' => 'No key'], ['key' => 'b']]])
		);
		$this->assertSame([], $this->sync->projectsOn(workflowId: ''));
		$this->assertSame(['projects' => 0, 'failed' => 0, 'moved' => 0], $this->sync->syncWorkflow(workflowId: 'gone'));
	}//end testWorkflowColumnsAreCleaned()

	/**
	 * The listener syncs a workflow whose columns changed, and a project put on a workflow; other saves do nothing.
	 *
	 * @return void
	 */
	public function testTheListenerSyncsOnTheRightChanges(): void {
		$sync = $this->createMock(WorkflowColumnSync::class);
		$sync->expects($this->once())->method('syncWorkflow')->with(self::WORKFLOW);
		$sync->expects($this->once())->method('syncProjectOnto')->with('p-own', self::WORKFLOW);
		$listener = new WorkflowColumnSyncListener(sync: $sync, scopeResolver: $this->scopeResolver(), logger: $this->createMock(LoggerInterface::class));
		$entity = fn (string $slug, string $uuid, array $data) => InMemoryObjectService::entity(uuid: $uuid, data: $data, register: '1', schema: $this->schemaId(slug: $slug));

		$listener->handle(new ObjectUpdatedEvent($entity('workflow', self::WORKFLOW, ['columns' => [['key' => 'a', 'title' => 'A']]]), $entity('workflow', self::WORKFLOW, ['columns' => []])));
		$listener->handle(new ObjectUpdatedEvent($entity('workflow', self::WORKFLOW, ['title' => 'Nieuw', 'columns' => []]), $entity('workflow', self::WORKFLOW, ['title' => 'Oud', 'columns' => []])));
		$listener->handle(new ObjectUpdatedEvent($entity('project', 'p-own', ['workflow' => self::WORKFLOW]), $entity('project', 'p-own', [])));
		$listener->handle(new ObjectUpdatedEvent($entity('project', 'p-1', ['workflow' => self::WORKFLOW, 'title' => 'x']), $entity('project', 'p-1', ['workflow' => self::WORKFLOW])));
	}//end testTheListenerSyncsOnTheRightChanges()
}//end class
