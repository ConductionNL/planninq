<?php

/**
 * Planix Task Dependency Cleanup Listener
 *
 * Removes every dependency edge a task participates in, at the moment that task
 * is deleted.
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
 * @package  OCA\Planix\Listener
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
 */

declare(strict_types=1);

namespace OCA\Planix\Listener;

use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\Planix\Service\DependencyService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Cascade-remove dependency edges when a planix task is deleted.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
class TaskDependencyCleanupListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param DependencyService $dependencyService Dependency edge writes.
	 * @param TaskScopeResolver $scopeResolver Resolves whether an object is a planix task.
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
	 * Handle a pre-delete event.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectDeletingEvent === false) {
			return;
		}

		try {
			$object = $event->getObject();
			$registerId = (string)($object->getRegister() ?? '');
			$schemaId = (string)($object->getSchema() ?? '');
			if ($this->scopeResolver->isPlanixTask(registerId: $registerId, schemaId: $schemaId) === false) {
				return;
			}

			$taskId = (string)($object->getUuid() ?? '');
			if ($taskId === '') {
				return;
			}

			$removed = $this->dependencyService->removeEdgesForTask($taskId);
			if ($removed > 0) {
				$this->logger->info(
					'Planix: removed dependency edges for deleted task',
					['task' => $taskId, 'edges' => $removed]
				);
			}
		} catch (\Throwable $e) {
			// Do NOT stop propagation. A cleanup failure must not block the
			// user's delete — the task still goes, and the residue is logged at
			// error level so it is visible rather than silently tolerated.
			$this->logger->error(
				'Planix: dependency cleanup failed for a deleted task; edges may be orphaned',
				['exception' => $e->getMessage()]
			);
		}//end try
	}//end handle()
}//end class
