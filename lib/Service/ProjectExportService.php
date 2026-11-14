<?php

/**
 * Planninq ProjectExportService
 *
 * Gathers everything one project holds, read as the requester: the project,
 * the objects of every project-scoped schema, the files on its tasks and
 * phases and the comments on its tasks. Each schema is first put to
 * OpenRegister's export gate with the profile `planninq-project-export`; a
 * refusal of the project or task schema stops the export, a refusal of any
 * other schema leaves that schema out and names it in the manifest.
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
 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectExportException;
use OCP\App\IAppManager;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\File;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads one project's objects, files and comments for its export.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) It reads OpenRegister objects, files, schemas and Nextcloud comments.
 */
class ProjectExportService {

	/**
	 * The export profile name put to OpenRegister's export gate and audit trail.
	 */
	public const PROFILE = 'planninq-project-export';

	/**
	 * The register holding planninq's schemas.
	 */
	public const REGISTER = 'planninq';

	/**
	 * Schemas whose objects carry a `project` relation.
	 *
	 * @var array<int,string>
	 */
	public const PROJECT_SCHEMAS = [
		'task',
		'projectPhase',
		'column',
		'plannedTimeEntry',
		'projectLogEntry',
		'risk',
		'projectStatusReport',
		'financeLine',
		'projectRelease',
		'boardFilter',
		'forgeLink',
	];

	/**
	 * Schemas whose objects name their projects in a `projects` list.
	 *
	 * @var array<int,string>
	 */
	public const LIST_SCHEMAS = ['boardView', 'taskReport'];

	/**
	 * The dependency schema: rows count when either task is in the project.
	 */
	public const DEPENDENCY_SCHEMA = 'dependency';

	/**
	 * Schemas whose refusal stops the whole export.
	 *
	 * @var array<int,string>
	 */
	public const REQUIRED_SCHEMAS = ['project', 'task'];

	/**
	 * The page size used when reading a schema.
	 */
	public const PAGE_SIZE = 500;

	/**
	 * The columns of `tasks.csv`.
	 *
	 * @var array<int,string>
	 */
	public const CSV_HEADER = ['key', 'title', 'status', 'column', 'assignee', 'start', 'due', 'labels'];

	/**
	 * Schema entities by slug, resolved once per export.
	 *
	 * @var array<string,object>|null
	 */
	private ?array $schemas = null;

	/**
	 * The gate's answer per schema slug in this export.
	 *
	 * @var array<string,bool>
	 */
	private array $answers = [];

	/**
	 * The planninq register's id, once resolved.
	 *
	 * @var integer|null
	 */
	private ?int $registerId = null;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface $container   Resolves OpenRegister's services by name.
	 * @param IAppManager        $appManager  Tells whether OpenRegister is installed.
	 * @param ICommentsManager   $comments    Nextcloud's comments.
	 * @param IUserManager       $userManager Resolves display names.
	 * @param LoggerInterface    $logger      The logger.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IAppManager $appManager,
		private readonly ICommentsManager $comments,
		private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Everything the export of one project holds, read as the current user.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return array{
	 *     project: array<string,mixed>,
	 *     title: string,
	 *     key: string,
	 *     registerId: int|null,
	 *     schemas: array<string,list<array<string,mixed>>>,
	 *     csv: list<list<string>>,
	 *     comments: array<string,list<array<string,mixed>>>,
	 *     files: list<array{path:string,file:File}>,
	 *     skipped: list<array{item:string,reason:string}>
	 * }
	 *
	 * @throws ProjectExportException When OpenRegister is missing, the project does not resolve, or the project or task schema is refused.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.1
	 */
	public function collect(string $projectId): array {
		if ($this->appManager->isInstalled('openregister') === false) {
			throw new ProjectExportException(reason: ProjectExportException::UNAVAILABLE, message: 'OpenRegister is not installed.');
		}

		$this->schemas    = null;
		$this->registerId = null;
		$this->answers    = [];
		foreach (self::REQUIRED_SCHEMAS as $required) {
			$this->assertAllowed(schema: $required);
		}

		$project = $this->readProject(projectId: $projectId);
		$skipped = [];
		$schemas = [];
		$rows    = [];
		foreach (array_merge(self::PROJECT_SCHEMAS, self::LIST_SCHEMAS) as $schema) {
			if ($this->allowed(schema: $schema) === false) {
				$skipped[] = ['item' => $schema . '.json', 'reason' => 'no export permission'];
				continue;
			}

			$rows[$schema]    = $this->projectRows(schema: $schema, projectId: $projectId);
			$schemas[$schema] = $this->storedValues(rows: $rows[$schema]);
		}

		$taskIds = array_keys($rows['task']);
		if ($this->allowed(schema: self::DEPENDENCY_SCHEMA) === false) {
			$skipped[] = ['item' => self::DEPENDENCY_SCHEMA . '.json', 'reason' => 'no export permission'];
		} else {
			$schemas[self::DEPENDENCY_SCHEMA] = $this->storedValues(rows: $this->dependencyRows(taskIds: $taskIds));
		}

		$files = $this->files(rows: $rows, skipped: $skipped);

		return [
			'project'    => $project['stored'],
			'title'      => (string)($project['data']['title'] ?? ''),
			'key'        => (string)($project['data']['key'] ?? ''),
			'registerId' => $this->registerId,
			'schemas'    => $schemas,
			'csv'        => $this->tasksCsv(tasks: $rows['task'], columns: ($rows['column'] ?? [])),
			'comments'   => $this->taskComments(taskIds: $taskIds),
			'files'      => $files,
			'skipped'    => $skipped,
		];
	}//end collect()

	/**
	 * Throw when the gate refuses a schema the export cannot do without.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return void
	 *
	 * @throws ProjectExportException When refused.
	 */
	private function assertAllowed(string $schema): void {
		if ($this->allowed(schema: $schema) === false) {
			throw new ProjectExportException(
				reason: ProjectExportException::REFUSED,
				message: 'The export permission is missing on the ' . $schema . ' schema.'
			);
		}
	}//end assertAllowed()

	/**
	 * Whether OpenRegister's export gate lets the current user export a schema.
	 *
	 * The gate records its own refusal on the audit trail, so a refusal is
	 * not recorded here again; each schema is put to it once per export.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return bool
	 */
	private function allowed(string $schema): bool {
		if (array_key_exists($schema, $this->answers) === false) {
			$this->answers[$schema] = $this->container->get('OCA\\OpenRegister\\Service\\Export\\ExportGate')->refusalFor(
				schema: ($this->schemaEntities()[$schema] ?? null),
				profile: self::PROFILE,
				registerId: $this->registerId
			) === null;
		}

		return $this->answers[$schema];
	}//end allowed()

	/**
	 * The planninq schemas by slug, read from the register once.
	 *
	 * @return array<string,object>
	 */
	private function schemaEntities(): array {
		if ($this->schemas !== null) {
			return $this->schemas;
		}

		$this->schemas = [];
		try {
			$register         = $this->container->get('OCA\\OpenRegister\\Db\\RegisterMapper')->find(self::REGISTER);
			$this->registerId = (int)$register->getId();
			$mapper           = $this->container->get('OCA\\OpenRegister\\Db\\SchemaMapper');
			foreach ((array)($register->getSchemas() ?? []) as $schemaId) {
				$schema = $mapper->find((int)$schemaId);
				$this->schemas[(string)$schema->getSlug()] = $schema;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: could not read the planninq schemas for an export', ['exception' => $e->getMessage()]);
		}

		return $this->schemas;
	}//end schemaEntities()

	/**
	 * The project, read as the current user.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return array{data:array<string,mixed>,stored:array<string,mixed>}
	 *
	 * @throws ProjectExportException When the project does not resolve.
	 */
	private function readProject(string $projectId): array {
		try {
			$entity = $this->objectService()->find(id: $projectId, register: self::REGISTER, schema: 'project');
		} catch (\Throwable $e) {
			$entity = null;
		}

		if (is_object($entity) === false) {
			throw new ProjectExportException(reason: ProjectExportException::NOT_FOUND, message: 'Project not found: ' . $projectId);
		}

		return ['data' => (array)$entity->getObject(), 'stored' => $this->stored(entity: $entity)];
	}//end readProject()

	/**
	 * The objects of one schema that belong to the project, by uuid.
	 *
	 * @param string $schema    The schema slug.
	 * @param string $projectId The project uuid.
	 *
	 * @return array<string,object>
	 */
	private function projectRows(string $schema, string $projectId): array {
		if (in_array($schema, self::LIST_SCHEMAS, true) === false) {
			return $this->search(schema: $schema, filters: ['project' => $projectId]);
		}

		return array_filter(
			$this->search(schema: $schema, filters: []),
			static fn (object $entity): bool => in_array($projectId, (array)($entity->getObject()['projects'] ?? []), true)
		);
	}//end projectRows()

	/**
	 * The dependencies whose blocker or blocked task is one of the project's tasks.
	 *
	 * @param array<int,string> $taskIds The project's task uuids.
	 *
	 * @return array<string,object>
	 */
	private function dependencyRows(array $taskIds): array {
		if ($taskIds === []) {
			return [];
		}

		return array_filter(
			$this->search(schema: self::DEPENDENCY_SCHEMA, filters: []),
			static function (object $entity) use ($taskIds): bool {
				$data = (array)$entity->getObject();
				return in_array((string)($data['blocker'] ?? ''), $taskIds, true) === true
					|| in_array((string)($data['blocked'] ?? ''), $taskIds, true) === true;
			}
		);
	}//end dependencyRows()

	/**
	 * Every object of a schema matching the filters, read page by page as the current user.
	 *
	 * @param string              $schema  The schema slug.
	 * @param array<string,mixed> $filters OpenRegister search filters.
	 *
	 * @return array<string,object> Entities by uuid.
	 */
	private function search(string $schema, array $filters): array {
		$found  = [];
		$offset = 0;
		do {
			$page = $this->objectService()->searchObjectsBySlug(
				registerSlug: self::REGISTER,
				schemaSlug: $schema,
				filters: array_merge($filters, ['_limit' => self::PAGE_SIZE, '_offset' => $offset])
			);
			if (is_array($page) === true && array_key_exists('results', $page) === true) {
				$page = (array)$page['results'];
			}

			$page = array_values(array_filter((array)$page, 'is_object'));
			foreach ($page as $entity) {
				$found[(string)$entity->getUuid()] = $entity;
			}

			$offset += self::PAGE_SIZE;
		} while (count($page) === self::PAGE_SIZE);

		return $found;
	}//end search()

	/**
	 * The stored values of each entity, in uuid order as read.
	 *
	 * @param array<string,object> $rows Entities by uuid.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function storedValues(array $rows): array {
		return array_values(array_map(fn (object $entity): array => $this->stored(entity: $entity), $rows));
	}//end storedValues()

	/**
	 * One entity's stored values with its `@self` metadata, as OpenRegister serialises it.
	 *
	 * @param object $entity The entity.
	 *
	 * @return array<string,mixed>
	 */
	private function stored(object $entity): array {
		if ($entity instanceof \JsonSerializable) {
			$value = $entity->jsonSerialize();
			if (is_array($value) === true) {
				return $value;
			}
		}

		return (array)$entity->getObject();
	}//end stored()

	/**
	 * The rows of `tasks.csv`, header first, with rendered values.
	 *
	 * @param array<string,object> $tasks   The project's tasks by uuid.
	 * @param array<string,object> $columns The project's columns by uuid.
	 *
	 * @return list<list<string>>
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
	 */
	private function tasksCsv(array $tasks, array $columns): array {
		$csv = [self::CSV_HEADER];
		foreach ($tasks as $task) {
			$data     = (array)$task->getObject();
			$column   = (string)($data['column'] ?? '');
			$assignee = (string)($data['assignedTo'] ?? '');
			$title    = '';
			if (isset($columns[$column]) === true) {
				$title = (string)($columns[$column]->getObject()['title'] ?? '');
			}

			$csv[] = [
				(string)($data['key'] ?? ''),
				(string)($data['title'] ?? ''),
				(string)($data['status'] ?? ''),
				$title,
				$this->displayName(uid: $assignee),
				(string)($data['startDate'] ?? ''),
				(string)($data['dueDate'] ?? ''),
				implode(', ', array_map('strval', array_filter((array)($data['labels'] ?? []), 'is_scalar'))),
			];
		}

		return $csv;
	}//end tasksCsv()

	/**
	 * A user's display name, or the uid when the account is gone.
	 *
	 * @param string $uid The user id.
	 *
	 * @return string
	 */
	private function displayName(string $uid): string {
		if ($uid === '') {
			return '';
		}

		return ($this->userManager->getDisplayName($uid) ?? $uid);
	}//end displayName()

	/**
	 * The comments on each task, by task uuid.
	 *
	 * @param array<int,string> $taskIds The task uuids.
	 *
	 * @return array<string,list<array<string,mixed>>>
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
	 */
	private function taskComments(array $taskIds): array {
		$comments = [];
		foreach ($taskIds as $taskId) {
			$rows = array_map(
				fn (IComment $comment): array => [
					'id'          => $comment->getId(),
					'actorId'     => $comment->getActorId(),
					'displayName' => $this->displayName(uid: $comment->getActorId()),
					'time'        => $comment->getCreationDateTime()->format(\DateTimeInterface::ATOM),
					'message'     => $comment->getMessage(),
				],
				$this->comments->getForObject('openregister', $taskId)
			);
			if ($rows !== []) {
				$comments[$taskId] = array_values($rows);
			}
		}

		return $comments;
	}//end taskComments()

	/**
	 * The files on the project's tasks and phases, with their path in the zip.
	 *
	 * A file the requester cannot read is left out and named in `$skipped`.
	 *
	 * @param array<string,array<string,object>>         $rows    Entities by schema and uuid.
	 * @param list<array{item:string,reason:string}> $skipped The skipped items, appended to.
	 *
	 * @return list<array{path:string,file:File}>
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.2
	 */
	private function files(array $rows, array &$skipped): array {
		$sources = [];
		foreach (($rows['task'] ?? []) as $task) {
			$sources[] = ['entity' => $task, 'folder' => 'attachments/' . $this->segment(name: (string)($task->getObject()['key'] ?? $task->getUuid()))];
		}

		foreach (($rows['projectPhase'] ?? []) as $phase) {
			$title     = (string)($phase->getObject()['title'] ?? $phase->getUuid());
			$sources[] = ['entity' => $phase, 'folder' => 'phase-documents/' . $this->segment(name: $title)];
		}

		$files = [];
		foreach ($sources as $source) {
			foreach ($this->filesOf(entity: $source['entity']) as $file) {
				$path = $source['folder'] . '/' . $this->segment(name: $file->getName());
				if ($file->isReadable() === false) {
					$skipped[] = ['item' => $path, 'reason' => 'no read access'];
					continue;
				}

				$files[] = ['path' => $path, 'file' => $file];
			}
		}

		return $files;
	}//end files()

	/**
	 * The files on one object; none when its folder cannot be read.
	 *
	 * @param object $entity The object.
	 *
	 * @return list<File>
	 */
	private function filesOf(object $entity): array {
		try {
			$nodes = $this->container->get('OCA\\OpenRegister\\Service\\FileService')->getFiles($entity);
		} catch (\Throwable $e) {
			$this->logger->info('Planninq: the files of an object were not exported', ['object' => $entity->getUuid(), 'exception' => $e->getMessage()]);
			return [];
		}

		return array_values(array_filter((array)$nodes, static fn ($node): bool => $node instanceof File));
	}//end filesOf()

	/**
	 * A name that is safe as one path segment in the zip.
	 *
	 * @param string $name The name.
	 *
	 * @return string
	 */
	private function segment(string $name): string {
		$name = trim(str_replace(['/', '\\', "\0"], '-', $name));
		if ($name === '' || $name === '.' || $name === '..') {
			return '_';
		}

		return $name;
	}//end segment()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object
	 */
	private function objectService(): object {
		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()
}//end class
