<?php

/**
 * Tests for ProjectCopyService: a project copied with its columns, phases,
 * tasks and dependencies, every reference pointing at the copies, dates
 * shifted to the new start, and nothing left behind when a copy fails.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Exception\ProjectCopyException;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\ProjectCopyService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyServiceTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const SOURCE = '11111111-1111-4111-8111-111111111111';

	/**
	 * Saves of these schemas throw once this many objects of them were written.
	 *
	 * @var array<string,int>
	 */
	private array $failAfter = [];

	protected function setUp(): void {
		parent::setUp();
		$test          = $this;
		$this->objects = new class ($test) extends InMemoryObjectService {
			private int $next = 0;

			/** @var array<string,int> */
			private array $written = [];

			public function __construct(private ProjectCopyServiceTest $test) {
			}

			public function saveObject(
				array|object $object,
				?array $extend = [],
				mixed $register = null,
				mixed $schema = null,
				?string $uuid = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $silent = false,
				bool $_validation = true,
			): ?object {
				$limit = $this->test->failAfterFor(schema: (string)$schema);
				if ($uuid === null && $limit !== null && ($this->written[(string)$schema] ?? 0) >= $limit) {
					throw new \RuntimeException('database went away');
				}

				if ($uuid === null) {
					$this->next++;
					$this->written[(string)$schema] = (($this->written[(string)$schema] ?? 0) + 1);
					$uuid = sprintf('00000000-0000-4000-8000-%012d', $this->next);
				}

				return parent::saveObject($object, $extend, $register, $schema, $uuid, $_rbac, $_multitenancy, $silent, $_validation);
			}
		};
		$this->seedSource();
	}//end setUp()

	/**
	 * The limit for a schema, read by the fake ObjectService.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return int|null
	 */
	public function failAfterFor(string $schema): ?int {
		return ($this->failAfter[$schema] ?? null);
	}//end failAfterFor()

	/**
	 * A project of 3 columns, 2 phases, 4 tasks (one a sub-task, one under an
	 * epic), 2 dependencies, 4 people and a time entry.
	 */
	private function seedSource(bool $template = false, ?string $startDate = '2027-03-01'): void {
		$project = [
			'title'         => 'Aanbesteding',
			'status'        => 'active',
			'owner'         => 'olga',
			'managers'      => ['mark'],
			'members'       => ['olga', 'mark', 'mia'],
			'viewers'       => ['vera'],
			'memberGroups'  => ['inkoop'],
			'color'         => '#0082c9',
			'labels'        => ['22222222-2222-4222-8222-222222222222'],
			'key'           => 'AANB',
			'nextTaskNumber' => 5,
			'healthOverall' => 'red',
			'isTemplate'    => $template,
			'endDate'       => '2027-04-30',
		];
		if ($startDate !== null) {
			$project['startDate'] = $startDate;
		}

		$this->objects->rows = [];
		$this->objects->seed('project', self::SOURCE, $project);
		$this->objects->seed('project', 'other-project', ['title' => 'Other', 'status' => 'active', 'owner' => 'zed']);
		foreach (['col-a' => ['To do', 0, 'active'], 'col-b' => ['Doing', 1, 'active'], 'col-c' => ['Done', 2, 'done']] as $id => [$title, $order, $type]) {
			$this->objects->seed('column', $id, ['title' => $title, 'project' => self::SOURCE, 'order' => $order, 'type' => $type, 'members' => ['olga']]);
		}

		$this->objects->seed('projectPhase', 'ph-1', ['title' => 'Voorbereiding', 'project' => self::SOURCE, 'order' => 0, 'startDate' => '2027-03-01', 'endDate' => '2027-03-20']);
		$this->objects->seed('projectPhase', 'ph-2', ['title' => 'Gunning', 'project' => self::SOURCE, 'order' => 1]);
		// The sub-task comes first, so the copy must order parents before children.
		$this->objects->seed('task', 't-sub', ['title' => 'Bestek lezen', 'status' => 'done', 'project' => self::SOURCE, 'parent' => 't-epic-child', 'column' => 'col-c', 'key' => 'AANB-4', 'completedAt' => '2027-03-05T10:00:00+00:00']);
		$this->objects->seed('task', 't-epic', ['title' => 'Bestek', 'status' => 'in_progress', 'project' => self::SOURCE, 'issueType' => 'epic', 'column' => 'col-b', 'phase' => 'ph-1', 'assignedTo' => 'mia', 'watchers' => ['mark']]);
		$this->objects->seed('task', 't-epic-child', ['title' => 'Bestek schrijven', 'status' => 'open', 'project' => self::SOURCE, 'epic' => 't-epic', 'column' => 'col-a', 'phase' => 'ph-1', 'dueDate' => '2027-03-15', 'startDate' => '2027-03-02', 'checklist' => [['id' => 'c1', 'text' => 'Eisen', 'done' => true]]]);
		$this->objects->seed('task', 't-plain', ['title' => 'Publiceren', 'status' => 'blocked', 'project' => self::SOURCE, 'column' => 'col-a', 'phase' => 'ph-2', 'reporter' => 'olga', 'release' => 'rel-1']);
		$this->objects->seed('task', 't-elsewhere', ['title' => 'Not ours', 'status' => 'open', 'project' => 'other-project']);
		$this->objects->seed('dependency', 'dep-1', ['blocker' => 't-epic-child', 'blocked' => 't-plain', 'type' => 'relates']);
		$this->objects->seed('dependency', 'dep-2', ['blocker' => 't-epic', 'blocked' => 't-plain']);
		$this->objects->seed('dependency', 'dep-out', ['blocker' => 't-plain', 'blocked' => 't-elsewhere']);
		$this->objects->seed('plannedTimeEntry', 'te-1', ['task' => 't-plain', 'project' => self::SOURCE, 'hours' => 2]);
	}//end seedSource()

	private function service(bool $mayCreate = true): ProjectCopyService {
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('canCurrentUserCreateProject')->willReturn($mayCreate);
		$settings->method('getAdminSettings')->willReturn(['default_columns' => '["Nieuw","Klaar"]']);

		$keys = $this->createMock(originalClassName: WorkItemKeyService::class);
		$keys->method('normalise')->willReturnCallback(static fn (mixed $key): string => strtoupper(trim((string)$key)));
		$keys->method('isValidFormat')->willReturnCallback(static fn (string $key): bool => preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) === 1);
		$keys->method('isTaken')->willReturnCallback(static fn (string $key): bool => $key === 'AANB');

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectCopyService(
			container: $this->container(),
			membership: $this->membershipService(),
			settings: $settings,
			keys: $keys,
			columns: new BoardColumnService(
				container: $this->container(),
				membership: $this->membershipService(),
				settings: $settings,
				logger: $logger,
				appManager: $appManager
			),
			logger: $logger
		);
	}//end service()

	/**
	 * Copy as mark (a manager) unless told otherwise.
	 *
	 * @param array<string,mixed> $options The copy options.
	 *
	 * @return array<string,mixed>
	 */
	private function copy(array $options, string $uid = 'mark', array $groups = [], bool $admin = false, bool $mayCreate = true): array {
		return $this->service(mayCreate: $mayCreate)->copy(sourceId: self::SOURCE, uid: $uid, groupIds: $groups, isAdmin: $admin, options: $options);
	}//end copy()

	/**
	 * The new rows of a schema (not seeded).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function created(string $schema): array {
		return array_filter(
			($this->objects->rows[$schema] ?? []),
			static fn (string $id): bool => str_starts_with($id, '00000000-'),
			ARRAY_FILTER_USE_KEY
		);
	}//end created()

	/**
	 * Scenario "Copying a project without its people": columns, tasks and
	 * dependencies come along, every reference points at a copy, tasks start open.
	 */
	public function testCopyRemapsEveryReferenceAndOpensEveryTask(): void {
		$result = $this->copy(options: ['title' => 'Aanbesteding wegbeheer', 'parts' => ['columns', 'tasks', 'phases', 'dependencies']]);

		$newId = $result['id'];
		self::assertSame('Aanbesteding wegbeheer', $this->objects->rows['project'][$newId]['title']);
		self::assertSame(['columns' => 3, 'phases' => 2, 'tasks' => 4, 'dependencies' => 2], $result['counts']);

		$columns = $this->created('column');
		$phases  = $this->created('projectPhase');
		$tasks   = $this->created('task');
		self::assertCount(3, $columns);
		self::assertCount(2, $phases);
		self::assertCount(4, $tasks);
		foreach (array_merge($columns, $phases, $tasks) as $row) {
			self::assertSame($newId, $row['project']);
		}

		$byTitle = [];
		foreach ($tasks as $id => $task) {
			$byTitle[$task['title']] = ['id' => $id] + $task;
			self::assertSame('open', $task['status'], $task['title'] . ' starts open');
			self::assertArrayNotHasKey('key', $task, 'the new project numbers its own tasks');
			self::assertArrayNotHasKey('completedAt', $task);
			self::assertArrayNotHasKey('release', $task, 'releases are not copied');
		}

		$columnIds = array_keys($columns);
		$phaseIds  = array_keys($phases);
		foreach ($byTitle as $task) {
			self::assertContains($task['column'], $columnIds, $task['title'] . ' sits in a copied column');
			if (isset($task['phase']) === true) {
				self::assertContains($task['phase'], $phaseIds, $task['title'] . ' sits in a copied phase');
			}
		}

		self::assertSame($byTitle['Bestek schrijven']['id'], $byTitle['Bestek lezen']['parent']);
		self::assertSame($byTitle['Bestek']['id'], $byTitle['Bestek schrijven']['epic']);
		self::assertSame([false], array_column($byTitle['Bestek schrijven']['checklist'], 'done'), 'checklist items start undone');

		$deps = $this->created('dependency');
		self::assertCount(2, $deps, 'the dependency to another project is not copied');
		$taskIds = array_keys($tasks);
		foreach ($deps as $dep) {
			self::assertContains($dep['blocker'], $taskIds);
			self::assertContains($dep['blocked'], $taskIds);
		}

		self::assertSame([], $this->created('plannedTimeEntry'), 'time entries are not copied');
		self::assertCount(4, array_filter($this->objects->rows['task'], static fn (array $t): bool => $t['project'] === self::SOURCE), 'the source keeps its tasks');
	}//end testCopyRemapsEveryReferenceAndOpensEveryTask()

	/**
	 * Without people the caller is the only person; owner is the caller, never the source's owner.
	 */
	public function testCopyWithoutPeopleLeavesOnlyTheCaller(): void {
		$result  = $this->copy(options: ['title' => 'Kopie', 'parts' => ['columns', 'tasks']]);
		$project = $this->objects->rows['project'][$result['id']];

		self::assertSame('mark', $project['owner']);
		self::assertSame(['mark'], $project['members']);
		foreach (['managers', 'viewers', 'managerGroups', 'memberGroups', 'viewerGroups', 'ownerGroups'] as $list) {
			self::assertSame([], ($project[$list] ?? []), $list . ' is empty');
		}

		foreach ($this->created('task') as $task) {
			self::assertArrayNotHasKey('assignedTo', $task);
			self::assertArrayNotHasKey('watchers', $task);
			self::assertArrayNotHasKey('reporter', $task);
		}

		self::assertFalse($project['isTemplate']);
		self::assertSame('active', $project['status']);
		self::assertArrayNotHasKey('key', $project);
		self::assertArrayNotHasKey('healthOverall', $project, 'status reports are the source\'s, not the copy\'s');
		self::assertSame([], $this->created('projectPhase'), 'phases were not chosen');
		self::assertSame([], $this->created('dependency'), 'dependencies were not chosen');
		foreach ($this->created('task') as $task) {
			self::assertArrayNotHasKey('phase', $task, 'a phase that was not copied is not referenced');
		}
	}//end testCopyWithoutPeopleLeavesOnlyTheCaller()

	public function testCopyWithPeopleKeepsTheRolesAndAddsTheCaller(): void {
		$result  = $this->copy(options: ['title' => 'Kopie', 'parts' => ['people']], uid: 'olga');
		$project = $this->objects->rows['project'][$result['id']];

		self::assertSame('olga', $project['owner']);
		self::assertSame(['mark'], $project['managers']);
		self::assertSame(['olga', 'mark', 'mia'], $project['members']);
		self::assertSame(['vera'], $project['viewers']);
		self::assertSame(['inkoop'], $project['memberGroups']);
	}//end testCopyWithPeopleKeepsTheRolesAndAddsTheCaller()

	/**
	 * Without columns the copy gets the admin's default columns, so its board is never empty.
	 */
	public function testCopyWithoutColumnsGetsTheDefaultColumns(): void {
		$result = $this->copy(options: ['title' => 'Kopie', 'parts' => ['tasks']]);

		self::assertSame(['Nieuw', 'Klaar'], array_values(array_column($this->created('column'), 'title')));
		self::assertSame(0, $result['counts']['columns']);
		foreach ($this->created('task') as $task) {
			self::assertArrayNotHasKey('column', $task, 'the tasks land in the backlog');
		}
	}//end testCopyWithoutColumnsGetsTheDefaultColumns()

	/**
	 * Scenario "Starting a project from a template": a task due on 15 March is due on 15 June.
	 */
	public function testDatesShiftByTheNewStartDate(): void {
		$result  = $this->copy(options: ['title' => 'Aanbesteding wegbeheer', 'startDate' => '2027-06-01', 'parts' => ['columns', 'tasks', 'phases']]);
		$project = $this->objects->rows['project'][$result['id']];

		self::assertSame('2027-06-01', $project['startDate']);
		self::assertSame('2027-07-31', $project['endDate'], '30 April plus the same 92 days');
		$byTitle = array_column($this->created('task'), null, 'title');
		self::assertSame('2027-06-15', $byTitle['Bestek schrijven']['dueDate']);
		self::assertSame('2027-06-02', $byTitle['Bestek schrijven']['startDate']);
		$phases = array_column($this->created('projectPhase'), null, 'title');
		self::assertSame('2027-06-01', $phases['Voorbereiding']['startDate']);
		self::assertSame('2027-06-20', $phases['Voorbereiding']['endDate']);
	}//end testDatesShiftByTheNewStartDate()

	public function testATemplateWithoutAStartDateCopiesNoDates(): void {
		$this->seedSource(template: true, startDate: null);
		$result = $this->copy(options: ['title' => 'Kopie', 'startDate' => '2027-06-01', 'parts' => ['tasks', 'phases']], uid: 'nina');

		self::assertSame('2027-06-01', $this->objects->rows['project'][$result['id']]['startDate']);
		self::assertArrayNotHasKey('endDate', $this->objects->rows['project'][$result['id']]);
		foreach (array_merge($this->created('task'), $this->created('projectPhase')) as $row) {
			self::assertArrayNotHasKey('dueDate', $row);
			self::assertArrayNotHasKey('startDate', $row);
			self::assertArrayNotHasKey('endDate', $row);
		}
	}//end testATemplateWithoutAStartDateCopiesNoDates()

	/**
	 * Scenario "A failed copy leaves nothing behind": the answer names the step.
	 */
	public function testAFailedCopyLeavesNothingBehind(): void {
		$this->failAfter = ['task' => 2];
		$before          = $this->objects->rows;

		try {
			$this->copy(options: ['title' => 'Kopie', 'parts' => ['columns', 'tasks', 'phases', 'dependencies']]);
			self::fail('the copy should fail');
		} catch (ProjectCopyException $e) {
			self::assertSame(500, $e->getStatus());
			self::assertSame('tasks', $e->getStep());
		}

		foreach (['project', 'column', 'projectPhase', 'task', 'dependency'] as $schema) {
			self::assertSame([], $this->created($schema), 'no ' . $schema . ' of the partial copy remains');
			self::assertSame(($before[$schema] ?? []), ($this->objects->rows[$schema] ?? []), 'the source ' . $schema . ' rows are untouched');
		}
	}//end testAFailedCopyLeavesNothingBehind()

	public function testCopyStateIsSetWhileCopyingAndClearedAfter(): void {
		$result = $this->copy(options: ['title' => 'Kopie']);

		$projectSaves = array_values(array_filter($this->objects->saves, static fn (array $s): bool => $s['schema'] === 'project'));
		self::assertSame('copying', $projectSaves[0]['object']['metadata']['copyState']);
		$final = $this->objects->rows['project'][$result['id']];
		self::assertArrayNotHasKey('copyState', ($final['metadata'] ?? []));
		self::assertSame(self::SOURCE, $final['metadata']['copiedFrom']);
	}//end testCopyStateIsSetWhileCopyingAndClearedAfter()

	/**
	 * @return array<string,array{0:array<string,mixed>,1:string,2:array<int,string>,3:bool,4:bool,5:int,6:string}>
	 */
	public static function refusals(): array {
		return [
			'creation not allowed'   => [['title' => 'Kopie'], 'mark', [], false, false, 403, 'planninq-copy-policy'],
			'a member is no manager' => [['title' => 'Kopie'], 'mia', [], false, true, 403, 'planninq-copy-forbidden'],
			'a stranger'             => [['title' => 'Kopie'], 'zed', [], false, true, 403, 'planninq-copy-forbidden'],
			'no title'               => [['title' => '  '], 'mark', [], false, true, 400, 'planninq-copy-title'],
			'a bad start date'       => [['title' => 'Kopie', 'startDate' => '1 June'], 'mark', [], false, true, 400, 'planninq-copy-start-date'],
			'an unknown part'        => [['title' => 'Kopie', 'parts' => ['files']], 'mark', [], false, true, 400, 'planninq-copy-parts'],
			'a key in use'           => [['title' => 'Kopie', 'key' => 'aanb'], 'mark', [], false, true, 409, 'planninq-project-key-used'],
			'a malformed key'        => [['title' => 'Kopie', 'key' => '1-x'], 'mark', [], false, true, 400, 'planninq-project-key-format'],
		];
	}//end refusals()

	/**
	 * @dataProvider refusals
	 */
	public function testRefusals(array $options, string $uid, array $groups, bool $admin, bool $mayCreate, int $status, string $code): void {
		try {
			$this->copy(options: $options, uid: $uid, groups: $groups, admin: $admin, mayCreate: $mayCreate);
			self::fail('the copy should be refused');
		} catch (ProjectCopyException $e) {
			self::assertSame($status, $e->getStatus());
			self::assertSame($code, $e->getReason());
		}

		self::assertSame([], $this->created('project'), 'a refused copy writes nothing');
	}//end testRefusals()

	public function testAnUnknownSourceIsNotFound(): void {
		$this->expectException(ProjectCopyException::class);
		$this->expectExceptionMessage('not found');
		$this->service()->copy(sourceId: 'nope', uid: 'mark', groupIds: [], isAdmin: false, options: ['title' => 'Kopie']);
	}//end testAnUnknownSourceIsNotFound()

	/**
	 * Who may copy: the owner, a manager, an owning or manager group, an
	 * admin; anyone who may create a project for a template.
	 */
	public function testWhoMayCopy(): void {
		self::assertNotSame('', $this->copy(options: ['title' => 'A'], uid: 'olga')['id']);
		self::assertNotSame('', $this->copy(options: ['title' => 'B'], uid: 'zed', admin: true)['id']);
		$this->objects->rows['project'][self::SOURCE]['managerGroups'] = ['pmo'];
		self::assertNotSame('', $this->copy(options: ['title' => 'C'], uid: 'pia', groups: ['pmo'])['id']);

		$this->seedSource(template: true);
		self::assertNotSame('', $this->copy(options: ['title' => 'D'], uid: 'nina')['id'], 'anyone who may create starts from a template');
	}//end testWhoMayCopy()

	/**
	 * The exact payloads written into OpenRegister validate against the real schemas.
	 */
	public function testEveryPayloadValidatesAgainstTheRegisterSchema(): void {
		$this->copy(options: ['title' => 'Kopie', 'startDate' => '2027-06-01', 'key' => 'KOP', 'parts' => ['columns', 'tasks', 'phases', 'dependencies', 'people']]);

		$schemas = [];
		foreach ($this->objects->saves as $save) {
			$schemas[$save['schema']] = true;
			self::assertSame([], $this->registerSchemaErrors(slug: (string)$save['schema'], payload: (array)$save['object']), (string)$save['schema']);
			self::assertFalse($save['_rbac'], 'the copy writes as the system after its own checks');
		}

		self::assertSame(['project', 'column', 'projectPhase', 'task', 'dependency'], array_keys($schemas));
	}//end testEveryPayloadValidatesAgainstTheRegisterSchema()

	/**
	 * Control: the validator refuses a project the register refuses.
	 */
	public function testTheValidatorRefusesAProjectTheRegisterRefuses(): void {
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'project', payload: ['title' => 'x', 'status' => 'active', 'isTemplate' => 'yes']));
	}//end testTheValidatorRefusesAProjectTheRegisterRefuses()
}//end class
