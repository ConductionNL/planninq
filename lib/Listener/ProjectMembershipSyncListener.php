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
 * @spec openspec/specs/projects/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\TaskReportReaderService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Copies a project's changed membership to the objects of that project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/projects/spec.md
 */
class ProjectMembershipSyncListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Computes and writes the members list.
	 * @param FinanceLineService $finance Keeps the access copies on the project's finance lines.
	 * @param TaskScopeResolver $scopeResolver Tells whether an object is a planninq project.
	 * @param LoggerInterface $logger The logger.
	 * @param TaskReportReaderService $reports Keeps who reads the shared reports of the project.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private FinanceLineService $finance,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
		private TaskReportReaderService $reports,
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
	 * @spec openspec/specs/projects/spec.md
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectUpdatedEvent === false) {
			return;
		}

		try {
			$change = $this->projectChange(event: $event);
			if ($change !== null) {
				$this->sync(change: $change);
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
	 * The project change of an update: its uuid, the saved data and the stored data, or null for another object.
	 *
	 * @param ObjectUpdatedEvent $event The post-update event.
	 *
	 * @return array{id:string,data:array<string,mixed>,old:array<string,mixed>|null}|null
	 */
	private function projectChange(ObjectUpdatedEvent $event): ?array {
		$project    = $event->getNewObject();
		$schemaSlug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($project->getRegister() ?? ''),
			schemaId: (string)($project->getSchema() ?? '')
		);
		if ($schemaSlug !== ProjectMembershipService::PROJECT_SCHEMA) {
			return null;
		}

		$old     = $event->getOldObject();
		$oldData = null;
		if ($old !== null) {
			$oldData = (array)$old->getObject();
		}

		return ['id' => (string)($project->getUuid() ?? ''), 'data' => (array)$project->getObject(), 'old' => $oldData];
	}//end projectChange()

	/**
	 * Copy the changed role and reader lists, then the shared reports and the finance lines.
	 *
	 * @param array{id:string,data:array<string,mixed>,old:array<string,mixed>|null} $change The project change.
	 *
	 * @return void
	 */
	private function sync(array $change): void {
		$rolesChanged   = $this->syncRoleLists(change: $change);
		$readersChanged = $this->syncReaders(change: $change);
		if ($rolesChanged === true || $readersChanged === true) {
			$this->reports->syncProject(projectId: $change['id']);
		}

		$this->syncFinance(projectId: $change['id'], data: $change['data'], oldData: $change['old']);
	}//end sync()

	/**
	 * Copy the portfolio readers to every object of the project when they changed.
	 *
	 * @param array{id:string,data:array<string,mixed>,old:array<string,mixed>|null} $change The project change.
	 *
	 * @return bool Whether they changed.
	 */
	private function syncReaders(array $change): bool {
		$field   = ProjectMembershipService::READERS_FIELD;
		$readers = $this->membership->normalise(members: ($change['data'][$field] ?? []));
		if ($change['old'] !== null && $this->membership->normalise(members: ($change['old'][$field] ?? [])) === $readers) {
			return false;
		}

		$this->membership->syncProjectMembers(projectId: $change['id'], members: $readers, field: $field);

		return true;
	}//end syncReaders()

	/**
	 * Copy each role list that changed (members, viewers, member and viewer
	 * groups) to every object of the project.
	 *
	 * @param array{id:string,data:array<string,mixed>,old:array<string,mixed>|null} $change The project change.
	 *
	 * @return bool Whether a list changed.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	private function syncRoleLists(array $change): bool {
		$changed = false;
		$before  = [];
		if ($change['old'] !== null) {
			$before = $this->membership->childLists(project: $change['old']);
		}

		foreach ($this->membership->childLists(project: $change['data']) as $field => $values) {
			if ($change['old'] !== null && $before[$field] === $values) {
				continue;
			}

			$changed = true;
			$written = $this->membership->syncProjectMembers(projectId: $change['id'], members: $values, field: $field);
			$this->logger->info(
				'Planninq: project roles changed; a role list updated on its objects',
				['project' => $change['id'], 'field' => $field, 'written' => $written]
			);
		}

		return $changed;
	}//end syncRoleLists()

	/**
	 * Copy the owner, portfolio and portfolio managers onto the project's finance lines when they changed.
	 *
	 * @param string                   $projectId The project UUID.
	 * @param array<string,mixed>      $data      The saved project.
	 * @param array<string,mixed>|null $oldData   The stored project before the change.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	private function syncFinance(string $projectId, array $data, ?array $oldData): void {
		if ($oldData === null || $this->financeCopiesChanged(data: $data, oldData: $oldData) === true) {
			$this->finance->syncProject(projectId: $projectId);
		}
	}//end syncFinance()

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

		$now    = json_encode([($data['owner'] ?? null), ($data['portfolio'] ?? null)]);
		$before = json_encode([($oldData['owner'] ?? null), ($oldData['portfolio'] ?? null)]);

		return $now !== $before;
	}//end financeCopiesChanged()
}//end class
