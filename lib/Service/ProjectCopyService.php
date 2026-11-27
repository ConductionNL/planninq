<?php

/**
 * Planninq ProjectCopyService
 *
 * Copies a project, or starts one from a template, with every reference
 * inside it pointing at the copies.
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
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use OCA\OpenRegister\Contract\RegisterSchemaSlugs;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectDeletion;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Server-side project copy (design decision 2).
 *
 * The creation policy is checked first, and the source is read as the caller,
 * so a copy reveals nothing the caller could not open. Then the project is
 * created with the caller as owner, followed by its columns, phases, tasks
 * (parents before children) and the dependencies between copied tasks. Every
 * `column`, `phase`, `parent` and `epic` reference points at a copy; one that
 * points outside the copied set is dropped rather than left pointing at the
 * source. Dates move by the distance between the source's start date and the
 * one asked for. Copied tasks start open. People come along only when asked
 * for, and the caller is always the owner. A failure deletes everything the
 * copy wrote.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyService {

	/**
	 * The register every planninq object lives in.
	 */
	public const REGISTER = 'planninq';

	/**
	 * The parts a copy can include, with their default.
	 */
	public const PARTS = ['columns' => true, 'phases' => true, 'tasks' => true, 'dependencies' => true, 'people' => false];

	/**
	 * Project fields a copy carries over; status, review, keys, counters,
	 * health and people are the copy's own or come with `people`.
	 */
	private const PROJECT_FIELDS = [
		'description', 'color', 'icon', 'portfolio', 'customFields', 'labels', 'autoSchedule',
		'client', 'billable', 'billingModel', 'budgetHours', 'budgetAmount', 'hourlyRate',
	];

	/**
	 * Project people lists, copied only with `people`.
	 */
	private const PROJECT_PEOPLE = [
		'managers', 'members', 'viewers', 'ownerGroups', 'managerGroups', 'memberGroups', 'viewerGroups', 'defaultAssignee',
	];

	/**
	 * Task fields a copy never carries: identity, the readable key, history, and what listeners derive.
	 */
	private const TASK_DROPPED = [
		'id', 'uuid', '@self', 'key', 'members', 'portfolioReaders', 'reporter', 'watchers', 'completedAt', 'resolvedAt',
		'resolution', 'calendarEventUid', 'zaakUuid', 'percentComplete', 'remainingEstimate', 'release',
	];

	/**
	 * Task people fields, copied only with `people`.
	 */
	private const TASK_PEOPLE = ['assignedTo', 'sharedWith', 'viewers', 'memberGroups', 'viewerGroups'];

	/**
	 * Date fields shifted with the project, per schema.
	 */
	private const DATE_FIELDS = [
		'project' => ['startDate', 'endDate'],
		'projectPhase' => ['startDate', 'endDate'],
		'task' => ['startDate', 'dueDate'],
	];

	/**
	 * The task fields that point at other copied objects.
	 *
	 * @var list<string>
	 */
	private const TASK_REFERENCES = ['column', 'phase', 'parent', 'epic'];

	/**
	 * The most rows of one schema a copy reads from its source.
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Constructor.
	 *
	 * @param OpenRegisterObjects   $objects        Locates OpenRegister's ObjectService.
	 * @param CreationPolicyService $creationPolicy Whether the caller may create a project.
	 * @param LoggerInterface       $logger         Logs a failed copy and its clean-up.
	 */
	public function __construct(
		private OpenRegisterObjects $objects,
		private CreationPolicyService $creationPolicy,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Copy a project.
	 *
	 * @param string              $sourceId The project or template to copy.
	 * @param string              $uid      The caller, who owns the copy.
	 * @param array<string,mixed> $request  `title` (required), `key`, `startDate` (`YYYY-MM-DD`) and `parts`.
	 *
	 * @return array{status:int,project?:array<string,mixed>,error?:string}
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function copy(string $sourceId, string $uid, array $request): array {
		$objectService = $this->objects->objectService();
		$refusal       = $this->refusal(objectService: $objectService, request: $request);
		if ($refusal !== null) {
			return $refusal;
		}

		$source = $this->readableSource(objectService: $objectService, sourceId: $sourceId);
		if ($source === null) {
			return ['status' => 404, 'error' => 'Project not found.'];
		}

		$run = new ProjectCopyRun(objectService: $objectService, sourceId: $sourceId, plan: self::plan(request: $request, source: $source, uid: $uid));

		$project = $this->copyAll(run: $run, source: $source);
		if ($project === null) {
			return ['status' => 500, 'error' => 'The project could not be copied. Nothing was kept.'];
		}

		return ['status' => 201, 'project' => $project];
	}//end copy()

	/**
	 * What to copy, how far to move the dates, and the new project's title, key and owner.
	 *
	 * @param array<string, mixed> $request The request body.
	 * @param array<string, mixed> $source  The source project's data.
	 * @param string               $uid     The caller, who owns the copy.
	 *
	 * @return array<string, mixed> The plan ProjectCopyRun takes.
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private static function plan(array $request, array $source, string $uid): array {
		return [
			'parts'     => self::parts(requested: (array)($request['parts'] ?? [])),
			'shiftDays' => self::shiftDays(sourceStart: (string)($source['startDate'] ?? ''), newStart: (string)($request['startDate'] ?? '')),
			'identity'  => ['title' => trim((string)($request['title'] ?? '')), 'key' => (string)($request['key'] ?? ''), 'owner' => $uid],
		];
	}//end plan()

	/**
	 * Why a copy cannot start, in the order the checks run: OpenRegister
	 * missing (503), the caller may not create projects (403), no title (400).
	 *
	 * @param object|null          $objectService OpenRegister's ObjectService, or null when it is not available.
	 * @param array<string, mixed> $request       The request body.
	 *
	 * @return array{status:int,error:string}|null The refusal, or null when the copy may start.
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private function refusal(?object $objectService, array $request): ?array {
		if ($objectService === null) {
			return ['status' => 503, 'error' => 'OpenRegister is not available.'];
		}

		if ($this->creationPolicy->canCreate() === false) {
			return ['status' => 403, 'error' => 'Project creation is restricted to administrators.'];
		}

		if (trim((string)($request['title'] ?? '')) === '') {
			return ['status' => 400, 'error' => 'A copy needs a title.'];
		}

		return null;
	}//end refusal()

	/**
	 * Write the project and its parts; on any failure remove what was written.
	 *
	 * @param ProjectCopyRun       $run    The copy.
	 * @param array<string, mixed> $source The source project's data.
	 *
	 * @return array<string, mixed>|null The new project, or null when the copy failed and was removed.
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private function copyAll(ProjectCopyRun $run, array $source): ?array {
		try {
			$project = $this->copyProject(run: $run, source: $source);
			$this->copyChildren(run: $run);
			return $this->finish(run: $run, project: $project);
		} catch (Throwable $e) {
			$this->logger->error('Planninq: project copy failed, removing what it wrote', ['source' => $run->sourceId, 'exception' => $e->getMessage()]);
			$this->cleanUp(run: $run);
			return null;
		}
	}//end copyAll()

	/**
	 * The source as the caller may read it, or null.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $sourceId      The source project.
	 *
	 * @return array<string,mixed>|null
	 */
	private function readableSource(object $objectService, string $sourceId): ?array {
		try {
			$entity = $objectService->find(
				id: $sourceId,
				options: new ObjectFindOptions(register: self::REGISTER, schema: 'project'),
				_rbac: true,
				_multitenancy: true
			);
		} catch (Throwable $e) {
			return null;
		}

		if (is_object($entity) === false || is_callable([$entity, 'getObject']) === false) {
			return null;
		}

		return (array)$entity->getObject();
	}//end readableSource()

	/**
	 * Which parts to copy: the defaults, overridden by booleans the caller sent.
	 *
	 * @param array<string,mixed> $requested The requested parts.
	 *
	 * @return array<string,bool>
	 */
	private static function parts(array $requested): array {
		$parts = self::PARTS;
		foreach (array_keys($parts) as $part) {
			if (array_key_exists($part, $requested) === true) {
				$parts[$part] = filter_var($requested[$part], FILTER_VALIDATE_BOOLEAN);
			}
		}

		// A dependency joins two copied tasks, so there are none without tasks.
		if ($parts['tasks'] === false) {
			$parts['dependencies'] = false;
		}

		return $parts;
	}//end parts()

	/**
	 * Days to move every date by, or null to copy no dates.
	 *
	 * Without a new start date dates are kept; a source without a start date
	 * has nothing to measure from, so its dates are not copied (design, risks).
	 *
	 * @param string $sourceStart The source's start date.
	 * @param string $newStart    The requested start date.
	 *
	 * @return int|null
	 */
	private static function shiftDays(string $sourceStart, string $newStart): ?int {
		if ($newStart === '') {
			return 0;
		}

		$from = self::day(value: $sourceStart);
		$to   = self::day(value: $newStart);
		if ($from === null || $to === null) {
			return null;
		}

		return (int)$from->diff($to)->format('%r%a');
	}//end shiftDays()

	/**
	 * A `YYYY-MM-DD` as a day, or null.
	 *
	 * @param string $value The date.
	 *
	 * @return DateTimeImmutable|null
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	private static function day(string $value): ?DateTimeImmutable {
		$day = date_create_immutable_from_format('!Y-m-d', $value);
		if ($day === false || $day->format('Y-m-d') !== $value) {
			return null;
		}

		return $day;
	}//end day()

	/**
	 * Create the project, owned by the caller and marked as being copied.
	 * Its title, key and owner come from the run's identity.
	 *
	 * @param ProjectCopyRun      $run    The copy.
	 * @param array<string,mixed> $source The source project.
	 *
	 * @return array<string,mixed> The saved project, with its id.
	 */
	private function copyProject(ProjectCopyRun $run, array $source): array {
		$fields = self::PROJECT_FIELDS;
		if ($run->parts['people'] === true) {
			$fields = array_merge($fields, self::PROJECT_PEOPLE);
		}

		$body = array_intersect_key($source, array_flip($fields));
		$body = array_merge(
			$body,
			['title' => $run->identity['title'], 'status' => 'active', 'owner' => $run->identity['owner'], 'isTemplate' => false],
			$run->shifted(dates: self::datesOf(schema: 'project', data: $source))
		);
		if ($run->identity['key'] !== '') {
			$body['key'] = strtoupper($run->identity['key']);
		}

		$body['metadata'] = array_merge((array)($source['metadata'] ?? []), ['copiedFrom' => $run->sourceId, 'copyState' => 'copying']);

		$id = $this->save(run: $run, schema: 'project', data: $body);
		$run->projectId = $id;

		return ['id' => $id] + $body;
	}//end copyProject()

	/**
	 * Copy the columns, phases, tasks and dependencies the caller chose.
	 *
	 * @param ProjectCopyRun $run The copy.
	 *
	 * @return void
	 */
	private function copyChildren(ProjectCopyRun $run): void {
		foreach (['columns' => 'column', 'phases' => 'projectPhase'] as $part => $schema) {
			if ($run->parts[$part] === false) {
				continue;
			}

			$this->copyRows(run: $run, schema: $schema);
		}

		if ($run->parts['tasks'] === true) {
			$this->copyTasks(run: $run);
		}

		if ($run->parts['dependencies'] === true) {
			$this->copyDependencies(run: $run);
		}
	}//end copyChildren()

	/**
	 * Copy every column or phase of the source onto the new project, its dates shifted.
	 *
	 * @param ProjectCopyRun $run    The copy.
	 * @param string         $schema The schema slug (column or projectPhase).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private function copyRows(ProjectCopyRun $run, string $schema): void {
		foreach ($this->sourceRows(run: $run, schema: $schema) as $oldId => $row) {
			$data = array_diff_key($row, array_flip(['id', 'uuid', '@self', 'members']));
			$data = array_merge($data, ['project' => $run->projectId], $run->shifted(dates: self::datesOf(schema: $schema, data: $row)));
			$run->idMap[$oldId] = $this->save(run: $run, schema: $schema, data: $data);
		}
	}//end copyRows()

	/**
	 * Copy the tasks, a parent or epic before the tasks that point at it.
	 *
	 * @param ProjectCopyRun $run The copy.
	 *
	 * @return void
	 */
	private function copyTasks(ProjectCopyRun $run): void {
		$dropped = self::TASK_DROPPED;
		if ($run->parts['people'] === false) {
			$dropped = array_merge($dropped, self::TASK_PEOPLE);
		}

		foreach (self::parentsFirst(rows: $this->sourceRows(run: $run, schema: 'task')) as $oldId => $row) {
			$data = array_diff_key($row, array_flip(array_merge($dropped, self::TASK_REFERENCES)));
			$data = array_merge(
				$data,
				self::remapped(run: $run, row: $row),
				['project' => $run->projectId, 'status' => 'open'],
				$run->shifted(dates: self::datesOf(schema: 'task', data: $row))
			);
			$run->idMap[$oldId] = $this->save(run: $run, schema: 'task', data: $data);
		}
	}//end copyTasks()

	/**
	 * A task's column, phase, parent and epic, pointed at their copies; a
	 * reference whose target was not copied is left out.
	 *
	 * @param ProjectCopyRun      $run The copy.
	 * @param array<string,mixed> $row The source task.
	 *
	 * @return array<string,string>
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private static function remapped(ProjectCopyRun $run, array $row): array {
		$references = [];
		foreach (self::TASK_REFERENCES as $reference) {
			$target = (string)($row[$reference] ?? '');
			if ($target !== '' && isset($run->idMap[$target]) === true) {
				$references[$reference] = $run->idMap[$target];
			}
		}

		return $references;
	}//end remapped()

	/**
	 * Order tasks so a parent or epic comes before the tasks that refer to it.
	 *
	 * A reference outside the set, or a cycle, does not hold a task back: the
	 * remaining tasks follow in their source order.
	 *
	 * @param array<string,array<string,mixed>> $rows The tasks by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function parentsFirst(array $rows): array {
		$ordered = [];
		$waiting = $rows;
		while ($waiting !== []) {
			$progress = false;
			foreach ($waiting as $id => $row) {
				if (self::waitsOn(row: $row, id: (string)$id, waiting: $waiting) === false) {
					$ordered[$id] = $row;
					unset($waiting[$id]);
					$progress = true;
				}
			}

			if ($progress === false) {
				return $ordered + $waiting;
			}
		}

		return $ordered;
	}//end parentsFirst()

	/**
	 * Whether a task's parent or epic (other than itself) is still waiting to be ordered.
	 *
	 * @param array<string,mixed>                $row     The task.
	 * @param string                             $id      Its id.
	 * @param array<string,array<string,mixed>>  $waiting The tasks not yet ordered.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private static function waitsOn(array $row, string $id, array $waiting): bool {
		foreach (['parent', 'epic'] as $reference) {
			$target = (string)($row[$reference] ?? '');
			if ($target !== '' && $target !== $id && isset($waiting[$target]) === true) {
				return true;
			}
		}

		return false;
	}//end waitsOn()

	/**
	 * Copy each dependency whose two tasks were both copied.
	 *
	 * @param ProjectCopyRun $run The copy.
	 *
	 * @return void
	 */
	private function copyDependencies(ProjectCopyRun $run): void {
		foreach ($this->sourceRows(run: $run, schema: 'dependency') as $row) {
			$blocker = (string)($row['blocker'] ?? '');
			$blocked = (string)($row['blocked'] ?? '');
			if (isset($run->idMap[$blocker], $run->idMap[$blocked]) === false) {
				continue;
			}

			$data = array_diff_key($row, array_flip(['id', 'uuid', '@self', 'members']));
			$data = array_merge($data, ['project' => $run->projectId, 'blocker' => $run->idMap[$blocker], 'blocked' => $run->idMap[$blocked]]);
			$this->save(run: $run, schema: 'dependency', data: $data);
		}
	}//end copyDependencies()

	/**
	 * Mark the copy finished: `copyState` leaves the project's metadata.
	 *
	 * @param ProjectCopyRun      $run     The copy.
	 * @param array<string,mixed> $project The copied project, with its id.
	 *
	 * @return array<string,mixed> The project as stored, with its id.
	 */
	private function finish(ProjectCopyRun $run, array $project): array {
		$id = (string)$project['id'];
		// Re-read: creating tasks moves the project's task counter, and listeners
		// may have filled in more; only `copyState` is this step's to change.
		$stored = (new TimetableSessionRows())->dataOf(
			row: $run->objectService->find(
				id: $id,
				options: new ObjectFindOptions(register: self::REGISTER, schema: 'project'),
				_rbac: false,
				_multitenancy: false
			)
		);
		unset($stored['id'], $stored['@self'], $stored['metadata']['copyState']);
		$run->objectService->saveObject(
			object: $stored,
			options: new ObjectSaveOptions(target: new ObjectFindTarget(register: self::REGISTER, schema: 'project'), uuid: $id),
			_rbac: false
		);

		return ['id' => $id] + $stored;
	}//end finish()

	/**
	 * The source's rows of one schema, by id, read as the system: the caller already passed the read check on the project.
	 *
	 * @param ProjectCopyRun $run    The copy.
	 * @param string         $schema The schema slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function sourceRows(ProjectCopyRun $run, string $schema): array {
		$results = $run->objectService->searchObjectsBySlug(
			target: new RegisterSchemaSlugs(register: self::REGISTER, schema: $schema),
			filters: ['project' => $run->sourceId, '_limit' => self::MAX_ROWS],
			_rbac: false,
			_multitenancy: false
		);

		$rows  = [];
		$tools = new TimetableSessionRows();
		foreach ($tools->listOf(results: $results) as $row) {
			$data = $tools->dataOf(row: $row);
			$id   = $tools->idOf(row: $row);
			// Re-checked here, so a copy never depends on OpenRegister applying the filter.
			if ($id !== '' && (string)($data['project'] ?? '') === $run->sourceId) {
				$rows[$id] = $data;
			}
		}

		return $rows;
	}//end sourceRows()

	/**
	 * The valid date fields of a row, as days.
	 *
	 * @param string              $schema The schema slug.
	 * @param array<string,mixed> $data   The source row.
	 *
	 * @return array<string,DateTimeImmutable>
	 *
	 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	private static function datesOf(string $schema, array $data): array {
		$dates = [];
		foreach (self::DATE_FIELDS[$schema] ?? [] as $field) {
			$day = self::day(value: (string)($data[$field] ?? ''));
			if ($day !== null) {
				$dates[$field] = $day;
			}
		}

		return $dates;
	}//end datesOf()

	/**
	 * Save one new object and remember it for the clean-up.
	 *
	 * Children are written without a tenant filter, as the default columns
	 * are; the project takes the caller's organisation.
	 *
	 * @param ProjectCopyRun      $run    The copy.
	 * @param string              $schema The schema slug.
	 * @param array<string,mixed> $data   The object.
	 *
	 * @return string The new id.
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	private function save(ProjectCopyRun $run, string $schema, array $data): string {
		$entity = $run->objectService->saveObject(
			object: $data,
			options: new ObjectSaveOptions(target: new ObjectFindTarget(register: self::REGISTER, schema: $schema)),
			_rbac: false,
			_multitenancy: $schema === 'project'
		);
		$id     = (new TimetableSessionRows())->idOf(row: $entity);
		if ($id === '') {
			throw new RuntimeException('OpenRegister returned no id for a copied ' . $schema);
		}

		$run->created[] = [$schema, $id];

		return $id;
	}//end save()

	/**
	 * Delete everything the copy wrote, newest first.
	 *
	 * @param ProjectCopyRun $run The failed copy.
	 *
	 * @return void
	 */
	private function cleanUp(ProjectCopyRun $run): void {
		foreach (array_reverse($run->created) as [$schema, $id]) {
			try {
				$run->objectService->deleteObject(
					new ObjectDeletion(uuid: $id, target: new ObjectFindTarget(register: self::REGISTER, schema: $schema)),
					_rbac: false,
					_multitenancy: false
				);
			} catch (Throwable $e) {
				$this->logger->error('Planninq: could not remove a partly copied object', ['schema' => $schema, 'id' => $id, 'exception' => $e->getMessage()]);
			}
		}
	}//end cleanUp()
}//end class
