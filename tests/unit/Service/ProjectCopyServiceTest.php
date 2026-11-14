<?php

/**
 * Unit tests for ProjectCopyService over an in-memory OpenRegister.
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Service\CreationPolicyService;
use OCA\Planninq\Service\OpenRegisterObjects;
use OCA\Planninq\Service\ProjectCopyRun;
use OCA\Planninq\Service\ProjectCopyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A source project with two columns, a phase, a parent and child task, an epic
 * and a dependency, copied through the real service into memory.
 */
#[CoversClass(ProjectCopyService::class)]
#[UsesClass(ProjectCopyRun::class)]
class ProjectCopyServiceTest extends TestCase {

	private const SOURCE = '11111111-1111-4111-8111-111111111111';
	private const COL_TODO = '22222222-2222-4222-8222-222222222221';
	private const COL_DONE = '22222222-2222-4222-8222-222222222222';
	private const PHASE = '33333333-3333-4333-8333-333333333333';
	private const EPIC = '44444444-4444-4444-8444-444444444440';
	private const PARENT = '44444444-4444-4444-8444-444444444441';
	private const CHILD = '44444444-4444-4444-8444-444444444442';
	private const OUTSIDER = '55555555-5555-4555-8555-555555555555';

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objects;

	/**
	 * Seed the source project.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new class extends InMemoryObjectService {
			/**
			 * Titles whose save throws, to stop a copy part-way.
			 *
			 * @var list<string>
			 */
			public array $refusedTitles = [];

			/**
			 * {@inheritDoc}
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array|int {
				// As OpenRegister: `_`-prefixed keys page and sort, they do not filter.
				$filters = array_filter($filters, static fn ($key): bool => str_starts_with((string)$key, '_') === false, ARRAY_FILTER_USE_KEY);
				return parent::searchObjectsBySlug(registerSlug: $registerSlug, schemaSlug: $schemaSlug, filters: $filters, _rbac: $_rbac, _multitenancy: $_multitenancy);
			}

			/**
			 * {@inheritDoc}
			 */
			public function saveObject(array|object $object, ?array $extend = [], mixed $register = null, mixed $schema = null, ?string $uuid = null, bool $_rbac = true, bool $_multitenancy = true, bool $silent = false, bool $_validation = true): ?object {
				if (in_array(($object['title'] ?? ''), $this->refusedTitles, true) === true) {
					throw new \RuntimeException('refused');
				}

				return parent::saveObject(object: $object, extend: $extend, register: $register, schema: $schema, uuid: $uuid, _rbac: $_rbac, _multitenancy: $_multitenancy, silent: $silent, _validation: $_validation);
			}
		};

		$this->objects->seed('project', self::SOURCE, [
			'title' => 'Woo template', 'status' => 'active', 'owner' => 'anna', 'isTemplate' => true, 'key' => 'WOO',
			'nextTaskNumber' => 9, 'color' => '#336699', 'members' => ['bob'], 'managers' => ['carol'],
			'startDate' => '2026-01-01', 'endDate' => '2026-01-31', 'metadata' => ['origin' => 'seed'],
		]);
		$this->objects->seed('column', self::COL_TODO, ['title' => 'To do', 'project' => self::SOURCE, 'order' => 1, 'type' => 'active', 'wipLimit' => 3]);
		$this->objects->seed('column', self::COL_DONE, ['title' => 'Done', 'project' => self::SOURCE, 'order' => 2, 'type' => 'done']);
		$this->objects->seed('projectPhase', self::PHASE, ['title' => 'Intake', 'project' => self::SOURCE, 'startDate' => '2026-01-05', 'endDate' => '2026-01-10']);
		// The child comes first in storage, so the copy has to order parents first.
		$this->objects->seed('task', self::CHILD, [
			'title' => 'Child', 'project' => self::SOURCE, 'status' => 'done', 'key' => 'WOO-3', 'column' => self::COL_DONE,
			'phase' => self::PHASE, 'parent' => self::PARENT, 'epic' => self::EPIC, 'assignedTo' => 'bob', 'dueDate' => '2026-01-08',
			'completedAt' => '2026-01-08T10:00:00Z', 'reporter' => 'anna', 'members' => ['bob'],
		]);
		$this->objects->seed('task', self::PARENT, ['title' => 'Parent', 'project' => self::SOURCE, 'status' => 'in_progress', 'column' => self::COL_TODO, 'epic' => self::EPIC, 'startDate' => '2026-01-02']);
		$this->objects->seed('task', self::EPIC, ['title' => 'Epic', 'project' => self::SOURCE, 'status' => 'open', 'issueType' => 'epic', 'parent' => self::OUTSIDER]);
		$this->objects->seed('dependency', '66666666-6666-4666-8666-666666666661', ['blocker' => self::PARENT, 'blocked' => self::CHILD, 'project' => self::SOURCE]);
		$this->objects->seed('dependency', '66666666-6666-4666-8666-666666666662', ['blocker' => self::OUTSIDER, 'blocked' => self::CHILD, 'project' => self::SOURCE]);
		$this->objects->seed('task', self::OUTSIDER, ['title' => 'Elsewhere', 'project' => 'another-project', 'status' => 'open']);
	}//end setUp()

	/**
	 * The service over the in-memory store.
	 *
	 * @param bool $mayCreate What the creation policy answers.
	 *
	 * @return ProjectCopyService
	 */
	private function service(bool $mayCreate = true): ProjectCopyService {
		$locator = $this->createMock(OpenRegisterObjects::class);
		$locator->method('objectService')->willReturn($this->objects);
		$policy = $this->createMock(CreationPolicyService::class);
		$policy->method('canCreate')->willReturn($mayCreate);

		return new ProjectCopyService(objects: $locator, creationPolicy: $policy, logger: $this->createMock(LoggerInterface::class));
	}//end service()

	/**
	 * The rows of a schema that belong to a project.
	 *
	 * @param string $schema    The schema slug.
	 * @param string $projectId The project.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function rowsOf(string $schema, string $projectId): array {
		return array_filter($this->objects->rows[$schema] ?? [], static fn (array $row): bool => ($row['project'] ?? '') === $projectId);
	}//end rowsOf()

	/**
	 * The copied task with a title.
	 *
	 * @param string $projectId The copy.
	 * @param string $title     The task title.
	 *
	 * @return array{0:string,1:array<string,mixed>}
	 */
	private function copiedTask(string $projectId, string $title): array {
		foreach ($this->rowsOf(schema: 'task', projectId: $projectId) as $id => $row) {
			if ($row['title'] === $title) {
				return [$id, $row];
			}
		}

		self::fail('No copied task ' . $title);
	}//end copiedTask()

	/**
	 * Every reference inside the copy points at a copy, the dates move, tasks start open, and people stay behind.
	 *
	 * @return void
	 */
	public function testACopyRemapsEveryReferenceAndShiftsTheDates(): void {
		$result = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'Woo 2026-014', 'startDate' => '2026-03-01']);

		$this->assertSame(201, $result['status']);
		$copy = $result['project']['id'];
		$this->assertNotSame(self::SOURCE, $copy);

		$project = $this->objects->rows['project'][$copy];
		$this->assertSame(['Woo 2026-014', 'active', 'dave', false, '#336699'], [$project['title'], $project['status'], $project['owner'], $project['isTemplate'], $project['color']]);
		$this->assertSame(['2026-03-01', '2026-03-31'], [$project['startDate'], $project['endDate']]);
		$this->assertSame(['origin' => 'seed', 'copiedFrom' => self::SOURCE], $project['metadata'], 'copyState is cleared when the copy is done');
		$this->assertArrayNotHasKey('members', $project);
		$this->assertArrayNotHasKey('key', $project);
		$this->assertArrayNotHasKey('nextTaskNumber', $project);

		$columns = $this->rowsOf(schema: 'column', projectId: $copy);
		$phases = $this->rowsOf(schema: 'projectPhase', projectId: $copy);
		$this->assertSame(['To do', 'Done'], array_column(array_values($columns), 'title'));
		$this->assertSame(3, array_values($columns)[0]['wipLimit']);
		$this->assertSame(['2026-03-05', '2026-03-10'], [array_values($phases)[0]['startDate'], array_values($phases)[0]['endDate']]);

		[$epicId, $epic] = $this->copiedTask(projectId: $copy, title: 'Epic');
		[$parentId, $parent] = $this->copiedTask(projectId: $copy, title: 'Parent');
		[$childId, $child] = $this->copiedTask(projectId: $copy, title: 'Child');

		$this->assertArrayNotHasKey('parent', $epic, 'a parent outside the project is dropped, not left pointing at it');
		$this->assertSame($epicId, $parent['epic']);
		$this->assertSame($parentId, $child['parent']);
		$this->assertSame($epicId, $child['epic']);
		$this->assertSame(array_keys($columns)[1], $child['column']);
		$this->assertSame(array_keys($phases)[0], $child['phase']);
		$this->assertSame('2026-03-08', $child['dueDate']);
		$this->assertSame('2026-03-02', $parent['startDate']);
		foreach ([$epic, $parent, $child] as $task) {
			$this->assertSame('open', $task['status']);
		}

		foreach (['key', 'completedAt', 'reporter', 'members', 'assignedTo'] as $field) {
			$this->assertArrayNotHasKey($field, $child, $field . ' is not copied');
		}

		$dependencies = array_values($this->rowsOf(schema: 'dependency', projectId: $copy));
		$this->assertSame([['blocker' => $parentId, 'blocked' => $childId, 'project' => $copy]], $dependencies, 'only the dependency between copied tasks');

		$this->assertCount(4, $this->rowsOf(schema: 'task', projectId: self::SOURCE) + $this->rowsOf(schema: 'task', projectId: 'another-project'), 'the source is untouched');
	}//end testACopyRemapsEveryReferenceAndShiftsTheDates()

	/**
	 * With people chosen the lists and assignees come along, and the caller still owns the copy.
	 *
	 * @return void
	 */
	public function testPeopleComeAlongOnlyWhenChosen(): void {
		$result = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'With people', 'parts' => ['people' => 'true']]);

		$copy = $result['project']['id'];
		$project = $this->objects->rows['project'][$copy];
		$this->assertSame(['dave', ['bob'], ['carol']], [$project['owner'], $project['members'], $project['managers']]);
		$this->assertSame('bob', $this->copiedTask(projectId: $copy, title: 'Child')[1]['assignedTo']);
		$this->assertSame(['2026-01-01', '2026-01-08'], [$project['startDate'], $this->copiedTask(projectId: $copy, title: 'Child')[1]['dueDate']], 'without a new start date the dates stay');
	}//end testPeopleComeAlongOnlyWhenChosen()

	/**
	 * Leaving tasks out leaves their dependencies out; leaving columns out drops the column reference.
	 *
	 * @return void
	 */
	public function testPartsThatAreLeftOutAreNotCopied(): void {
		$withoutTasks = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'Board only', 'parts' => ['tasks' => false]]);
		$this->assertSame([], $this->rowsOf(schema: 'task', projectId: $withoutTasks['project']['id']));
		$this->assertSame([], $this->rowsOf(schema: 'dependency', projectId: $withoutTasks['project']['id']));
		$this->assertCount(2, $this->rowsOf(schema: 'column', projectId: $withoutTasks['project']['id']));

		$withoutColumns = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'Tasks only', 'parts' => ['columns' => false, 'phases' => false]]);
		$child = $this->copiedTask(projectId: $withoutColumns['project']['id'], title: 'Child')[1];
		$this->assertArrayNotHasKey('column', $child);
		$this->assertArrayNotHasKey('phase', $child);
	}//end testPartsThatAreLeftOutAreNotCopied()

	/**
	 * A source without a start date copies no dates when a new start is asked for.
	 *
	 * @return void
	 */
	public function testASourceWithoutAStartDateCopiesNoDates(): void {
		unset($this->objects->rows['project'][self::SOURCE]['startDate']);

		$copy = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'Undated', 'startDate' => '2026-03-01'])['project']['id'];

		$this->assertNull($this->objects->rows['project'][$copy]['endDate']);
		$this->assertNull($this->copiedTask(projectId: $copy, title: 'Child')[1]['dueDate']);
	}//end testASourceWithoutAStartDateCopiesNoDates()

	/**
	 * A failure part-way removes everything the copy wrote.
	 *
	 * @return void
	 */
	public function testAFailureMidCopyLeavesNothing(): void {
		$before = $this->objects->rows;
		$this->objects->refusedTitles = ['Child'];

		$result = $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'Doomed']);

		$this->assertSame(500, $result['status']);
		$this->assertNotEmpty($this->objects->deletes);
		$this->assertSame($before, array_map(static fn (array $rows): array => $rows, array_filter($this->objects->rows, static fn (array $rows): bool => $rows !== [])), 'every written row is gone again');
	}//end testAFailureMidCopyLeavesNothing()

	/**
	 * The policy, a missing title and an unreadable source refuse before anything is written.
	 *
	 * @return void
	 */
	public function testRefusalsWriteNothing(): void {
		$this->assertSame(403, $this->service(mayCreate: false)->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'X'])['status']);
		$this->assertSame(400, $this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => '  '])['status']);
		$this->assertSame(404, $this->service()->copy(sourceId: '99999999-9999-4999-8999-999999999999', uid: 'dave', request: ['title' => 'X'])['status']);

		$this->assertSame([], $this->objects->saves);
		$this->assertTrue($this->objects->finds === [] || $this->objects->finds[0]['_rbac'] === true, 'the source is read as the caller');
	}//end testRefusalsWriteNothing()

	/**
	 * The source is read as the caller, never as the system.
	 *
	 * @return void
	 */
	public function testTheSourceIsReadAsTheCaller(): void {
		$this->service()->copy(sourceId: self::SOURCE, uid: 'dave', request: ['title' => 'X']);

		$this->assertSame(['id' => self::SOURCE, 'register' => 'planninq', 'schema' => 'project', '_rbac' => true, '_multitenancy' => true], $this->objects->finds[0]);
	}//end testTheSourceIsReadAsTheCaller()
}//end class
