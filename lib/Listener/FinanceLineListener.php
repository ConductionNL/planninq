<?php

/**
 * Planninq FinanceLineListener
 *
 * Keeps a project's money to the people who may see and change it.
 *
 * WHO WRITES WHAT
 * ---------------
 * A line entered by hand belongs to a project, and only the project owner or
 * an admin writes it (there is no manager role yet, so the owner is the
 * project's manager). A line from the finance system (`source: import`) is
 * written by the integration app's account in the `planninq-finance-import`
 * group, or an admin, and by nobody else: the next import would overwrite a
 * hand edit (design, risks). OpenRegister cannot express either rule, because
 * a create is checked before the object exists and no rule reads `source`.
 *
 * WHAT IT COPIES
 * --------------
 * On every create and update it copies the project's owner, portfolio and
 * portfolio managers onto the line (FinanceLineService), resolves the project
 * of an imported line from its project number, and refuses a second imported
 * line with the same finance line id, naming the line that holds it so the
 * integration can update that one instead.
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
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Guards finance line writes and keeps their access copies.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
 */
class FinanceLineListener implements IEventListener {

	/**
	 * Error code of a manual line write by someone who is not the project owner.
	 *
	 * @var string
	 */
	public const ERROR_NOT_OWNER = 'planninq-not-the-project-owner';

	/**
	 * Error code of a write to a line from the finance system by anyone but the import.
	 *
	 * @var string
	 */
	public const ERROR_IMPORTED = 'planninq-finance-line-imported';

	/**
	 * Error code of a second imported line with a finance line id already held.
	 *
	 * @var string
	 */
	public const ERROR_DUPLICATE = 'planninq-finance-line-exists';

	/**
	 * Constructor.
	 *
	 * @param FinanceLineService       $finance       Access copies, project matching and duplicates.
	 * @param ProjectMembershipService $membership    Resolves a line's project and its owner.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq schema from any other object.
	 * @param IUserSession             $userSession   The caller.
	 * @param IGroupManager            $groupManager  Tells an admin and the import group.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private FinanceLineService $finance,
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Guard and stamp a pre-save or pre-delete event on a finance line.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
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
	 * Send a finance line to the delete guard or the write guard.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param object                                                      $object  The line.
	 * @param array<string,mixed>|null                                    $oldData The stored line on an update.
	 *
	 * @return void
	 */
	private function route(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, object $object, ?array $oldData): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== FinanceLineService::SCHEMA) {
			return;
		}

		$data = (array)$object->getObject();
		if ($event instanceof ObjectDeletingEvent === true) {
			$this->guard(event: $event, data: $data, oldData: null);
			return;
		}

		if ($oldData !== null && $this->withoutKept(data: $data) === $this->withoutKept(data: $oldData)) {
			// Only the kept copies changed: the sync after a project change.
			return;
		}

		$data = $this->resolveProject(event: $event, data: $data);
		if ($this->guard(event: $event, data: $data, oldData: $oldData) === false) {
			return;
		}

		if ($this->refuseDuplicate(event: $event, data: $data, ownUuid: (string)($object->getUuid() ?? '')) === true) {
			return;
		}

		$projectId = $this->membership->projectIdFor(schemaSlug: FinanceLineService::SCHEMA, data: $data);
		$kept      = $this->finance->keptFieldsFor(projectId: $projectId);
		if ($this->finance->inStep(data: $data, kept: $kept) === false) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $kept));
		}
	}//end route()

	/**
	 * Refuse the write when the caller may not make it.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param array<string,mixed>                                         $data    The line's new data, or the line being deleted.
	 * @param array<string,mixed>|null                                    $oldData The stored line on an update.
	 *
	 * @return bool True when the write may go ahead.
	 */
	private function guard(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, array $data, ?array $oldData): bool {
		$imported = ($this->isImported(data: $data) === true || ($oldData !== null && $this->isImported(data: $oldData) === true));
		if ($imported === true) {
			if ($this->callerImports() === true) {
				return true;
			}

			return $this->refuse(
				event: $event,
				code: self::ERROR_IMPORTED,
				message: 'Lines from the finance system are changed by the finance system only.',
				data: $data
			);
		}

		$projectId  = $this->membership->projectIdFor(schemaSlug: FinanceLineService::SCHEMA, data: $data);
		$oldProject = '';
		if ($oldData !== null) {
			$oldProject = $this->membership->projectIdFor(schemaSlug: FinanceLineService::SCHEMA, data: $oldData);
		}

		if ($this->mayWrite(projectId: $projectId) === true && ($oldProject === '' || $this->mayWrite(projectId: $oldProject) === true)) {
			return true;
		}

		return $this->refuse(
			event: $event,
			code: self::ERROR_NOT_OWNER,
			message: 'Only the project owner can change the money of this project.',
			data: $data
		);
	}//end guard()

	/**
	 * Fill in the project of an imported line from its project number.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The event.
	 * @param array<string,mixed>                     $data  The line's new data.
	 *
	 * @return array<string,mixed> The data with the resolved project, if any.
	 */
	private function resolveProject(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $data): array {
		if ($this->isImported(data: $data) === false
			|| $this->membership->projectIdFor(schemaSlug: FinanceLineService::SCHEMA, data: $data) !== ''
		) {
			return $data;
		}

		$projectId = $this->finance->projectIdForKey(projectKey: (string)($data['projectKey'] ?? ''));
		if ($projectId === '') {
			return $data;
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), ['project' => $projectId]));
		$data['project'] = $projectId;

		return $data;
	}//end resolveProject()

	/**
	 * Refuse a second imported line with a finance line id another line holds.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event   The event.
	 * @param array<string,mixed>                     $data    The line's new data.
	 * @param string                                  $ownUuid The line's own UUID.
	 *
	 * @return bool True when the write was refused.
	 */
	private function refuseDuplicate(ObjectCreatingEvent|ObjectUpdatingEvent $event, array $data, string $ownUuid): bool {
		if ($this->isImported(data: $data) === false) {
			return false;
		}

		$other = $this->finance->importedLineWith(externalRef: trim((string)($data['externalRef'] ?? '')), ownUuid: $ownUuid);
		if ($other === '') {
			return false;
		}

		$this->refuse(
			event: $event,
			code: self::ERROR_DUPLICATE,
			message: 'A line with this finance line id already exists. Update that line instead.',
			data: $data + ['conflictingObject' => $other]
		);

		return true;
	}//end refuseDuplicate()

	/**
	 * Stop the event with an error a client can branch on.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event   The event.
	 * @param string                                                      $code    The error code.
	 * @param string                                                      $message The message.
	 * @param array<string,mixed>                                         $data    The line.
	 *
	 * @return bool Always false.
	 */
	private function refuse(ObjectCreatingEvent|ObjectUpdatingEvent|ObjectDeletingEvent $event, string $code, string $message, array $data): bool {
		$projectId = $this->membership->projectIdFor(schemaSlug: FinanceLineService::SCHEMA, data: $data);
		$error     = ['message' => $message, 'code' => $code, 'project' => $projectId];
		if (isset($data['conflictingObject']) === true) {
			$error['conflictingObject'] = $data['conflictingObject'];
		}

		$event->setErrors($error);
		$event->stopPropagation();
		$this->logger->info('Planninq: refused a finance line write', ['code' => $code, 'project' => $error['project']]);

		return false;
	}//end refuse()

	/**
	 * Whether the caller writes lines from the finance system.
	 *
	 * @return bool
	 */
	private function callerImports(): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->finance->isSystemOperation() === true) {
			return true;
		}

		$uid = $user->getUID();

		return ($this->groupManager->isAdmin($uid) === true || $this->groupManager->isInGroup($uid, FinanceLineService::IMPORT_GROUP) === true);
	}//end callerImports()

	/**
	 * Whether the caller may write the hand-entered money of this project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return bool
	 */
	private function mayWrite(string $projectId): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->finance->isSystemOperation() === true) {
			// No session (occ, cron, a repair step) or an OpenRegister system import.
			return true;
		}

		$uid = $user->getUID();
		if ($this->groupManager->isAdmin($uid) === true) {
			return true;
		}

		return ($projectId !== '' && $this->membership->ownerOfProject(projectId: $projectId) === $uid);
	}//end mayWrite()

	/**
	 * Whether a line came from the finance system.
	 *
	 * @param array<string,mixed> $data The line.
	 *
	 * @return bool
	 */
	private function isImported(array $data): bool {
		return (($data['source'] ?? '') === FinanceLineService::SOURCE_IMPORT);
	}//end isImported()

	/**
	 * A line without the kept copies and metadata, key order ignored.
	 *
	 * @param array<string,mixed> $data The line.
	 *
	 * @return array<string,mixed>
	 */
	private function withoutKept(array $data): array {
		foreach (FinanceLineService::KEPT_FIELDS as $field) {
			unset($data[$field]);
		}

		unset($data['@self'], $data['id']);
		ksort($data);

		return $data;
	}//end withoutKept()
}//end class
