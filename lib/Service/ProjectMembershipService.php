<?php

/**
 * Planninq ProjectMembershipService
 *
 * Keeps a copy of each project's members on the objects that belong to it, so
 * OpenRegister can scope them with a rule it evaluates.
 *
 * WHY A COPY (planninq#681)
 * -------------------------
 * `task`, `column` and `projectPhase` limited every verb, and `plannedTimeEntry`
 * a read rule, to project members with
 * `{"project": {"$in": {"$lookup": {"schema": "project", ...}}}}`. OpenRegister
 * has no `$lookup` operator and no cross-schema operator of any kind. It stored
 * the rule and used the `$lookup` map as the literal operand of `$in`, so the
 * rule matched no row: members saw an empty board and a task create answered
 * 403 (verified live, see the issue and openregister#4089).
 *
 * OpenRegister does evaluate `{"members": {"$contains": "$userId"}}` on an array
 * property, on the list path and the single-object path alike; the `project`
 * schema already relies on it. So each of the four schemas now carries its own
 * `members` list. This service computes it (the project's `members` plus its
 * `owner`, who can always reach the project through OpenRegister's owner rule),
 * and writes it to every object of a project when that project's membership
 * changes, and to every existing object on upgrade.
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
 * @spec openspec/specs/projects.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Computes and writes the denormalised `members` list of project-scoped objects.
 *
 * @spec openspec/specs/projects.md
 */
class ProjectMembershipService {

	/**
	 * OpenRegister register slug of the planninq schemas.
	 *
	 * @var string
	 */
	public const REGISTER = 'planninq';

	/**
	 * Schema slug of the project, whose membership is copied.
	 *
	 * @var string
	 */
	public const PROJECT_SCHEMA = 'project';

	/**
	 * The field holding the managers of a project's portfolio, copied like `members`.
	 *
	 * @var string
	 */
	public const READERS_FIELD = 'portfolioReaders';

	/**
	 * Schemas that carry a copy of their project's members.
	 *
	 * @var array<int,string>
	 */
	public const SCOPED_SCHEMAS = [
		'task',
		'column',
		'projectPhase',
		'plannedTimeEntry',
		'projectLogEntry',
		'risk',
		'projectStatusReport',
		'projectRelease',
	];

	/**
	 * The one scoped schema that may name its project only through its task.
	 *
	 * @var string
	 */
	private const TIME_ENTRY_SCHEMA = 'plannedTimeEntry';

	/**
	 * Largest `IN` list sent in one search, below the 1000-item cap some databases enforce.
	 *
	 * @var integer
	 */
	private const IN_CHUNK = 500;


	/**
	 * Members per project id for this request; null for a project that did not resolve.
	 *
	 * @var array<string, array<int,string>|null>
	 */
	private array $membersCache = [];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService at runtime.
	 * @param IAppManager $appManager Tells whether OpenRegister is installed.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether OpenRegister is installed, so there is anything to read or write.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function isAvailable(): bool {
		return $this->appManager->isInstalled('openregister');
	}//end isAvailable()

	/**
	 * The members list a project hands down: its members plus its owner, sorted and distinct.
	 *
	 * @param array<string,mixed> $project The project data.
	 *
	 * @return array<int,string> The user ids.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function membersFromProject(array $project): array {
		$members = ($project['members'] ?? []);
		if (is_array($members) === false) {
			$members = [];
		}

		$members[] = ($project['owner'] ?? '');

		return $this->normalise(members: $members);
	}//end membersFromProject()

	/**
	 * A stored `members` value as a sorted, distinct list, for comparison.
	 *
	 * @param mixed $members The stored value.
	 *
	 * @return array<int,string> The user ids.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function normalise(mixed $members): array {
		if (is_array($members) === false) {
			return [];
		}

		$ids = [];
		foreach ($members as $member) {
			if (is_scalar($member) === true && (string)$member !== '') {
				$ids[(string)$member] = true;
			}
		}

		$ids = array_map('strval', array_keys($ids));
		sort($ids);

		return $ids;
	}//end normalise()

	/**
	 * The project an object of a scoped schema belongs to.
	 *
	 * A planned time entry may name only its task; its project is then the task's.
	 *
	 * @param string $schemaSlug The object's schema slug.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return string The project uuid, or '' when none resolves.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function projectIdFor(string $schemaSlug, array $data): string {
		$projectId = $this->referenceId(value: ($data['project'] ?? null));
		if ($projectId !== '' || $schemaSlug !== self::TIME_ENTRY_SCHEMA) {
			return $projectId;
		}

		$taskId = $this->referenceId(value: ($data['task'] ?? null));
		if ($taskId === '') {
			return '';
		}

		$task = $this->findData(schema: 'task', id: $taskId);

		return $this->referenceId(value: ($task['project'] ?? null));
	}//end projectIdFor()

	/**
	 * The members list of a project, cached for the request.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return array<int,string>|null The user ids, or null when the project does not resolve.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function membersOfProject(string $projectId): ?array {
		if ($projectId === '') {
			return null;
		}

		if (array_key_exists($projectId, $this->membersCache) === false) {
			$project = $this->findData(schema: self::PROJECT_SCHEMA, id: $projectId);
			$members = null;
			if ($project !== null) {
				$members = $this->membersFromProject(project: $project);
			}

			$this->membersCache[$projectId] = $members;
		}

		return $this->membersCache[$projectId];
	}//end membersOfProject()

	/**
	 * The owner of a project, or null when the project does not resolve.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.1
	 */
	public function ownerOfProject(string $projectId): ?string {
		if ($projectId === '') {
			return null;
		}

		$owner = ($this->findData(schema: self::PROJECT_SCHEMA, id: $projectId)['owner'] ?? null);
		if (is_string($owner) === false || $owner === '') {
			return null;
		}

		return $owner;
	}//end ownerOfProject()

	/**
	 * One planninq object's stored data, read as the system, or null when it does not resolve.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function objectData(string $schema, string $id): ?array {
		if ($id === '') {
			return null;
		}

		return $this->findData(schema: $schema, id: $id);
	}//end objectData()

	/**
	 * Every planninq object of one schema matching the filters, read with RBAC off.
	 *
	 * @param string              $schema  The schema slug.
	 * @param array<string,mixed> $filters OpenRegister search filters.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function rows(string $schema, array $filters): array {
		return $this->search(objectService: $this->objectService(), schema: $schema, filters: $filters);
	}//end rows()

	/**
	 * Write a project's members list to every object of that project that is out of step.
	 *
	 * Writes run as the system (`_rbac: false`, all organisations), silent (no
	 * audit row, event or notification per object) and without validation: the
	 * acting user changed the project, and only `members` changes on the object.
	 * One object that refuses the write is logged and does not stop the rest.
	 *
	 * The same writes keep `portfolioReaders` in step: pass that field and the
	 * managers of the project's portfolio.
	 *
	 * @param string            $projectId The project uuid.
	 * @param array<int,string> $members   The list, for `members` from membersFromProject().
	 * @param string            $field     `members`, or READERS_FIELD for the portfolio readers.
	 *
	 * @return int The number of objects written.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function syncProjectMembers(string $projectId, array $members, string $field = 'members'): int {
		$members = $this->normalise(members: $members);
		if ($field === 'members') {
			$this->membersCache[$projectId] = $members;
		}

		return $this->syncField(projectId: $projectId, field: $field, values: $members);
	}//end syncProjectMembers()

	/**
	 * Write one access list to every object of a project that is out of step.
	 *
	 * @param string            $projectId The project uuid.
	 * @param string            $field     `members` or `portfolioReaders`.
	 * @param array<int,string> $values    The normalised list.
	 *
	 * @return int The number of objects written.
	 */
	private function syncField(string $projectId, string $field, array $values): int {
		if ($projectId === '') {
			return 0;
		}

		$objectService = $this->objectService();
		$written = 0;
		foreach ($this->childrenOf(objectService: $objectService, projectId: $projectId) as $child) {
			if ($this->normalise(members: ($child['data'][$field] ?? null)) === $values) {
				continue;
			}

			$written += $this->writeField(objectService: $objectService, child: $child, field: $field, values: $values);
		}

		return $written;
	}//end syncField()

	/**
	 * Bring every project's objects in step: the upgrade back-fill.
	 *
	 * An object whose project no longer exists is left as it is, so it stays
	 * visible to admins only.
	 *
	 * @return array{projects: int, written: int} Projects visited and objects written.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function syncAll(): array {
		$objectService = $this->objectService();
		$projects = 0;
		$written = 0;
		foreach ($this->search(objectService: $objectService, schema: self::PROJECT_SCHEMA, filters: []) as $project) {
			$projects++;
			$written += $this->syncProjectMembers(
				projectId: $project['id'],
				members: $this->membersFromProject(project: $project['data'])
			);
			$written += $this->syncProjectMembers(
				projectId: $project['id'],
				members: $this->normalise(members: ($project['data'][self::READERS_FIELD] ?? [])),
				field: self::READERS_FIELD
			);
		}

		return ['projects' => $projects, 'written' => $written];
	}//end syncAll()

	/**
	 * Every object of a project across the scoped schemas, each once.
	 *
	 * Planned time entries are found by their own `project` and, for those that
	 * name only a task, by the project's task ids.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $projectId The project uuid.
	 *
	 * @return array<string, array{schema: string, id: string, data: array<string,mixed>}> Keyed `schema/id`.
	 */
	private function childrenOf(object $objectService, string $projectId): array {
		$children = [];
		$taskIds = [];
		foreach (self::SCOPED_SCHEMAS as $schema) {
			foreach ($this->search(objectService: $objectService, schema: $schema, filters: ['project' => $projectId]) as $row) {
				$children[$schema . '/' . $row['id']] = ['schema' => $schema] + $row;
				if ($schema === 'task') {
					$taskIds[] = $row['id'];
				}
			}
		}

		foreach (array_chunk($taskIds, self::IN_CHUNK) as $chunk) {
			$entries = $this->search(objectService: $objectService, schema: self::TIME_ENTRY_SCHEMA, filters: ['task' => $chunk]);
			foreach ($entries as $row) {
				$children[self::TIME_ENTRY_SCHEMA . '/' . $row['id']] = ['schema' => self::TIME_ENTRY_SCHEMA] + $row;
			}
		}

		return $children;
	}//end childrenOf()

	/**
	 * Write one access list onto one object.
	 *
	 * @param object                                                       $objectService OpenRegister's ObjectService.
	 * @param array{schema: string, id: string, data: array<string,mixed>} $child         The object.
	 * @param string                                                       $field         `members` or `portfolioReaders`.
	 * @param array<int,string>                                            $values        The list.
	 *
	 * @return int 1 when written, 0 when the write was refused.
	 */
	private function writeField(object $objectService, array $child, string $field, array $values): int {
		$data = $child['data'];
		$data[$field] = $values;

		try {
			$objectService->saveObject(
				object: $data,
				register: self::REGISTER,
				schema: $child['schema'],
				uuid: $child['id'],
				_rbac: false,
				_multitenancy: false,
				silent: true,
				_validation: false
			);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Planninq: could not write an access list of a project object; people may not see it',
				['schema' => $child['schema'], 'object' => $child['id'], 'field' => $field, 'exception' => $e->getMessage()]
			);
			return 0;
		}

		return 1;
	}//end writeField()

	/**
	 * Search one planninq schema as the system.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $schema The schema slug.
	 * @param array<string,mixed> $filters Filters: a scalar is equality, a list is IN.
	 *
	 * @return array<int, array{id: string, data: array<string,mixed>}> The rows found.
	 */
	private function search(object $objectService, string $schema, array $filters): array {
		// `searchObjectsBySlug()`, not setters plus `searchObjects()`: the latter
		// ignores the register and schema the setters leave behind (see
		// DependencyRepository::fetchProjectTaskIds()).
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: $schema,
			filters: $filters,
			_rbac: false,
			_multitenancy: false
		);

		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$rows = [];
		foreach ((array)$results as $row) {
			$id = $this->rowId(row: $row);
			if ($id !== '') {
				$rows[] = ['id' => $id, 'data' => $this->rowData(row: $row)];
			}
		}

		return $rows;
	}//end search()

	/**
	 * Read one planninq object as the system.
	 *
	 * The membership check must not depend on what the acting user may read,
	 * and an upgrade has no user at all.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id The uuid.
	 *
	 * @return array<string,mixed>|null The object data, or null when it does not resolve.
	 */
	private function findData(string $schema, string $id): ?array {
		try {
			$entity = $this->objectService()->find(
				id: $id,
				register: self::REGISTER,
				schema: $schema,
				_rbac: false,
				_multitenancy: false,
				_render: false,
				_audit: false
			);
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Planninq: could not read a planninq object for membership',
				['schema' => $schema, 'object' => $id, 'exception' => $e->getMessage()]
			);
			return null;
		}

		if ($entity === null) {
			return null;
		}

		return $this->rowData(row: $entity);
	}//end findData()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object The service.
	 *
	 * @throws RuntimeException When OpenRegister is not installed.
	 */
	private function objectService(): object {
		if ($this->isAvailable() === false) {
			throw new RuntimeException('OpenRegister is not installed.');
		}

		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()

	/**
	 * The uuid of a search row, entity or array.
	 *
	 * @param mixed $row The row.
	 *
	 * @return string The uuid, or ''.
	 */
	private function rowId(mixed $row): string {
		// `is_callable()`, not `method_exists()`: an OpenRegister entity serves
		// getUuid() through `__call()` (see LabelService::extractId()).
		if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
			return (string)($row->getUuid() ?? '');
		}

		if (is_array($row) === true) {
			return (string)($row['@self']['id'] ?? ($row['id'] ?? ''));
		}

		return '';
	}//end rowId()

	/**
	 * The data of a search row, entity or array.
	 *
	 * @param mixed $row The row.
	 *
	 * @return array<string,mixed> The object data.
	 */
	private function rowData(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			unset($row['@self']);
			return $row;
		}

		return [];
	}//end rowData()

	/**
	 * A reference value as a uuid string.
	 *
	 * A relation arrives as a uuid string, or resolved as an object carrying it.
	 *
	 * @param mixed $value The reference.
	 *
	 * @return string The uuid, or ''.
	 */
	private function referenceId(mixed $value): string {
		if (is_string($value) === true) {
			return trim($value);
		}

		if (is_array($value) === true) {
			$candidate = ($value['id'] ?? ($value['uuid'] ?? ($value['value'] ?? ($value['@self']['id'] ?? ''))));
			if (is_string($candidate) === true) {
				return trim($candidate);
			}
		}

		return '';
	}//end referenceId()
}//end class
