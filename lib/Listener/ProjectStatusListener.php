<?php

/**
 * Planninq ProjectStatusListener
 *
 * Keeps a project's `health` fields equal to its newest status report, and
 * keeps status reports to the project owner.
 *
 * WHY PRE-EVENTS
 * --------------
 * ADR-078 (gate-61) keeps synchronous writes out of post-event listeners. The
 * copy belongs inside the report's save anyway: the listener sees the report's
 * new data on ObjectCreatingEvent and ObjectUpdatingEvent and the report that
 * is leaving on ObjectDeletingEvent, so it can work out the newest report
 * without the row being stored yet. It runs after its own owner check, so a
 * refused report is never copied.
 *
 * WHY A PROJECT GUARD
 * -------------------
 * No person writes the health fields (design decision 1, risk 1). A project
 * create or update from a signed-in caller keeps the stored values; only
 * OpenRegister's system-operation scope, which ProjectHealthService wraps its
 * copy in, may change them. An update is corrected, not refused, because the
 * project settings form sends the whole project back, health included.
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
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectHealthService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Copies the newest status report onto its project and guards who writes reports and health.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
 */
class ProjectStatusListener implements IEventListener {

	/**
	 * Error code of a refused report write.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'planninq-not-the-project-owner';

	/**
	 * Constructor.
	 *
	 * @param ProjectHealthService     $health        Works out and writes the project's health.
	 * @param ProjectMembershipService $membership    Resolves a report's project and its owner.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq schema from any other object.
	 * @param IUserSession             $userSession   The caller.
	 * @param IGroupManager            $groupManager  Tells an admin.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private ProjectHealthService $health,
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Guard and copy on a pre-save or pre-delete event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true || $event instanceof ObjectDeletingEvent === true) {
			$this->route(event: $event, object: $event->getObject(), oldData: null);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$old     = $event->getOldObject();
			$oldData = [];
			if ($old !== null) {
				$oldData = (array)$old->getObject();
			}

			$this->route(event: $event, object: $event->getNewObject(), oldData: $oldData);
		}
	}//end handle()

	/**
	 * Send the event to the report handler or the project guard.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param object                                                      $object  The object being written or deleted.
	 * @param array<string,mixed>|null                                    $oldData The stored data on an update.
	 *
	 * @return void
	 */
	private function route(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);

		if ($slug === ProjectMembershipService::PROJECT_SCHEMA && $event instanceof ObjectDeletingEvent === false) {
			$this->guardHealth(event: $event, data: (array)$object->getObject(), oldData: $oldData);
			return;
		}

		if ($slug === ProjectHealthService::REPORT_SCHEMA) {
			$this->onReport(event: $event, object: $object, oldData: $oldData);
		}
	}//end route()

	/**
	 * Keep the stored health on a project written by anyone but the system.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event   The event.
	 * @param array<string,mixed>                     $data    The project's new data.
	 * @param array<string,mixed>|null                $oldData The stored project, null on a create.
	 *
	 * @return void
	 */
	private function guardHealth(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $data, ?array $oldData): void {
		if ($this->health->isSystemOperation() === true) {
			return;
		}

		$stored = $this->health->healthOf(project: ($oldData ?? []));
		if ($this->health->healthOf(project: $data) === $stored) {
			return;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), $stored));
	}//end guardHealth()

	/**
	 * Refuse a report write by anyone but the owner, else copy the newest report onto the project.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param object                                                      $object  The report.
	 * @param array<string,mixed>|null                                    $oldData The stored report on an update.
	 *
	 * @return void
	 */
	private function onReport(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$data = (array)$object->getObject();
		if ($oldData !== null && $this->withoutMembers(data: $data) === $this->withoutMembers(data: $oldData)) {
			// A members-only rewrite by the membership sync: nothing to check or copy.
			return;
		}

		$projectId = $this->membership->projectIdFor(schemaSlug: ProjectHealthService::REPORT_SCHEMA, data: $data);
		$oldProject = '';
		if ($oldData !== null) {
			$oldProject = $this->membership->projectIdFor(schemaSlug: ProjectHealthService::REPORT_SCHEMA, data: $oldData);
		}

		if ($this->mayWriteBoth(projectId: $projectId, oldProject: $oldProject) === false) {
			$event->setErrors(
				[
					'message' => 'Only the project owner can write a status report.',
					'code'    => self::ERROR_CODE,
					'project' => $projectId,
				]
			);
			$event->stopPropagation();
			$this->logger->info('Planninq: refused a status report by someone who is not the project owner', ['project' => $projectId]);
			return;
		}

		$saving = $data;
		if ($event instanceof ObjectDeletingEvent === true) {
			$saving = null;
		}

		$this->copy(reportId: (string)($object->getUuid() ?? ''), projectId: $projectId, oldProject: $oldProject, saving: $saving);
	}//end onReport()

	/**
	 * Copy the newest report onto the report's project, and onto the project it left.
	 *
	 * @param string                   $reportId   The report UUID.
	 * @param string                   $projectId  The report's project.
	 * @param string                   $oldProject The project it was in before this update, or ''.
	 * @param array<string,mixed>|null $saving     The report's new data, null when it is being deleted.
	 *
	 * @return void
	 */
	private function copy(string $reportId, string $projectId, string $oldProject, ?array $saving): void {
		$this->health->refresh(projectId: $projectId, reportId: $reportId, saving: $saving);
		if ($oldProject !== '' && $oldProject !== $projectId) {
			$this->health->refresh(projectId: $oldProject, reportId: $reportId, saving: null);
		}
	}//end copy()

	/**
	 * Whether the caller may write reports of the report's project and of the project it leaves.
	 *
	 * @param string $projectId  The report's project.
	 * @param string $oldProject The project it was in before this update, or ''.
	 *
	 * @return bool
	 */
	private function mayWriteBoth(string $projectId, string $oldProject): bool {
		if ($this->mayWrite(projectId: $projectId) === false) {
			return false;
		}

		return ($oldProject === '' || $this->mayWrite(projectId: $oldProject) === true);
	}//end mayWriteBoth()

	/**
	 * Whether the caller may write status reports of this project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return bool
	 */
	private function mayWrite(string $projectId): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->health->isSystemOperation() === true) {
			// No session (occ, cron, a repair step) or an OpenRegister system import.
			return true;
		}

		$uid = $user->getUID();
		if ($this->groupManager->isAdmin($uid) === true) {
			return true;
		}

		return ($this->membership->ownerOfProject(projectId: $projectId) === $uid);
	}//end mayWrite()

	/**
	 * A report's data without the planninq-kept members list and metadata, key order ignored.
	 *
	 * @param array<string,mixed> $data The report data.
	 *
	 * @return array<string,mixed>
	 */
	private function withoutMembers(array $data): array {
		unset($data['members'], $data['@self'], $data['id']);
		ksort($data);
		return $data;
	}//end withoutMembers()
}//end class
