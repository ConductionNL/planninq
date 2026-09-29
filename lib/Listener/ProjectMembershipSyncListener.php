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
use OCA\Planninq\Service\FinanceLineService;
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
	 * @param FinanceLineService $finance Keeps the access copies on the project's finance lines.
	 * @param TaskScopeResolver $scopeResolver Tells whether an object is a planninq project.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private FinanceLineService $finance,
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

			$data      = (array)$project->getObject();
			$old       = $event->getOldObject();
			$oldData   = null;
			$projectId = (string)($project->getUuid() ?? '');
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$members = $this->membership->membersFromProject(project: $data);
			if ($oldData === null || $this->membership->membersFromProject(project: $oldData) !== $members) {
				$written = $this->membership->syncProjectMembers(projectId: $projectId, members: $members);
				$this->logger->info(
					'Planninq: project membership changed; members list updated on its objects',
					['project' => $projectId, 'written' => $written]
				);
			}

			$field   = ProjectMembershipService::READERS_FIELD;
			$readers = $this->membership->normalise(members: ($data[$field] ?? []));
			if ($oldData === null || $this->membership->normalise(members: ($oldData[$field] ?? [])) !== $readers) {
				$this->membership->syncProjectMembers(projectId: $projectId, members: $readers, field: $field);
			}

			if ($oldData === null || $this->financeCopiesChanged(data: $data, oldData: $oldData) === true) {
				// portfolio-finance task 3.3: owner, portfolio and its managers are copied onto the money.
				$this->finance->syncProject(projectId: $projectId);
			}
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

	/**
	 * Whether a project change touches what its finance lines copy.
	 *
	 * @param array<string,mixed> $data    The saved project.
	 * @param array<string,mixed> $oldData The stored project before the change.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	private function financeCopiesChanged(array $data, array $oldData): bool {
		$field = ProjectMembershipService::READERS_FIELD;
		if ($this->membership->normalise(members: ($data[$field] ?? [])) !== $this->membership->normalise(members: ($oldData[$field] ?? []))) {
			return true;
		}

		return json_encode([($data['owner'] ?? null), ($data['portfolio'] ?? null)]) !== json_encode([($oldData['owner'] ?? null), ($oldData['portfolio'] ?? null)]);
	}//end financeCopiesChanged()
}//end class
