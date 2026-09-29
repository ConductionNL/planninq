<?php

/**
 * Planninq WorkItemKeyService
 *
 * Project keys and readable task keys (VERG-42). A project key is two to ten
 * letters and digits starting with a letter, unique across all projects. A
 * task key is the project key and the next number of the project's counter
 * `nextTaskNumber`, read and bumped under a per-project lock so two creates
 * never get the same number.
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
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Project keys and the task numbers drawn from them.
 */
class WorkItemKeyService {

	/**
	 * Prefix of the per-project lock around the counter.
	 *
	 * @var string
	 */
	public const LOCK_PREFIX = 'planninq/task-key/';

	/**
	 * The project field that holds the next task number.
	 *
	 * @var string
	 */
	public const COUNTER = 'nextTaskNumber';

	/**
	 * Attempts to take the lock before a create is refused.
	 *
	 * @var integer
	 */
	private const LOCK_ATTEMPTS = 50;

	/**
	 * Microseconds between two lock attempts.
	 *
	 * @var integer
	 */
	private const LOCK_WAIT = 20000;

	/**
	 * OpenRegister's system-operation scope.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects and tasks as the system.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService.
	 * @param ILockingProvider         $locking    The per-project counter lock.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private ILockingProvider $locking,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * A key as it is stored: trimmed and uppercase.
	 *
	 * @param mixed $key The key as sent.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function normalise(mixed $key): string {
		if (is_string($key) === false) {
			return '';
		}

		return strtoupper(trim($key));
	}//end normalise()

	/**
	 * Whether a stored key has the project key format.
	 *
	 * @param string $key The normalised key.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function isValidFormat(string $key): bool {
		return preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) === 1;
	}//end isValidFormat()

	/**
	 * Whether another project already uses a key, whether or not the caller can read it.
	 *
	 * @param string $key       The normalised key.
	 * @param string $projectId The project that may keep its own key.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function isTaken(string $key, string $projectId = ''): bool {
		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['key' => $key]) as $row) {
			if ($row['id'] !== $projectId) {
				return true;
			}
		}

		return false;
	}//end isTaken()

	/**
	 * Whether a project has numbered any task, after which its key stays.
	 *
	 * @param array<string,mixed> $project The stored project.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
	 */
	public function hasNumberedTasks(array $project): bool {
		return (int)($project[self::COUNTER] ?? 0) > 1;
	}//end hasNumberedTasks()

	/**
	 * The key for a new task in a project, or null when the project has no key.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return string|null
	 *
	 * @throws LockedException When the counter stays locked by other creates.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
	 */
	public function nextTaskKey(string $projectId): ?string {
		$keys = $this->reserve(projectId: $projectId, count: 1);

		return ($keys[0] ?? null);
	}//end nextTaskKey()

	/**
	 * Give a project's keyless tasks keys, oldest first; tasks with a key keep it.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return int The number of tasks numbered.
	 *
	 * @throws LockedException When the counter stays locked by other creates.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
	 */
	public function numberTasks(string $projectId): int {
		$tasks = array_values(
			array_filter(
				$this->tasksOf(projectId: $projectId),
				static fn (array $task): bool => trim((string)($task['data']['key'] ?? '')) === ''
			)
		);
		if ($tasks === []) {
			return 0;
		}

		$keys = $this->reserve(projectId: $projectId, count: count($tasks));
		foreach ($keys as $index => $key) {
			$task = $tasks[$index];
			$this->writeAsSystem(schema: 'task', uuid: $task['id'], data: array_merge($task['data'], ['key' => $key]));
		}

		return count($keys);
	}//end numberTasks()

	/**
	 * Take a run of numbers from a project's counter under its lock.
	 *
	 * @param string $projectId The project UUID.
	 * @param int    $count     How many numbers.
	 *
	 * @return array<int,string> The keys, empty when the project has no key.
	 *
	 * @throws LockedException When the counter stays locked.
	 */
	private function reserve(string $projectId, int $count): array {
		$path = self::LOCK_PREFIX . $projectId;
		$this->lock(path: $path);

		try {
			$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
			$key     = $this->normalise(key: ($project['key'] ?? null));
			if ($project === null || $key === '') {
				return [];
			}

			$next = (int)($project[self::COUNTER] ?? 0);
			if ($next < 1) {
				$next = ($this->highestNumber(projectId: $projectId, key: $key) + 1);
			}

			$keys = [];
			for ($offset = 0; $offset < $count; $offset++) {
				$keys[] = $key . '-' . ($next + $offset);
			}

			$this->writeAsSystem(
				schema: ProjectMembershipService::PROJECT_SCHEMA,
				uuid: $projectId,
				data: array_merge($project, [self::COUNTER => ($next + $count)])
			);

			return $keys;
		} finally {
			$this->locking->releaseLock($path, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}//end reserve()

	/**
	 * Take the lock, waiting a moment for a create that holds it.
	 *
	 * @param string $path The lock path.
	 *
	 * @return void
	 *
	 * @throws LockedException When it stays held.
	 */
	private function lock(string $path): void {
		for ($attempt = 1; $attempt <= self::LOCK_ATTEMPTS; $attempt++) {
			try {
				$this->locking->acquireLock($path, ILockingProvider::LOCK_EXCLUSIVE, 'planninq task numbers');
				return;
			} catch (LockedException $e) {
				if ($attempt === self::LOCK_ATTEMPTS) {
					throw $e;
				}

				usleep(self::LOCK_WAIT);
			}
		}
	}//end lock()

	/**
	 * The highest number among the project's task keys that start with its key.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $key       The project key.
	 *
	 * @return int
	 */
	private function highestNumber(string $projectId, string $key): int {
		$highest = 0;
		$pattern = '/^' . preg_quote($key, '/') . '-(\d+)$/';
		foreach ($this->tasksOf(projectId: $projectId) as $task) {
			if (preg_match($pattern, (string)($task['data']['key'] ?? ''), $match) === 1) {
				$highest = max($highest, (int)$match[1]);
			}
		}

		return $highest;
	}//end highestNumber()

	/**
	 * The tasks of a project, in the order the register returns them (creation order).
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 */
	private function tasksOf(string $projectId): array {
		return $this->membership->rows(schema: 'task', filters: ['project' => $projectId]);
	}//end tasksOf()

	/**
	 * Write an object as the system: no RBAC, no events, so the counter guard lets it through.
	 *
	 * @param string              $schema The schema slug.
	 * @param string              $uuid   The object UUID.
	 * @param array<string,mixed> $data   The full object data.
	 *
	 * @return void
	 */
	private function writeAsSystem(string $schema, string $uuid, array $data): void {
		$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$write         = static fn () => $objectService->saveObject(
			object: $data,
			register: ProjectMembershipService::REGISTER,
			schema: $schema,
			uuid: $uuid,
			_rbac: false,
			_multitenancy: false,
			silent: true,
			_validation: false
		);

		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			$this->logger->warning('Planninq: OpenRegister has no system-operation scope; task numbers written without it', ['schema' => $schema]);
			$write();
			return;
		}

		$class::run($write);
	}//end writeAsSystem()
}//end class
