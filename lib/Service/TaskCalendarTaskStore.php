<?php

/**
 * Planninq TaskCalendarTaskStore
 *
 * The OpenRegister side of the task export (planning-calendar): the project
 * title a VTODO carries, the tasks the switch-on backfill reads, and the
 * silent system write that stores a task's VTODO UID. Read and written as the
 * system, because the export runs for users other than the actor and in a
 * background job with no user.
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
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Task and project reads and the UID write-back for the export.
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
 */
class TaskCalendarTaskStore {

	/**
	 * OpenRegister's system scope for the calendarEventUid write-back.
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * OpenRegister's object service.
	 */
	private const OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * The planninq register slug.
	 */
	private const REGISTER = 'planninq';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Lazy OpenRegister resolution.
	 * @param LoggerInterface    $logger    Diagnostics.
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * A project's title, read as the system; '' when it does not resolve.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function projectTitle(string $projectId): string {
		if ($projectId === '') {
			return '';
		}

		try {
			$entity = $this->container->get(self::OBJECT_SERVICE)->find(
				id: $projectId,
				register: self::REGISTER,
				schema: 'project',
				_rbac: false,
				_multitenancy: false
			);
		} catch (\Throwable $e) {
			return '';
		}

		$data = $this->rowData(row: $entity);

		return $this->text(value: ($data['title'] ?? ''));
	}//end projectTitle()

	/**
	 * Every planninq task, keyed by uuid, read as the system (the backfill picks the user's own).
	 *
	 * OpenRegister filters a property by equality, so a list property such as
	 * `sharedWith` cannot be searched for a member.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function tasksOf(string $userId): array {
		try {
			$results = $this->container->get(self::OBJECT_SERVICE)->searchObjectsBySlug(
				registerSlug: self::REGISTER,
				schemaSlug: 'task',
				filters: [],
				_rbac: false,
				_multitenancy: false
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: the task export backfill could not read tasks', ['user' => $userId, 'exception' => $e->getMessage()]);
			return [];
		}

		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = $results['results'];
		}

		$tasks = [];
		foreach ((array)$results as $row) {
			$data = $this->rowData(row: $row);
			$id   = $this->text(value: ($data['id'] ?? ($data['@self']['id'] ?? '')));
			if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
				$id = (string)$row->getUuid();
			}

			if ($id !== '') {
				$tasks[$id] = $data;
			}
		}

		return $tasks;
	}//end tasksOf()

	/**
	 * Store the VTODO UID on the task as a silent system write, so it raises no event.
	 *
	 * @param string              $taskId The task uuid.
	 * @param array<string,mixed> $task   The task data.
	 * @param string              $uid    The UID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function storeUid(string $taskId, array $task, string $uid): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			return;
		}

		unset($task['@self'], $task['id']);
		$task['calendarEventUid'] = $uid;
		try {
			$objectService = $this->container->get(self::OBJECT_SERVICE);
			$class::run(
				static fn () => $objectService->saveObject(
					object: $task,
					register: self::REGISTER,
					schema: 'task',
					uuid: $taskId,
					_rbac: false,
					_multitenancy: false,
					silent: true
				)
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: could not store the calendar UID on a task', ['task' => $taskId, 'exception' => $e->getMessage()]);
		}
	}//end storeUid()

	/**
	 * The data of a search row or entity.
	 *
	 * @param mixed $row The row.
	 *
	 * @return array<string,mixed>
	 */
	private function rowData(mixed $row): array {
		if (is_object($row) === true && is_callable([$row, 'getObject']) === true) {
			return (array)$row->getObject();
		}

		if (is_object($row) === true && method_exists($row, 'jsonSerialize') === true) {
			return (array)$row->jsonSerialize();
		}

		return (array)$row;
	}//end rowData()

	/**
	 * A scalar as a string; anything else as ''.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function text(mixed $value): string {
		if (is_scalar($value) === true) {
			return (string)$value;
		}

		return '';
	}//end text()
}//end class
