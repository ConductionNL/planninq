<?php

/**
 * Planninq WorkflowColumnSyncListener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Keeps project columns in step with their shared workflow
 * (projects-templates-shared-workflow, task 2.2): when a workflow's columns
 * change, every project that follows it is updated; when a project's
 * `workflow` changes, that project's columns are built from the workflow.
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\WorkflowColumnSync;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Runs the workflow column sync after a workflow or project is saved.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
class WorkflowColumnSyncListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param WorkflowColumnSync $sync          Updates the columns.
	 * @param TaskScopeResolver  $scopeResolver Tells which planninq schema an object is.
	 * @param LoggerInterface    $logger        The logger.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function __construct(
		private WorkflowColumnSync $sync,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Sync on a saved workflow whose columns changed, or a project whose workflow changed.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @listener-placement inline correctness: a board must show the workflow's
	 * columns as soon as the workflow or the project is saved. The work is
	 * bounded by the projects on one workflow and runs only on a real change.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function handle(Event $event): void {
		[$object, $old] = $this->objectsOf(event: $event);
		if ($object === null) {
			return;
		}

		try {
			$schema  = $this->scopeResolver->planninqSchemaSlug(
				registerId: (string)($object->getRegister() ?? ''),
				schemaId: (string)($object->getSchema() ?? '')
			);
			$id      = (string)($object->getUuid() ?? '');
			$data    = (array)$object->getObject();
			$oldData = null;
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$columnsChanged = ($oldData === null || json_encode($oldData['columns'] ?? null) !== json_encode($data['columns'] ?? null));
			if ($schema === WorkflowColumnSync::WORKFLOW_SCHEMA && $columnsChanged === true) {
				$this->sync->syncWorkflow(workflowId: $id);
				return;
			}

			$workflow = (string)($data['workflow'] ?? '');
			if ($schema === ProjectMembershipService::PROJECT_SCHEMA && $workflow !== '' && (string)($oldData['workflow'] ?? '') !== $workflow) {
				$this->sync->syncProjectOnto(projectId: $id, workflowId: $workflow);
			}
		} catch (\Throwable $e) {
			// The save already happened; the columns stay as they were until
			// the next change or `occ planninq:workflow:resync`.
			$this->logger->error('Planninq: could not sync workflow columns', ['exception' => $e->getMessage()]);
		}//end try
	}//end handle()

	/**
	 * The saved object and, for an update, the stored one before it.
	 *
	 * @param Event $event The event.
	 *
	 * @return array{0: object|null, 1: object|null}
	 */
	private function objectsOf(Event $event): array {
		if ($event instanceof ObjectUpdatedEvent) {
			return [$event->getNewObject(), $event->getOldObject()];
		}

		if (is_a($event, ObjectCreatedEvent::class) === true && method_exists($event, 'getObject') === true) {
			return [$event->getObject(), null];
		}

		return [null, null];
	}//end objectsOf()
}//end class
