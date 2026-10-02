<?php

/**
 * Planninq ProjectCopyService
 *
 * Copies a project, or starts one from a template, on the server: the new
 * project with the caller as owner, then the chosen parts (columns, phases,
 * tasks parent before child, dependencies between copied tasks, people),
 * every reference remapped to the copies and every date shifted to the new
 * start date. A failure deletes what the copy wrote.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectCopyException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Copies a project with its structure.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) One copy, six steps, each a small method.
 */
class ProjectCopyService {

	/**
	 * The register every planninq object lives in.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * The parts a copy may include.
	 *
	 * @var array<int,string>
	 */
	public const PARTS = ['columns', 'tasks', 'phases', 'dependencies', 'people'];

	/**
	 * The parts a copy includes when the request names none.
	 *
	 * @var array<int,string>
	 */
	public const DEFAULT_PARTS = ['columns', 'tasks', 'phases', 'dependencies'];

	/**
	 * Project fields a copy takes over as they are.
	 *
	 * @var array<int,string>
	 */
	private const PROJECT_FIELDS = [
		'description',
		'color',
		'icon',
		'labels',
		'customFields',
		'portfolio',
		'parent',
		'client',
		'autoSchedule',
		'billable',
		'billingModel',
		'budgetHours',
		'budgetAmount',
		'hourlyRate',
		'defaultAssignee',
	];

	/**
	 * The role lists that come along when people are chosen (never the owner).
	 *
	 * @var array<int,string>
	 */
	private const PEOPLE_FIELDS = ['managers', 'members', 'viewers', 'managerGroups', 'memberGroups', 'viewerGroups'];

	/**
	 * Fields no copied object keeps: access copies the membership listener
	 * writes, and the source's own state.
	 *
	 * @var array<int,string>
	 */
	private const DROPPED_FIELDS = [
		'id',
		'@self',
		'members',
		'portfolioReaders',
		'viewers',
		'memberGroups',
		'viewerGroups',
		'metadata',
	];

	/**
	 * Task fields that belong to the work done on the source, not to its plan.
	 *
	 * @var array<int,string>
	 */
	private const TASK_STATE_FIELDS = [
		'key',
		'completedAt',
		'resolvedAt',
		'resolution',
		'percentComplete',
		'remainingEstimate',
		'calendarEventUid',
		'zaakUuid',
		'release',
		'reporter',
	];

	/**
	 * Task fields that name people, kept only when people are chosen.
	 *
	 * @var array<int,string>
	 */
	private const TASK_PEOPLE_FIELDS = ['assignedTo', 'sharedWith', 'watchers', 'contractorRef'];

	/**
	 * Task references to other copied objects, by the map they resolve in.
	 *
	 * @var array<string,string>
	 */
	private const TASK_REFERENCES = ['column' => 'column', 'phase' => 'projectPhase', 'parent' => 'task', 'epic' => 'task'];

	/**
	 * The ids the copy wrote, per schema, so a failure can delete them.
	 *
	 * @var array<string,array<int,string>>
	 */
	private array $written = [];

	/**
	 * Old id => new id, per schema.
	 *
	 * @var array<string,array<string,string>>
	 */
	private array $map = [];

	/**
	 * Days every date moves; null when the copy carries no dates.
	 *
	 * @var integer|null
	 */
	private ?int $shift = 0;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService at runtime.
	 * @param ProjectMembershipService $membership Reads planninq objects with RBAC off.
	 * @param SettingsService          $settings   The creation policy.
	 * @param WorkItemKeyService       $keys       The project key rules.
	 * @param BoardColumnService       $columns    Default columns when columns are not copied.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private ProjectMembershipService $membership,
		private SettingsService $settings,
		private WorkItemKeyService $keys,
		private BoardColumnService $columns,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Copy a project.
	 *
	 * @param string              $sourceId The project (or template) to copy.
	 * @param string              $uid      The caller, owner of the copy.
	 * @param array<int,string>   $groupIds The caller's groups.
	 * @param bool                $isAdmin  Whether the caller is an admin.
	 * @param array<string,mixed> $options  title, key, startDate (Y-m-d) and parts.
	 *
	 * @return array{id:string,project:array<string,mixed>,counts:array<string,int>}
	 *
	 * @throws ProjectCopyException When the copy is refused or fails; a failure leaves nothing.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function copy(string $sourceId, string $uid, array $groupIds, bool $isAdmin, array $options): array {
		$request = $this->validated(options: $options);
		if ($this->settings->canCurrentUserCreateProject() === false) {
			throw new ProjectCopyException(status: 403, reason: 'planninq-copy-policy', message: 'You may not create projects.');
		}

		$source = $this->membership->objectData(schema: 'project', id: $sourceId);
		if ($source === null) {
			throw new ProjectCopyException(status: 404, reason: 'planninq-copy-not-found', message: 'The project to copy was not found.');
		}

		if ($isAdmin === false && $this->mayCopy(project: $source, uid: $uid, groupIds: $groupIds) === false) {
			throw new ProjectCopyException(status: 403, reason: 'planninq-copy-forbidden', message: 'Only an owner or manager of this project may copy it.');
		}

		$this->written = [];
		$this->map     = ['column' => [], 'projectPhase' => [], 'task' => []];
		$this->shift   = $this->shiftDays(from: (string)($source['startDate'] ?? ''), to: $request['startDate']);

		return $this->run(sourceId: $sourceId, source: $source, uid: $uid, request: $request);
	}//end copy()

	/**
	 * Run the steps; on a failure delete what was written and name the step.
	 *
	 * @param string              $sourceId The source project id.
	 * @param array<string,mixed> $source   The source project's data.
	 * @param string              $uid      The caller.
	 * @param array<string,mixed> $request  The validated options.
	 *
	 * @return array{id:string,project:array<string,mixed>,counts:array<string,int>}
	 */
	private function run(string $sourceId, array $source, string $uid, array $request): array {
		$objects = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$parts   = $request['parts'];
		$step    = 'project';
		$counts  = ['columns' => 0, 'phases' => 0, 'tasks' => 0, 'dependencies' => 0];
		try {
			$project = $this->projectBody(source: $source, sourceId: $sourceId, uid: $uid, request: $request);
			$newId   = $this->write(objects: $objects, schema: 'project', data: $project);

			$step = 'columns';
			$counts['columns'] = $this->copyColumns(objects: $objects, sourceId: $sourceId, newId: $newId, include: in_array('columns', $parts, true));
			$step = 'phases';
			$counts['phases'] = $this->copyChildren(objects: $objects, schema: 'projectPhase', sourceId: $sourceId, newId: $newId, include: in_array('phases', $parts, true));
			$step = 'tasks';
			$counts['tasks'] = $this->copyTasks(objects: $objects, sourceId: $sourceId, newId: $newId, request: $request);
			$step = 'dependencies';
			$counts['dependencies'] = $this->copyDependencies(objects: $objects, include: in_array('dependencies', $parts, true));

			$step = 'finish';
			$project['metadata'] = ['copiedFrom' => $sourceId];
			$objects->saveObject(object: $project, register: self::REGISTER, schema: 'project', uuid: $newId, _rbac: false, _multitenancy: false);
		} catch (ProjectCopyException $e) {
			$this->rollBack(objects: $objects);
			throw $e;
		} catch (\Throwable $e) {
			$this->rollBack(objects: $objects);
			$this->logger->error('Planninq: a project copy failed and was removed', ['source' => $sourceId, 'step' => $step, 'exception' => $e->getMessage()]);
			throw new ProjectCopyException(status: 500, reason: 'planninq-copy-failed', message: 'The copy failed while copying ' . $step . '; nothing was kept.', step: $step);
		}//end try

		return ['id' => $newId, 'project' => ['id' => $newId] + $project, 'counts' => $counts];
	}//end run()

	/**
	 * The options, checked: a title, a well-formed key that is free, a Y-m-d start date, known parts.
	 *
	 * @param array<string,mixed> $options The request options.
	 *
	 * @return array{title:string,key:string,startDate:string,parts:array<int,string>}
	 */
	private function validated(array $options): array {
		$title = trim((string)($options['title'] ?? ''));
		if ($title === '') {
			throw new ProjectCopyException(status: 400, reason: 'planninq-copy-title', message: 'A copy needs a title.');
		}

		$startDate = trim((string)($options['startDate'] ?? ''));
		if ($startDate !== '' && $this->isDate(value: $startDate) === false) {
			throw new ProjectCopyException(status: 400, reason: 'planninq-copy-start-date', message: 'The start date is a date like 2027-06-01.');
		}

		$parts = $this->parts(raw: ($options['parts'] ?? null));

		return ['title' => $title, 'key' => $this->checkedKey(raw: ($options['key'] ?? null)), 'startDate' => $startDate, 'parts' => $parts];
	}//end validated()

	/**
	 * The chosen parts, or the default when none are named.
	 *
	 * @param mixed $raw The request's parts.
	 *
	 * @return array<int,string>
	 */
	private function parts(mixed $raw): array {
		if ($raw === null) {
			return self::DEFAULT_PARTS;
		}

		$parts = array_values(array_map('strval', (array)$raw));
		if (array_diff($parts, self::PARTS) !== []) {
			throw new ProjectCopyException(status: 400, reason: 'planninq-copy-parts', message: 'A copy takes columns, tasks, phases, dependencies and people.');
		}

		return $parts;
	}//end parts()

	/**
	 * The new project's key, normalised and checked like a created project's.
	 *
	 * @param mixed $raw The requested key.
	 *
	 * @return string The key, or '' for none.
	 */
	private function checkedKey(mixed $raw): string {
		$key = $this->keys->normalise(key: $raw);
		if ($key === '') {
			return '';
		}

		if ($this->keys->isValidFormat(key: $key) === false) {
			throw new ProjectCopyException(status: 400, reason: 'planninq-project-key-format', message: 'A key has 2 to 10 letters and digits and starts with a letter.');
		}

		if ($this->keys->isTaken(key: $key) === true) {
			throw new ProjectCopyException(status: 409, reason: 'planninq-project-key-used', message: 'This key is already used by another project.');
		}

		return $key;
	}//end checkedKey()

	/**
	 * Whether the caller may copy: anyone who may create starts from a
	 * template; any other project is copied by its owner, a manager, or a
	 * member of an owning or manager group.
	 *
	 * @param array<string,mixed> $project  The source project's data.
	 * @param string              $uid      The caller.
	 * @param array<int,string>   $groupIds The caller's groups.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function mayCopy(array $project, string $uid, array $groupIds): bool {
		if (($project['isTemplate'] ?? false) === true) {
			return true;
		}

		if ($uid !== '' && (($project['owner'] ?? null) === $uid || in_array($uid, (array)($project['managers'] ?? []), true) === true)) {
			return true;
		}

		$groups = array_merge((array)($project['ownerGroups'] ?? []), (array)($project['managerGroups'] ?? []));
		return array_intersect($groups, $groupIds) !== [];
	}//end mayCopy()

	/**
	 * The new project's data.
	 *
	 * @param array<string,mixed> $source   The source project's data.
	 * @param string              $sourceId The source project id.
	 * @param string              $uid      The caller, the copy's owner.
	 * @param array<string,mixed> $request  The validated options.
	 *
	 * @return array<string,mixed>
	 */
	private function projectBody(array $source, string $sourceId, string $uid, array $request): array {
		$body = array_intersect_key($source, array_flip(self::PROJECT_FIELDS));
		$body['title']      = $request['title'];
		$body['status']     = 'active';
		$body['isTemplate'] = false;
		$body['owner']      = $uid;
		$body['members']    = [$uid];
		if (in_array('people', $request['parts'], true) === true) {
			foreach (self::PEOPLE_FIELDS as $field) {
				$body[$field] = array_values(array_unique(array_map('strval', (array)($source[$field] ?? []))));
			}

			$body['members'] = array_values(array_unique(array_merge([$uid], $body['members'])));
		}

		if ($request['key'] !== '') {
			$body['key'] = $request['key'];
		}

		$startDate = $request['startDate'];
		if ($startDate === '') {
			$startDate = (string)($source['startDate'] ?? '');
		}

		if ($startDate !== '') {
			$body['startDate'] = $startDate;
		}

		$body = $this->withShiftedDates(data: $body + ['endDate' => ($source['endDate'] ?? null)], fields: ['endDate']);
		$body['metadata'] = ['copyState' => 'copying', 'copiedFrom' => $sourceId];

		return $body;
	}//end projectBody()

	/**
	 * Copy the columns, or give the copy the default columns when they are not chosen.
	 *
	 * @param object $objects  OpenRegister's ObjectService.
	 * @param string $sourceId The source project id.
	 * @param string $newId    The copy's id.
	 * @param bool   $include  Whether columns were chosen.
	 *
	 * @return int The number of columns copied.
	 */
	private function copyColumns(object $objects, string $sourceId, string $newId, bool $include): int {
		if ($include === true) {
			return $this->copyChildren(objects: $objects, schema: 'column', sourceId: $sourceId, newId: $newId, include: true);
		}

		foreach ($this->columns->createDefaultColumns(projectId: $newId) as $column) {
			$this->written['column'][] = (string)$column['id'];
		}

		return 0;
	}//end copyColumns()

	/**
	 * Copy every object of one project-scoped schema (columns, phases).
	 *
	 * @param object $objects  OpenRegister's ObjectService.
	 * @param string $schema   The schema slug.
	 * @param string $sourceId The source project id.
	 * @param string $newId    The copy's id.
	 * @param bool   $include  Whether this part was chosen.
	 *
	 * @return int The number copied.
	 */
	private function copyChildren(object $objects, string $schema, string $sourceId, string $newId, bool $include): int {
		if ($include === false) {
			return 0;
		}

		$rows = $this->membership->rows(schema: $schema, filters: ['project' => $sourceId]);
		foreach ($rows as $row) {
			$data = array_diff_key($row['data'], array_flip(self::DROPPED_FIELDS));
			$data['project'] = $newId;
			$data = $this->withShiftedDates(data: $data, fields: ['startDate', 'endDate']);
			$this->map[$schema][$row['id']] = $this->write(objects: $objects, schema: $schema, data: $data);
		}

		return count($rows);
	}//end copyChildren()

	/**
	 * Copy the tasks, parents and epics before the tasks that name them.
	 *
	 * @param object              $objects  OpenRegister's ObjectService.
	 * @param string              $sourceId The source project id.
	 * @param string              $newId    The copy's id.
	 * @param array<string,mixed> $request  The validated options.
	 *
	 * @return int The number copied.
	 */
	private function copyTasks(object $objects, string $sourceId, string $newId, array $request): int {
		if (in_array('tasks', $request['parts'], true) === false) {
			return 0;
		}

		$people = in_array('people', $request['parts'], true);
		$tasks  = $this->ordered(rows: $this->membership->rows(schema: 'task', filters: ['project' => $sourceId]));
		foreach ($tasks as $row) {
			$data = $this->taskBody(data: $row['data'], newId: $newId, people: $people);
			$this->map['task'][$row['id']] = $this->write(objects: $objects, schema: 'task', data: $data);
		}

		return count($tasks);
	}//end copyTasks()

	/**
	 * A copied task: open, its plan kept, the source's state and (unless chosen) people dropped, references remapped.
	 *
	 * @param array<string,mixed> $data   The source task's data.
	 * @param string              $newId  The copy's id.
	 * @param bool                $people Whether people come along.
	 *
	 * @return array<string,mixed>
	 */
	private function taskBody(array $data, string $newId, bool $people): array {
		$drop = array_merge(self::DROPPED_FIELDS, self::TASK_STATE_FIELDS);
		if ($people === false) {
			$drop = array_merge($drop, self::TASK_PEOPLE_FIELDS);
		}

		$data = array_diff_key($data, array_flip($drop));
		$data['project'] = $newId;
		$data['status']  = 'open';
		foreach (self::TASK_REFERENCES as $field => $schema) {
			$old = (string)($data[$field] ?? '');
			unset($data[$field]);
			if ($old !== '' && isset($this->map[$schema][$old]) === true) {
				$data[$field] = $this->map[$schema][$old];
			}
		}

		return $this->withShiftedDates(data: $this->withChecklistUndone(data: $data), fields: ['startDate', 'dueDate']);
	}//end taskBody()

	/**
	 * Copy the dependencies whose two tasks were both copied.
	 *
	 * @param object $objects OpenRegister's ObjectService.
	 * @param bool   $include Whether dependencies were chosen.
	 *
	 * @return int The number copied.
	 */
	private function copyDependencies(object $objects, bool $include): int {
		if ($include === false || $this->map['task'] === []) {
			return 0;
		}

		$copied = 0;
		foreach ($this->membership->rows(schema: 'dependency', filters: ['blocker' => array_keys($this->map['task'])]) as $row) {
			$blocked = ($this->map['task'][(string)($row['data']['blocked'] ?? '')] ?? null);
			if ($blocked === null) {
				continue;
			}

			$data = array_diff_key($row['data'], array_flip(self::DROPPED_FIELDS));
			$data['blocker'] = $this->map['task'][(string)$row['data']['blocker']];
			$data['blocked'] = $blocked;
			$this->write(objects: $objects, schema: 'dependency', data: $data);
			$copied++;
		}

		return $copied;
	}//end copyDependencies()

	/**
	 * Tasks in an order where a task's parent and epic come before it.
	 *
	 * A reference to a task outside the project, or a cycle, does not hold the
	 * order up: what is left after no more progress is appended as it is.
	 *
	 * @param array<int,array{id:string,data:array<string,mixed>}> $rows The tasks.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 */
	private function ordered(array $rows): array {
		$pending = array_column($rows, null, 'id');
		$done    = [];
		$ordered = [];
		do {
			$progress = false;
			foreach ($pending as $id => $row) {
				if ($this->waitsFor(data: $row['data'], pending: $pending, done: $done) === true) {
					continue;
				}

				$ordered[]  = $row;
				$done[$id]  = true;
				$progress   = true;
				unset($pending[$id]);
			}
		} while ($progress === true && $pending !== []);

		return array_merge($ordered, array_values($pending));
	}//end ordered()

	/**
	 * Whether a task names a parent or epic in this project that is not copied yet.
	 *
	 * @param array<string,mixed> $data    The task's data.
	 * @param array<string,mixed> $pending The tasks not yet ordered, by id.
	 * @param array<string,bool>  $done    The tasks already ordered, by id.
	 *
	 * @return bool
	 */
	private function waitsFor(array $data, array $pending, array $done): bool {
		foreach (['parent', 'epic'] as $field) {
			$ref = (string)($data[$field] ?? '');
			if ($ref !== '' && isset($pending[$ref]) === true && isset($done[$ref]) === false) {
				return true;
			}
		}

		return false;
	}//end waitsFor()

	/**
	 * Every checklist item undone.
	 *
	 * @param array<string,mixed> $data The task's data.
	 *
	 * @return array<string,mixed>
	 */
	private function withChecklistUndone(array $data): array {
		if (is_array($data['checklist'] ?? null) === false) {
			return $data;
		}

		foreach (array_keys($data['checklist']) as $index) {
			if (is_array($data['checklist'][$index]) === true) {
				$data['checklist'][$index]['done'] = false;
			}
		}

		return $data;
	}//end withChecklistUndone()

	/**
	 * The data with each date field moved by the shift, or removed when the copy carries no dates.
	 *
	 * @param array<string,mixed> $data   The object data.
	 * @param array<int,string>   $fields The date fields (Y-m-d).
	 *
	 * @return array<string,mixed>
	 */
	private function withShiftedDates(array $data, array $fields): array {
		foreach ($fields as $field) {
			$value = (string)($data[$field] ?? '');
			unset($data[$field]);
			if ($value === '' || $this->shift === null || $this->isDate(value: $value) === false) {
				continue;
			}

			$data[$field] = (new \DateTimeImmutable($value))->modify(sprintf('%+d days', $this->shift))->format('Y-m-d');
		}

		return $data;
	}//end withShiftedDates()

	/**
	 * Days from the source's start to the requested start: 0 with no requested
	 * start (dates stay), null when the source has no start (no dates are copied).
	 *
	 * @param string $from The source's startDate.
	 * @param string $to   The requested startDate.
	 *
	 * @return int|null
	 */
	private function shiftDays(string $from, string $to): ?int {
		if ($to === '') {
			return 0;
		}

		if ($this->isDate(value: $from) === false) {
			return null;
		}

		return (int)(new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');
	}//end shiftDays()

	/**
	 * Whether a value is a real Y-m-d date.
	 *
	 * @param string $value The value.
	 *
	 * @return bool
	 */
	private function isDate(string $value): bool {
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
			return false;
		}

		return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
	}//end isDate()

	/**
	 * Create one object as the system and remember it for a roll-back.
	 *
	 * @param object              $objects OpenRegister's ObjectService.
	 * @param string              $schema  The schema slug.
	 * @param array<string,mixed> $data    The object data.
	 *
	 * @return string The new object's id.
	 */
	private function write(object $objects, string $schema, array $data): string {
		$entity = $objects->saveObject(object: $data, register: self::REGISTER, schema: $schema, _rbac: false, _multitenancy: false);
		$id     = (string)($entity?->getUuid() ?? '');
		if ($id === '') {
			throw new \RuntimeException('OpenRegister returned no id for a new ' . $schema);
		}

		$this->written[$schema][] = $id;
		return $id;
	}//end write()

	/**
	 * Delete everything the copy wrote, newest first; a delete that fails is logged.
	 *
	 * @param object $objects OpenRegister's ObjectService.
	 *
	 * @return void
	 */
	private function rollBack(object $objects): void {
		foreach (['dependency', 'task', 'projectPhase', 'column', 'project'] as $schema) {
			foreach (array_reverse($this->written[$schema] ?? []) as $id) {
				try {
					$objects->deleteObject(uuid: $id, register: self::REGISTER, schema: $schema, _rbac: false, _multitenancy: false);
				} catch (\Throwable $e) {
					$this->logger->error('Planninq: could not remove part of a failed project copy', ['schema' => $schema, 'object' => $id, 'exception' => $e->getMessage()]);
				}
			}
		}

		$this->written = [];
	}//end rollBack()
}//end class
