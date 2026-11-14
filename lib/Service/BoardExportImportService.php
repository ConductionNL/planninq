<?php

/**
 * Planninq BoardExportImportService
 *
 * Imports a board exported from another tool as a new planninq project: the
 * export is mapped by {@see BoardExportMapper} under one of the mapping
 * configurations in `lib/Settings/board-import/`, then the project, its
 * columns (in the source's order, the last one being the done column, as for
 * a new project's default columns) and its tasks are written to OpenRegister.
 * A task takes its column's status. A person named in the export is assigned
 * only when a Nextcloud account has that user id; otherwise the task stays
 * unassigned and the name is reported.
 *
 * Callers are admin-only: the import writes with OpenRegister's RBAC off, as
 * the default-column step of a new project does.
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
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use InvalidArgumentException;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes a mapped board export as a project with columns and tasks.
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
 */
class BoardExportImportService {

	/**
	 * The planninq register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * Constructor.
	 *
	 * @param BoardExportMapper  $mapper    Maps an export onto a plan.
	 * @param BoardColumnService $columns   Shapes columns the way a new project's are.
	 * @param IUserManager       $users     Tells which named people have an account.
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService.
	 * @param LoggerInterface    $logger    The logger.
	 * @param string|null        $configDir Where the mapping configurations live; the shipped directory when null.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function __construct(
		private BoardExportMapper $mapper,
		private BoardColumnService $columns,
		private IUserManager $users,
		private ContainerInterface $container,
		private LoggerInterface $logger,
		private ?string $configDir = null,
	) {
	}//end __construct()

	/**
	 * The exports this instance can import, as id and label.
	 *
	 * @return array<int,array{id: string, label: string, format: string}>
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function tools(): array {
		$tools = [];
		foreach ($this->configs() as $id => $config) {
			$tools[] = ['id' => $id, 'label' => (string)($config['label'] ?? $id), 'format' => (string)($config['format'] ?? '')];
		}

		return $tools;
	}//end tools()

	/**
	 * Map an export without writing anything.
	 *
	 * @param string                               $tool The mapping configuration id.
	 * @param array{name: string, content: string} $file The uploaded export.
	 *
	 * @return array<string,mixed> The plan, as {@see BoardExportMapper::map()} returns it.
	 *
	 * @throws InvalidArgumentException When the tool is unknown or the file cannot be read.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function plan(string $tool, array $file): array {
		$config = ($this->configs()[$tool] ?? null);
		if ($config === null) {
			throw new InvalidArgumentException('Unknown import "' . $tool . '".');
		}

		$plan = $this->mapper->map(config: $config, content: $file['content'], fileName: $file['name']);
		if (trim((string)($plan['project']['title'] ?? '')) === '') {
			throw new InvalidArgumentException('The export names no project.');
		}

		if ($plan['columns'] === []) {
			throw new InvalidArgumentException('The export has no lists or statuses to make columns of.');
		}

		return $plan;
	}//end plan()

	/**
	 * Import an export as a new project owned by the caller.
	 *
	 * @param string                               $tool  The mapping configuration id.
	 * @param array{name: string, content: string} $file  The uploaded export.
	 * @param string                               $owner The importing admin's user id.
	 *
	 * @return array{project: string, columns: int, tasks: int, losses: array<int,string>}
	 *
	 * @throws InvalidArgumentException When the tool is unknown or the file cannot be read.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function import(string $tool, array $file, string $owner): array {
		$plan          = $this->plan(tool: $tool, file: $file);
		$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$losses        = $plan['losses'];

		$projectId = $this->save(
			objectService: $objectService,
			schema: 'project',
			data: $plan['project'] + ['status' => 'active', 'owner' => $owner, 'members' => [$owner], 'metadata' => ['importedFrom' => $tool]]
		);

		$columnIds = [];
		$statuses  = [];
		$shaped    = $this->columns->columnObjects(titles: array_column($plan['columns'], 'title'), projectId: $projectId);
		foreach ($plan['columns'] as $index => $column) {
			$columnIds[$column['key']] = $this->save(objectService: $objectService, schema: 'column', data: $shaped[$index]);
			$statuses[$column['key']]  = (string)$shaped[$index]['status'];
		}

		$order = [];
		foreach ($plan['tasks'] as $task) {
			$key  = $task['column'];
			$data = $task['data'] + [
				'project'     => $projectId,
				'column'      => $columnIds[$key],
				'columnOrder' => ($order[$key] ?? 0),
				'status'      => $statuses[$key],
				'metadata'    => ['importedFrom' => $tool, 'sourceId' => $task['sourceId']],
			];
			$order[$key] = (($order[$key] ?? 0) + 1);
			$this->save(objectService: $objectService, schema: 'task', data: $this->withUsers(data: $data, users: $task['users'], losses: $losses));
		}

		$this->logger->info('Planninq: imported a board export', ['tool' => $tool, 'project' => $projectId, 'tasks' => count($plan['tasks'])]);

		return ['project' => $projectId, 'columns' => count($columnIds), 'tasks' => count($plan['tasks']), 'losses' => $losses];
	}//end import()

	/**
	 * Add the user fields whose names are Nextcloud accounts.
	 *
	 * @param array<string,mixed>  $data   The task data.
	 * @param array<string,string> $users  Field => name from the export.
	 * @param array<int,string>    $losses Collects names that are not accounts.
	 *
	 * @return array<string,mixed> The task data.
	 */
	private function withUsers(array $data, array $users, array &$losses): array {
		foreach ($users as $field => $name) {
			if ($this->users->userExists($name) === true) {
				$data[$field] = $name;
				continue;
			}

			$losses[] = '"' . (string)($data['title'] ?? '') . '": ' . $name . ' has no account here, so the task is unassigned.';
		}

		return $data;
	}//end withUsers()

	/**
	 * Save one object and return its UUID.
	 *
	 * @param object              $objectService OpenRegister's ObjectService.
	 * @param string              $schema        The schema slug.
	 * @param array<string,mixed> $data          The object.
	 *
	 * @return string The UUID.
	 */
	private function save(object $objectService, string $schema, array $data): string {
		$saved = $objectService->saveObject(object: $data, register: self::REGISTER, schema: $schema, _rbac: false);
		if (is_object($saved) === true && is_callable([$saved, 'getUuid']) === true) {
			return (string)$saved->getUuid();
		}

		return (string)($saved['@self']['id'] ?? ($saved['id'] ?? ''));
	}//end save()

	/**
	 * The shipped mapping configurations, by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function configs(): array {
		$files = glob(($this->configDir ?? (__DIR__ . '/../Settings/board-import')) . '/*.json');
		if ($files === false) {
			return [];
		}

		sort($files);
		$configs = [];
		foreach ($files as $file) {
			$config = json_decode((string)file_get_contents($file), true);
			if (is_array($config) === true && (string)($config['id'] ?? '') !== '') {
				$configs[(string)$config['id']] = $config;
			}
		}

		return $configs;
	}//end configs()

}//end class
