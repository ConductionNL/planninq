<?php

/**
 * Planninq ProjectMembershipSyncListener
 *
 * When a project's members or owner change, writes the new members list to
 * every task, column, phase and planned time entry of that project.
 *
 * Those four schemas are scoped by their own copy of the list
 * (`{"members": {"$contains": "$userId"}}`, planninq#681), so the copy has to
 * follow the project. The project spec says an added member "MUST immediately
 * be able to access the project board and tasks"; a removed member must lose
 * them just as fast.
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
 * @spec openspec/specs/projects.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Copies a project's changed membership to the objects of that project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/projects.md
 */
class ProjectMembershipSyncListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Computes and writes the members list.
	 * @param TaskScopeResolver $scopeResolver Tells whether an object is a planninq project.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Copy a changed project membership to the project's objects.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @listener-placement inline correctness: the members list IS the access
	 * rule of every task, column, phase and time entry of the project. Deferred
	 * to a job, a removed member keeps reading and editing the project's tasks
	 * until cron runs, and an added member sees an empty board. The spec requires
	 * access to change immediately. The work is bounded by the size of one
	 * project and runs only when members or owner actually changed.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectUpdatedEvent === false) {
			return;
		}

		try {
			$project = $event->getNewObject();
			$schemaSlug = $this->scopeResolver->planninqSchemaSlug(
				registerId: (string)($project->getRegister() ?? ''),
				schemaId: (string)($project->getSchema() ?? '')
			);
			if ($schemaSlug !== ProjectMembershipService::PROJECT_SCHEMA) {
				return;
			}

			$members = $this->membership->membersFromProject(project: (array)$project->getObject());
			$old = $event->getOldObject();
			if ($old !== null && $this->membership->membersFromProject(project: (array)$old->getObject()) === $members) {
				return;
			}

			$projectId = (string)($project->getUuid() ?? '');
			$written = $this->membership->syncProjectMembers(projectId: $projectId, members: $members);
			$this->logger->info(
				'Planninq: project membership changed; members list updated on its objects',
				['project' => $projectId, 'written' => $written]
			);
		} catch (\Throwable $e) {
			// The project write already happened and must not turn into an
			// error. The objects keep the previous list until the next change
			// or the repair step, and the residue is logged at error level.
			$this->logger->error(
				'Planninq: could not copy a project membership change to its objects',
				['exception' => $e->getMessage()]
			);
		}//end try
	}//end handle()
}//end class
