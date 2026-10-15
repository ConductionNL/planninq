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

		$from = ((array)$old->getObject())['status'] ?? null;
		$to   = ((array)$new->getObject())['status'] ?? null;
		if ($from !== 'requested' || in_array($to, ['active', 'rejected'], true) === false) {
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->stampReview(event: $event);
			return;
		}

		if ($to === 'active') {
			$this->createBoard(projectId: (string)($new->getUuid() ?? ''));
		}
	}//end handle()

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
