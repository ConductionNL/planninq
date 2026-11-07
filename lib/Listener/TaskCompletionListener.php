<?php

/**
 * Planninq TaskCompletionListener
 *
 * Stamps a task's `completedAt` in the same save that moves its status to
 * `done`, and clears it in the save that moves the status away from `done`.
 * It runs on OpenRegister's pre-save events, so the finish time is right
 * whichever client changed the status: the board, the API, a flow or an
 * import. Flow reports and the dashboard's "completed today" read it.
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
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Keeps `completedAt` in step with a task's `done` status.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
 */
class TaskCompletionListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolver $scopeResolver Tells a planninq task from any other object.
	 * @param ITimeFactory      $timeFactory   The clock.
	 */
	public function __construct(
		private TaskScopeResolver $scopeResolver,
		private ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * Handle a pre-save event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2b
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->apply(event: $event, object: $event->getObject(), oldStatus: null);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$old       = $event->getOldObject();
			$oldStatus = null;
			if ($old !== null) {
				$oldStatus = (((array)$old->getObject())['status'] ?? null);
			}

			$this->apply(event: $event, object: $event->getNewObject(), oldStatus: $oldStatus);
		}
	}//end handle()

	/**
	 * Merge `completedAt` into the save when the status crosses `done`.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The event.
	 * @param object                                  $object    The object being saved.
	 * @param mixed                                   $oldStatus The stored status, null on create.
	 *
	 * @return void
	 */
	private function apply(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, mixed $oldStatus): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== 'task') {
			return;
		}

		$newStatus = (((array)$object->getObject())['status'] ?? null);
		$wasDone   = ($oldStatus === 'done');
		$isDone    = ($newStatus === 'done');
		if ($wasDone === $isDone) {
			return;
		}

		$completedAt = null;
		if ($isDone === true) {
			$completedAt = $this->timeFactory->getDateTime()->format(\DateTimeInterface::ATOM);
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), ['completedAt' => $completedAt]));
	}//end apply()
}//end class
