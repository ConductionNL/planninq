<?php

/**
 * Planninq ProjectReviewListener
 *
 * A project request is reviewed by moving it from `requested` to `active`
 * (approve) or `rejected` (reject). In that save this listener records who
 * reviewed it and when, whatever the client sent. After an approval it
 * creates the project's default board columns, as a project created directly
 * gets them; it does so as the system, because the reviewer is not the
 * project's owner and the column guard keeps columns to the owner.
 *
 * The review (`reviewedBy`, `reviewedAt`, `reviewNote`) and the case handover
 * record (`caseHandovers`) are audit fields: on every other project update
 * by a signed-in caller the stored values are kept, whatever was sent. Only
 * the reviewing save may carry a note, and only CaseHandoverService, which
 * writes as the system, adds a handover.
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
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\BoardColumnService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Records reviews of project requests and gives approved ones their board.
 *
 * @template-implements IEventListener<Event>
 */
class ProjectReviewListener implements IEventListener {

	/**
	 * OpenRegister's system-operation scope.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * The review fields, written only in the reviewing save.
	 *
	 * @var array<int,string>
	 */
	private const REVIEW_FIELDS = ['reviewedBy', 'reviewedAt', 'reviewNote'];

	/**
	 * The audit fields only the system writes.
	 *
	 * @var array<int,string>
	 */
	private const SYSTEM_FIELDS = ['caseHandovers'];

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolver  $scopeResolver Tells a planninq schema from any other object.
	 * @param BoardColumnService $columns       Creates the default columns.
	 * @param IUserSession       $userSession   The reviewer.
	 * @param ITimeFactory       $timeFactory   The review time.
	 * @param LoggerInterface    $logger        The logger.
	 */
	public function __construct(
		private TaskScopeResolver $scopeResolver,
		private BoardColumnService $columns,
		private IUserSession $userSession,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Stamp a review before the save; create the board after an approval.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectUpdatingEvent === false && $event instanceof ObjectUpdatedEvent === false) {
			return;
		}

		$new = $event->getNewObject();
		$old = $event->getOldObject();
		if ($old === null || $this->isProject(object: $new) === false) {
			return;
		}

		$to        = ((array)$new->getObject())['status'] ?? null;
		$reviewing = $this->isReview(from: (((array)$old->getObject())['status'] ?? null), to: $to);

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->keepAuditFields(event: $event, reviewing: $reviewing);
			if ($reviewing === true) {
				$this->stampReview(event: $event);
			}

			return;
		}

		if ($reviewing === true && $to === 'active') {
			$this->createBoard(projectId: (string)($new->getUuid() ?? ''));
		}
	}//end handle()

	/**
	 * Whether a status change reviews a project request: approves or rejects it.
	 *
	 * @param mixed $from The stored status.
	 * @param mixed $to   The new status.
	 *
	 * @return bool
	 */
	private function isReview(mixed $from, mixed $to): bool {
		return ($from === 'requested' && in_array($to, ['active', 'rejected'], true) === true);
	}//end isReview()

	/**
	 * Whether the object is a planninq project.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isProject(object $object): bool {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === 'project';
	}//end isProject()

	/**
	 * Put back the stored audit fields a signed-in caller may not change.
	 *
	 * @param ObjectUpdatingEvent $event     The event.
	 * @param bool                $reviewing Whether this save reviews a request.
	 *
	 * @return void
	 */
	private function keepAuditFields(ObjectUpdatingEvent $event, bool $reviewing): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if ($this->userSession->getUser() === null || (class_exists($class) === true && $class::isActive() === true)) {
			return;
		}

		$fields = self::SYSTEM_FIELDS;
		if ($reviewing === false) {
			$fields = array_merge($fields, self::REVIEW_FIELDS);
		}

		$stored  = (array)$event->getOldObject()?->getObject();
		$sent    = (array)$event->getNewObject()->getObject();
		$changes = [];
		foreach ($fields as $field) {
			if (($sent[$field] ?? null) !== ($stored[$field] ?? null)) {
				$changes[$field] = ($stored[$field] ?? null);
			}
		}

		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end keepAuditFields()

	/**
	 * Record the reviewer and the time in the reviewing save.
	 *
	 * @param ObjectUpdatingEvent $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	private function stampReview(ObjectUpdatingEvent $event): void {
		$event->setModifiedData(
			array_merge(
				$event->getModifiedData(),
				[
					'reviewedBy' => ($this->userSession->getUser()?->getUID() ?? null),
					'reviewedAt' => $this->timeFactory->getDateTime()->format(\DateTimeInterface::ATOM),
				]
			)
		);
	}//end stampReview()

	/**
	 * Create the default columns of an approved project, as the system.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	private function createBoard(string $projectId): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if ($projectId === '' || class_exists($class) === false) {
			$this->logger->warning('Planninq: approved project got no board columns', ['project' => $projectId]);
			return;
		}

		$columns = $this->columns;
		$class::run(static fn () => $columns->createDefaultColumns(projectId: $projectId));
	}//end createBoard()
}//end class
