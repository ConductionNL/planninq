<?php

/**
 * Planninq BoardColumnService
 *
 * Owns a project's board columns on the server: the column objects a new
 * project starts with (mapped from the admin's default column titles), and the
 * one-off placement of every task that has no column yet, so a board that
 * grouped cards by status before keeps every card once it groups them by
 * column.
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
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates default columns and places column-less tasks into a column.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
 */
class BoardColumnService {

	/**
	 * The register every planninq object lives in.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * Fallback column titles when the admin setting is empty or unreadable.
	 *
	 * @var array<int,string>
	 */
	public const FALLBACK_TITLES = ['To do', 'In progress', 'Review', 'Done'];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService at runtime.
	 * @param ProjectMembershipService $membership Reads planninq objects with RBAC off.
	 * @param SettingsService          $settings   Holds the admin's default column titles.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private ProjectMembershipService $membership,
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Map a list of column titles to column objects.
	 *
	 * Every column is active except the last, which is the done column. The
	 * mapped status follows the position: the first column opens a task, the
	 * last finishes it, every column between puts it in progress.
	 *
	 * @param array<int,string> $titles    The column titles, left to right.
	 * @param string            $projectId The project the columns belong to.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.2
	 */
	public function columnObjects(array $titles, string $projectId): array {
		$titles = array_values(
			array_filter(
				array_map(static fn (mixed $title): string => trim((string)$title), $titles),
				static fn (string $title): bool => $title !== ''
			)
		);
		if ($titles === []) {
			$titles = self::FALLBACK_TITLES;
		}

		$last    = (count($titles) - 1);
		$columns = [];
		foreach ($titles as $index => $title) {
			$status = 'in_progress';
			$type   = 'active';
			if ($index === $last) {
				$status = 'done';
				$type   = 'done';
			} else if ($index === 0) {
				$status = 'open';
			}

			$columns[] = [
				'title'   => $title,
				'project' => $projectId,
				'order'   => $index,
				'type'    => $type,
				'status'  => $status,
			];
		}

		return $columns;
	}//end columnObjects()

	/**
	 * The admin's default column titles, or the fallback when the setting is unusable.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.2
	 */
	public function defaultTitles(): array {
		$raw     = (string)($this->settings->getAdminSettings()['default_columns'] ?? '');
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || $decoded === []) {
			return self::FALLBACK_TITLES;
		}

		return array_values(array_map(static fn (mixed $title): string => (string)$title, $decoded));
	}//end defaultTitles()

	/**
	 * Create a project's default columns from the admin setting.
	 *
	 * Writes with RBAC off: the caller has already been allowed to create the
	 * project (the policy gate in ProjectController) or is a repair step.
	 *
	 * @param string $projectId The new project's UUID.
	 *
	 * @return array<int,array<string,mixed>> The saved columns, id included.
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.2
	 */
	public function createDefaultColumns(string $projectId): array {
		if ($projectId === '') {
			return [];
		}

		$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$saved         = [];
		foreach ($this->columnObjects(titles: $this->defaultTitles(), projectId: $projectId) as $column) {
			try {
				$entity = $objectService->saveObject(
					object: $column,
					register: self::REGISTER,
					schema: 'column',
					_rbac: false,
					_multitenancy: false
				);
			} catch (\Throwable $e) {
				$this->logger->error(
					'Planninq: could not create a default column',
					['project' => $projectId, 'column' => $column['title'], 'exception' => $e->getMessage()]
				);
				continue;
			}

			$saved[] = ['id' => (string)($entity?->getUuid() ?? '')] + $column;
		}//end foreach

		return $saved;
	}//end createDefaultColumns()

	/**
	 * Give every task of a project that has no column, and is not cancelled, a column.
	 *
	 * Creates the default columns first when the project has none. Tasks that
	 * already carry a column are never touched, so a second run writes nothing.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return array{created:int,assigned:int}
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function assignColumns(string $projectId): array {
		$columns = array_map(
			static fn (array $row): array => ['id' => $row['id']] + $row['data'],
			$this->membership->rows(schema: 'column', filters: ['project' => $projectId])
		);
		$created = 0;
		if ($columns === []) {
			$columns = $this->createDefaultColumns(projectId: $projectId);
			$created = count($columns);
		}

		usort($columns, static fn (array $a, array $b): int => ((int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0)));

		$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$assigned      = 0;
		foreach ($this->membership->rows(schema: 'task', filters: ['project' => $projectId]) as $task) {
			$status = (string)($task['data']['status'] ?? 'open');
			if (empty($task['data']['column']) === false || $status === 'cancelled') {
				continue;
			}

			$column = $this->columnForStatus(columns: $columns, status: $status);
			if ($column === null || $column['id'] === '') {
				continue;
			}

			$assigned += $this->writeColumn(objectService: $objectService, task: $task, columnId: $column['id']);
		}

		return ['created' => $created, 'assigned' => $assigned];
	}//end assignColumns()

	/**
	 * Run assignColumns() over every project.
	 *
	 * @return array{projects:int,created:int,assigned:int}
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function assignAll(): array {
		$totals = ['projects' => 0, 'created' => 0, 'assigned' => 0];
		foreach ($this->membership->rows(schema: 'project', filters: []) as $project) {
			$result = $this->assignColumns(projectId: $project['id']);
			$totals['projects']++;
			$totals['created']  += $result['created'];
			$totals['assigned'] += $result['assigned'];
		}

		return $totals;
	}//end assignAll()

	/**
	 * The column a task with this status belongs in.
	 *
	 * A done task goes to the done column. Any other task goes to the first
	 * column mapped to its status, else the first active column.
	 *
	 * @param array<int,array<string,mixed>> $columns The project's columns, in order.
	 * @param string                         $status  The task status.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function columnForStatus(array $columns, string $status): ?array {
		$wanted = [
			static fn (array $column): bool => ($status === 'done' && ($column['type'] ?? 'active') === 'done'),
			static fn (array $column): bool => (($column['status'] ?? null) === $status),
			static fn (array $column): bool => ($status !== 'done' && ($column['type'] ?? 'active') !== 'done'),
		];
		foreach ($wanted as $match) {
			foreach ($columns as $column) {
				if ($match($column) === true) {
					return $column;
				}
			}
		}

		return ($columns[0] ?? null);
	}//end columnForStatus()

	/**
	 * Store a task's column, logging and counting nothing on failure.
	 *
	 * @param object                                         $objectService OpenRegister's ObjectService.
	 * @param array{id:string,data:array<string,mixed>}      $task          The task row.
	 * @param string                                         $columnId      The column UUID.
	 *
	 * @return int 1 when written, 0 when not.
	 */
	private function writeColumn(object $objectService, array $task, string $columnId): int {
		$data           = $task['data'];
		$data['column'] = $columnId;
		try {
			$objectService->saveObject(
				object: $data,
				register: self::REGISTER,
				schema: 'task',
				uuid: $task['id'],
				_rbac: false,
				_multitenancy: false,
				silent: true
			);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Planninq: could not place a task in a board column',
				['task' => $task['id'], 'exception' => $e->getMessage()]
			);
			return 0;
		}

		return 1;
	}//end writeColumn()
}//end class
