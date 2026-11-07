<?php

/**
 * Planninq ProjectPrincipalCleanupListener
 *
 * When Nextcloud deletes an account or a group, takes it off every project
 * that lists it, so no project keeps a role for someone who no longer
 * exists. A deleted owner hands the project to the first manager, else the
 * first member (the rule leaveProject uses). The write runs as the system,
 * because no user session exists at that moment, and is not silent: the
 * project update reaches ProjectMembershipSyncListener, which copies the
 * changed lists onto the project's work.
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
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\ProjectRoles;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Removes a deleted account or group from every project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.3
 */
class ProjectPrincipalCleanupListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership The project reader.
	 * @param ProjectRoles             $roles      The project schema's roles.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService.
	 * @param LoggerInterface          $logger     The logger.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.3
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ProjectRoles $roles,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Take the deleted account or group off every project that lists it.
	 *
	 * @param Event $event UserDeletedEvent or GroupDeletedEvent.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.3
	 */
	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$uid = $event->getUser()->getUID();
			$this->cleanUp(
				change: fn (array $project): array => $this->roles->withoutUser(project: $project, uid: $uid),
				principal: 'user ' . $uid
			);
			return;
		}

		if ($event instanceof GroupDeletedEvent) {
			$gid = $event->getGroup()->getGID();
			$this->cleanUp(
				change: fn (array $project): array => $this->roles->withoutGroup(project: $project, gid: $gid),
				principal: 'group ' . $gid
			);
		}
	}//end handle()

	/**
	 * Apply a change to every project it alters, and save those.
	 *
	 * @param callable $change    Maps a project's data to its new data.
	 * @param string   $principal Who was deleted, for the log.
	 *
	 * @return void
	 */
	private function cleanUp(callable $change, string $principal): void {
		if ($this->membership->isAvailable() === false) {
			return;
		}

		$objectService = null;
		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: []) as $row) {
			$updated = $change($row['data']);
			if ($updated === $row['data']) {
				continue;
			}

			$objectService = ($objectService ?? $this->container->get('OCA\\OpenRegister\\Service\\ObjectService'));
			try {
				$objectService->saveObject(
					object: $updated,
					register: ProjectMembershipService::REGISTER,
					schema: ProjectMembershipService::PROJECT_SCHEMA,
					uuid: $row['id'],
					_rbac: false,
					_multitenancy: false
				);
			} catch (\Throwable $e) {
				$this->logger->error(
					'Planninq: could not take a deleted account or group off a project',
					['principal' => $principal, 'project' => $row['id'], 'exception' => $e->getMessage()]
				);
			}
		}//end foreach
	}//end cleanUp()
}//end class
