<?php

/**
 * Planninq ProjectClosureListener
 *
 * Stamps a project's `closedAt` in the same save that moves its status to
 * `completed` or `archived`, and clears it in the save that reopens it. The
 * project's retention dates from that moment, and its tasks and time entries
 * date from it through their project (retention-on-planninq-records).
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
 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Kernel\Contracts\TaskScopeResolverInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Keeps `closedAt` on a project in step with its status: set when the
 * project closes (completed or archived), kept while it moves between those
 * two, cleared when it reopens. It runs on OpenRegister's pre-save events,
 * so every client that closes a project dates it the same way.
 *
 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 *
 * @template-implements IEventListener<Event>
 */
class ProjectClosureListener implements IEventListener {

	/**
	 * The statuses in which a project is closed.
	 *
	 * @var array<int,string>
	 */
	public const CLOSED_STATUSES = ['completed', 'archived'];

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolverInterface $scopeResolver Tells a planninq project from other objects.
	 * @param ITimeFactory               $timeFactory   The clock.
	 */
	public function __construct(
		private TaskScopeResolverInterface $scopeResolver,
		private ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * Stamp or clear `closedAt` when a save opens or closes a project.
	 *
	 * @param Event $event The pre-save event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-1.1
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
	 * Merge `closedAt` into the save when the project's closed state changes.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The pre-save event.
	 * @param object                                  $object    The object being saved.
	 * @param mixed                                   $oldStatus The status before this save, null on create.
	 *
	 * @return void
	 */
	private function apply(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, mixed $oldStatus): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== 'project') {
			return;
		}

		$newStatus = (((array)$object->getObject())['status'] ?? null);
		$wasClosed = in_array($oldStatus, self::CLOSED_STATUSES, true);
		$isClosed  = in_array($newStatus, self::CLOSED_STATUSES, true);
		if ($wasClosed === $isClosed) {
			return;
		}

		$closedAt = null;
		if ($isClosed === true) {
			$closedAt = $this->timeFactory->getDateTime()->format(\DateTimeInterface::ATOM);
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), ['closedAt' => $closedAt]));
	}//end apply()
}//end class
