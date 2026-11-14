<?php

/**
 * Planninq Task Dependency Cleanup Listener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Removes every dependency edge a task participates in, at the moment that task
 * is deleted or moved to another project. Edges never cross projects
 * (DependencyService refuses them), so a task that changes project keeps none.
 *
 * WHY THIS EXISTS
 * ---------------
 * `DependencyService::removeEdgesForTask()` documented itself as "called from
 * the task delete flow and the move-to-another-project flow" — and NOTHING
 * called it. gate-57 (orphaned-write-capability) is what surfaced that: a write
 * method no caller can reach is not dead weight here, it is a MISSING CASCADE.
 * Deleting a task left its edges behind, pointing at a task that no longer
 * exists, and every graph read then had to tolerate dangling endpoints.
 *
 * WHY THE `*ing` EVENT, NOT `*ed`
 * -------------------------------
 * ADR-078 / gate-61 fail a POST-event listener (`ObjectDeletedEvent`) that does
 * a synchronous write: a post listener cannot influence the write it observes,
 * so any real work it does is pure latency charged to the user's request, and
 * such work belongs in a deferred job.
 *
 * A PRE-event listener is the opposite case, and gate-61 says so explicitly:
 * `*ing` listeners may veto or mutate and therefore MUST stay synchronous. The
 * cascade genuinely belongs inside the delete — the edges must go with the task,
 * not eventually — so this is the sanctioned shape and needs no job queue.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\DependencyService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Cascade-remove dependency edges when a Planninq task is deleted or moved to
 * another project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-1.1
 */
class TaskDependencyCleanupListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param DependencyService $dependencyService Dependency edge writes.
	 * @param TaskScopeResolver $scopeResolver Resolves whether an object is a Planninq task.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function __construct(
		private DependencyService $dependencyService,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a pre-delete or pre-update event.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-1.1
	 */
	public function handle(Event $event): void {
		try {
			$object = $this->taskLeavingItsProject(event: $event);
			if ($object === null) {
				return;
			}

			$taskId = (string)($object->getUuid() ?? '');
			if ($taskId === '') {
				return;
			}

			$removed = $this->dependencyService->removeEdgesForTask($taskId);
			if ($removed > 0) {
				$this->logger->info(
					'Planninq: removed dependency edges for a deleted or moved task',
					['task' => $taskId, 'edges' => $removed]
				);
			}
		} catch (\Throwable $e) {
			// Do NOT stop propagation. A cleanup failure must not block the
			// user's delete or move — the write still goes, and the residue is
			// logged at error level so it is visible rather than silently tolerated.
			$this->logger->error(
				'Planninq: dependency cleanup failed for a deleted or moved task; edges may be orphaned',
				['exception' => $e->getMessage()]
			);
		}//end try
	}//end handle()

	/**
	 * The Planninq task an event deletes or moves to another project, or null.
	 *
	 * A guard that ran earlier (TaskReporterGuardListener) may have refused the
	 * write: the task stays where it is, so do its edges.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return ObjectEntity|null
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-1.1
	 */
	private function taskLeavingItsProject(Event $event): ?ObjectEntity {
		$object = $this->leavingObject(event: $event);
		if ($object === null || $event->isPropagationStopped() === true) {
			return null;
		}

		$registerId = (string)($object->getRegister() ?? '');
		$schemaId = (string)($object->getSchema() ?? '');
		if ($this->scopeResolver->isPlanninqTask(registerId: $registerId, schemaId: $schemaId) === false) {
			return null;
		}

		return $object;
	}//end taskLeavingItsProject()

	/**
	 * The object a delete removes, or the object an update moves to another
	 * project; null for any other event or an update that keeps the project.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return ObjectEntity|null
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-1.1
	 */
	private function leavingObject(Event $event): ?ObjectEntity {
		if ($event instanceof ObjectDeletingEvent === true) {
			return $event->getObject();
		}

		if ($event instanceof ObjectUpdatingEvent === false || $event->getOldObject() === null) {
			return null;
		}

		$before = $this->projectOf(object: $event->getOldObject());
		$after = $this->projectOf(object: $event->getNewObject());
		if ($before === $after) {
			return null;
		}

		return $event->getNewObject();
	}//end leavingObject()

	/**
	 * The project uuid a task names, whether stored as a uuid or as a reference
	 * object; '' when it names none.
	 *
	 * @param ObjectEntity $object The task.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-1.1
	 */
	private function projectOf(ObjectEntity $object): string {
		$project = (((array)$object->getObject())['project'] ?? null);
		if (is_array($project) === true) {
			$project = ($project['id'] ?? $project['uuid'] ?? null);
		}

		return trim((string)$project);
	}//end projectOf()
}//end class
