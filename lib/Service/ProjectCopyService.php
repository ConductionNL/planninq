<?php

/**
 * Planninq ProjectCopyService
 *
 * Copies a project, or starts one from a template, with the parts the caller
 * keeps: columns, phases, tasks, dependencies and people. Every reference
 * between the copied objects is remapped to the new ids, dates shift to the
 * new start date, and a failure removes whatever the copy had written.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectCopyException;
use Psr\Log\LoggerInterface;

/**
 * Copies a project with its structure.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */
class ProjectCopyService {

	/**
	 * The planninq register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * Fields never copied from a project: identity, membership and counters.
	 *
	 * @var array<int,string>
	 */
	private const PROJECT_STRIP = [
		'id', 'uuid', '@self', 'key', 'nextTaskNumber', 'members', 'managers', 'viewers',
		'managerGroups', 'memberGroups', 'viewerGroups', 'ownerGroups', 'portfolioReaders',
		'caseHandovers', 'healthDate', 'reviewedBy', 'reviewedAt', 'reviewNote', 'requestReason',
	];

	/**
	 * Fields never copied from a task: identity, people, progress and links to the outside.
	 *
	 * @var array<int,string>
	 */
	private const TASK_STRIP = [
		'id', 'uuid', '@self', 'key', 'members', 'portfolioReaders', 'viewers', 'memberGroups',
		'viewerGroups', 'reporter', 'assignedTo', 'sharedWith', 'watchers', 'completedAt',
		'resolvedAt', 'resolution', 'percentComplete', 'calendarEventUid', 'zaakUuid', 'checklist',
	];

	/**
	 * Fields never copied from a column or phase.
	 *
	 * @var array<int,string>
	 */
	private const CHILD_STRIP = [
		'id', 'uuid', '@self', 'members', 'portfolioReaders', 'viewers', 'memberGroups', 'viewerGroups',
	];

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads the project and its children.
	 * @param DependencyRepository     $repository Resolves OpenRegister's ObjectService.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private DependencyRepository $repository,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Copy a project.
	 *
	 * @param string              $sourceId The project to copy.
	 * @param string              $userId   The person who becomes the new project's owner.
	 * @param array<string,mixed> $options  `title`, `key` (checked), `startDate` (Y-m-d) and `parts` (columns, phases, tasks, dependencies, people).
	 *
	 * @return array{id:string,counts:array<string,int>} The new project's id and what was copied.
	 *
	 * @throws ProjectCopyException When the source is missing or a step fails; nothing is left behind.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
	 */
	public function copy(string $sourceId, string $userId, array $options): array {
		$source = $this->membership->objectData(schema: 'project', id: $sourceId);
		if ($source === null) {
			throw new ProjectCopyException(step: 'project', message: 'Project not found.');
		}

		$parts   = (array)($options['parts'] ?? []);
		$keep    = static fn (string $part): bool => ($parts[$part] ?? false) === true;
		$none    = [];
		$wantColumns = $keep('columns');
		$wantPhases  = $keep('phases');
		$wantTasks   = $keep('tasks');
		$wantLinks   = $keep('dependencies');
		$shift   = $this->dayShift(from: ($source['startDate'] ?? null), to: ($options['startDate'] ?? null));
		$service = $this->repository->objectService();
		$created = [];
		$counts  = ['columns' => 0, 'phases' => 0, 'tasks' => 0, 'dependencies' => 0];
		$step    = 'project';

		try {
			$project = $this->projectPayload(source: $source, userId: $userId, options: $options, shift: $shift, people: $keep('people'));
			$newId   = $this->save(service: $service, schema: 'project', data: $project, created: $created);

			$step      = 'columns';
			$columnMap = $none;
			if ($wantColumns === true) {
				$columnMap = $this->copyChildren(service: $service, schema: 'column', sourceId: $sourceId, newId: $newId, created: $created);
			}

			$counts['columns'] = count($columnMap);

			$step     = 'phases';
			$phaseMap = $none;
			if ($wantPhases === true) {
				$phaseMap = $this->copyChildren(service: $service, schema: 'projectPhase', sourceId: $sourceId, newId: $newId, created: $created, shift: $shift);
			}

			$counts['phases'] = count($phaseMap);

			$step    = 'tasks';
			$taskMap = $none;
			if ($wantTasks === true) {
				$maps    = ['column' => $columnMap, 'phase' => $phaseMap];
				$taskMap = $this->copyTasks(service: $service, sourceId: $sourceId, newId: $newId, maps: $maps, shift: $shift, created: $created);
			}

			$counts['tasks'] = count($taskMap);

			$step = 'dependencies';
			if ($wantLinks === true && $taskMap !== []) {
				$counts['dependencies'] = $this->copyDependencies(service: $service, taskMap: $taskMap, created: $created);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: project copy failed', ['step' => $step, 'exception' => $e->getMessage()]);
			$this->rollBack(service: $service, created: $created);
			throw new ProjectCopyException(step: $step, message: 'The copy failed while writing the ' . $step . '.', previous: $e);
		}

		return ['id' => $newId, 'counts' => $counts];
	}//end copy()

	/**
	 * The new project's data.
	 *
	 * @param array<string,mixed> $source  The source project.
	 * @param string              $userId  The new owner.
	 * @param array<string,mixed> $options The caller's options.
	 * @param int                 $shift   Days to shift dates by.
	 * @param bool                $people  Whether the people come along.
	 *
	 * @return array<string,mixed>
	 */
	private function projectPayload(array $source, string $userId, array $options, int $shift, bool $people): array {
		$data = array_diff_key($source, array_flip(self::PROJECT_STRIP));
		$data['title']      = (string)($options['title'] ?? ($source['title'] ?? ''));
		$data['isTemplate'] = false;
		$data['status']     = 'active';
		$data['owner']      = $userId;
		$data['members']    = [$userId];
		if (is_string($options['key'] ?? null) === true && $options['key'] !== '') {
			$data['key'] = $options['key'];
		}
		if ($people === true) {
			foreach (['managers', 'viewers', 'managerGroups', 'memberGroups', 'viewerGroups'] as $list) {
				if (isset($source[$list]) === true) {
					$data[$list] = $source[$list];
				}
			}

			$data['members'] = array_values(array_unique(array_merge([$userId], (array)($source['members'] ?? []))));
		}

		foreach (['startDate', 'endDate'] as $field) {
			if (isset($data[$field]) === true) {
				$data[$field] = $this->shifted(date: (string)$data[$field], days: $shift);
			}
		}

		return $data;
	}//end projectPayload()

	/**
	 * Copy the columns or phases of a project; returns old id => new id.
	 *
	 * @param object              $service  OpenRegister's ObjectService.
	 * @param string              $schema   `column` or `projectPhase`.
	 * @param string              $sourceId The source project.
	 * @param string              $newId    The new project.
	 * @param array<int,mixed>    $created  Written so far; appended to.
	 * @param int                 $shift    Days to shift dates by.
	 *
	 * @return array<string,string>
	 */
	private function copyChildren(object $service, string $schema, string $sourceId, string $newId, array &$created, int $shift = 0): array {
		$map = [];
		foreach ($this->membership->rows(schema: $schema, filters: ['project' => $sourceId]) as $row) {
			$data = array_diff_key($row['data'], array_flip(self::CHILD_STRIP));
			$data['project'] = $newId;
			foreach (['startDate', 'endDate', 'dueDate'] as $field) {
				if (isset($data[$field]) === true && is_string($data[$field]) === true) {
					$data[$field] = $this->shifted(date: $data[$field], days: $shift);
				}
			}

			$map[$row['id']] = $this->save(service: $service, schema: $schema, data: $data, created: $created);
		}

		return $map;
	}//end copyChildren()

	/**
	 * Copy the tasks, parents first; returns old id => new id.
	 *
	 * @param object                                $service  OpenRegister's ObjectService.
	 * @param string                                $sourceId The source project.
	 * @param string                                $newId    The new project.
	 * @param array<string,array<string,string>>    $maps     `column` and `phase` id maps.
	 * @param int                                   $shift    Days to shift dates by.
	 * @param array<int,mixed>                      $created  Written so far; appended to.
	 *
	 * @return array<string,string>
	 */
	private function copyTasks(object $service, string $sourceId, string $newId, array $maps, int $shift, array &$created): array {
		$rows = $this->membership->rows(schema: 'task', filters: ['project' => $sourceId]);
		$depth = static fn (array $row): int => (int)(($row['data']['parent'] ?? '') !== '');
		usort($rows, static fn (array $a, array $b): int => $depth($a) <=> $depth($b));

		$map = [];
		foreach ($rows as $row) {
			$data = array_diff_key($row['data'], array_flip(self::TASK_STRIP));
			$data['project'] = $newId;
			$data['status']  = 'open';
			foreach (['column' => 'column', 'phase' => 'phase', 'parent' => null] as $field => $mapName) {
				$old = (string)($data[$field] ?? '');
				if ($old === '') {
					continue;
				}

				$lookup = $map;
				if ($mapName !== null) {
					$lookup = $maps[$mapName];
				}

				if (isset($lookup[$old]) === true) {
					$data[$field] = $lookup[$old];
				} else {
					unset($data[$field]);
				}
			}

			foreach (['startDate', 'dueDate'] as $field) {
				if (isset($data[$field]) === true && is_string($data[$field]) === true) {
					$data[$field] = $this->shifted(date: $data[$field], days: $shift);
				}
			}

			$map[$row['id']] = $this->save(service: $service, schema: 'task', data: $data, created: $created);
		}

		return $map;
	}//end copyTasks()

	/**
	 * Copy the dependencies between copied tasks; returns how many were written.
	 *
	 * @param object                $service OpenRegister's ObjectService.
	 * @param array<string,string>  $taskMap Old task id => new task id.
	 * @param array<int,mixed>      $created Written so far; appended to.
	 *
	 * @return int
	 */
	private function copyDependencies(object $service, array $taskMap, array &$created): int {
		$count = 0;
		foreach ($this->membership->rows(schema: 'dependency', filters: []) as $row) {
			$blocker = (string)($row['data']['blocker'] ?? '');
			$blocked = (string)($row['data']['blocked'] ?? '');
			if (isset($taskMap[$blocker]) === false || isset($taskMap[$blocked]) === false) {
				continue;
			}

			$data = ['blocker' => $taskMap[$blocker], 'blocked' => $taskMap[$blocked], 'type' => ($row['data']['type'] ?? 'blocks')];
			$this->save(service: $service, schema: 'dependency', data: $data, created: $created);
			$count++;
		}

		return $count;
	}//end copyDependencies()

	/**
	 * Write one object and remember it for roll-back.
	 *
	 * @param object              $service OpenRegister's ObjectService.
	 * @param string              $schema  The schema slug.
	 * @param array<string,mixed> $data    The payload.
	 * @param array<int,mixed>    $created Written so far; appended to.
	 *
	 * @return string The new object's UUID.
	 */
	private function save(object $service, string $schema, array $data, array &$created): string {
		$saved = $service->saveObject(object: $data, register: self::REGISTER, schema: $schema);
		$id    = $this->idOf(saved: $saved);
		if ($id === '') {
			throw new \RuntimeException('OpenRegister returned no id for a ' . $schema);
		}

		$created[] = [$schema, $id];

		return $id;
	}//end save()

	/**
	 * Remove everything the copy wrote, newest first.
	 *
	 * @param object           $service OpenRegister's ObjectService.
	 * @param array<int,mixed> $created `[schema, id]` pairs.
	 *
	 * @return void
	 */
	private function rollBack(object $service, array $created): void {
		foreach (array_reverse($created) as [$schema, $id]) {
			try {
				$service->deleteObject(uuid: $id, register: self::REGISTER, schema: $schema, _rbac: false);
			} catch (\Throwable $e) {
				$this->logger->error('Planninq: could not remove part of a failed copy', ['schema' => $schema, 'id' => $id, 'exception' => $e->getMessage()]);
			}
		}
	}//end rollBack()

	/**
	 * The id of a saved object, whatever shape OpenRegister returned.
	 *
	 * @param mixed $saved The save result.
	 *
	 * @return string
	 */
	private function idOf(mixed $saved): string {
		if (is_object($saved) === true && method_exists($saved, 'getUuid') === true) {
			return (string)$saved->getUuid();
		}

		if (is_array($saved) === true) {
			return (string)($saved['id'] ?? $saved['uuid'] ?? ($saved['@self']['id'] ?? ''));
		}

		return '';
	}//end idOf()

	/**
	 * Whole days between two dates, 0 when either is missing.
	 *
	 * @param mixed $from The template's start date.
	 * @param mixed $to   The new start date.
	 *
	 * @return int
	 */
	private function dayShift(mixed $from, mixed $to): int {
		if (is_string($from) === false || is_string($to) === false || $from === '' || $to === '') {
			return 0;
		}

		$a = date_create_immutable($from);
		$b = date_create_immutable($to);
		if ($a === false || $b === false) {
			return 0;
		}

		return (int)$a->diff($b)->format('%r%a');
	}//end dayShift()

	/**
	 * A date moved by whole days, keeping its Y-m-d shape.
	 *
	 * @param string $date The date.
	 * @param int    $days Days to add (may be negative).
	 *
	 * @return string
	 */
	private function shifted(string $date, int $days): string {
		if ($days === 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
			return $date;
		}

		$parsed = date_create_immutable($date);

		if ($parsed === false) {
			return $date;
		}

		$sign = '+';
		if ($days < 0) {
			$sign = '';
		}

		return $parsed->modify($sign . $days . ' days')->format('Y-m-d');
	}//end shifted()
}//end class
