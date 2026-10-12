<?php

/**
 * Planninq WorkflowColumnSyncListener
 *
 * Keeps the columns of the projects that follow a workflow in step with it,
 * builds a project's columns when it is put on a workflow, and refuses to
 * delete a workflow that projects still follow.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Service\WorkflowSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Workflow to project column sync.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
 */
class WorkflowColumnSyncListener implements IEventListener {

	/**
	 * Error code of a refused workflow delete.
	 *
	 * @var string
	 */
	public const ERROR_IN_USE = 'planninq-workflow-in-use';

	/**
	 * Constructor.
	 *
	 * @param WorkflowSyncService $sync          Does the column sync.
	 * @param TaskScopeResolver   $scopeResolver Tells a planninq schema from any other object.
	 * @param LoggerInterface     $logger        The logger.
	 */
	public function __construct(
		private WorkflowSyncService $sync,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a workflow or project event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.2
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectUpdatedEvent === true) {
			$this->onUpdated(event: $event);
			return;
		}

		if ($event instanceof ObjectCreatedEvent === true) {
			$object = $event->getObject();
			if ($this->slugOf(object: $object) === 'project' && (string)(((array)$object->getObject())['workflow'] ?? '') !== '') {
				$this->sync->syncProject(projectId: (string)$object->getUuid());
			}

			return;
		}

		if ($event instanceof ObjectDeletingEvent === true) {
			$this->onDeleting(event: $event);
		}
	}//end handle()

	/**
	 * A workflow changed: sync its projects. A project changed workflow: build its columns.
	 *
	 * @param ObjectUpdatedEvent $event The event.
	 *
	 * @return void
	 */
	private function onUpdated(ObjectUpdatedEvent $event): void {
		$object = $event->getNewObject();
		$slug   = $this->slugOf(object: $object);
		if ($slug === 'workflow') {
			$this->sync->syncWorkflow(workflowId: (string)$object->getUuid());
			return;
		}

		if ($slug !== 'project') {
			return;
		}

		$new = (string)(((array)$object->getObject())['workflow'] ?? '');
		$old = (string)(((array)($event->getOldObject()?->getObject() ?? []))['workflow'] ?? '');
		if ($new !== '' && $new !== $old) {
			$this->sync->syncProject(projectId: (string)$object->getUuid());
		}
	}//end onUpdated()

	/**
	 * Refuse deleting a workflow that projects follow.
	 *
	 * @param ObjectDeletingEvent $event The event.
	 *
	 * @return void
	 */
	private function onDeleting(ObjectDeletingEvent $event): void {
		$object = $event->getObject();
		if ($this->slugOf(object: $object) !== 'workflow') {
			return;
		}

		$projects = $this->sync->projectsOn(workflowId: (string)$object->getUuid());
		if ($projects === []) {
			return;
		}

		$event->setErrors(
			[
				'message' => count($projects) . ' projects follow this workflow. Move them to another workflow first.',
				'code'    => self::ERROR_IN_USE,
				'count'   => count($projects),
			]
		);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused deleting a workflow that projects follow', ['count' => count($projects)]);
	}//end onDeleting()

	/**
	 * The planninq schema slug of an object, or ''.
	 *
	 * @param object $object The event's object.
	 *
	 * @return string
	 */
	private function slugOf(object $object): string {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
	}//end slugOf()
}//end class
