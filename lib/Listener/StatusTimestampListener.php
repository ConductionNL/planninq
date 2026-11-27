<?php

/**
 * Planninq StatusTimestampListener
 *
 * The shared mechanics of a listener that stamps a timestamp field on an
 * object in the same save that moves its status into a set of statuses, and
 * clears it in the save that moves the status out again. It runs on
 * OpenRegister's pre-save events, so every client that changes the status
 * dates it the same way.
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
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
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
 * Keeps a timestamp field in step with an object's status: set when the
 * status enters the stamped set, kept while it moves inside that set,
 * cleared when it leaves it.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
 */
abstract class StatusTimestampListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolverInterface $scopeResolver Tells a planninq object's schema slug.
	 * @param ITimeFactory               $timeFactory   The clock.
	 */
	public function __construct(
		private TaskScopeResolverInterface $scopeResolver,
		private ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * The planninq schema slug whose objects this listener stamps.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
	 */
	abstract protected function schemaSlug(): string;

	/**
	 * The field that holds the timestamp.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
	 */
	abstract protected function timestampField(): string;

	/**
	 * The statuses in which the timestamp is set.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
	 */
	abstract protected function stampedStatuses(): array;

	/**
	 * Stamp or clear the timestamp when a save moves the status across the stamped set.
	 *
	 * @param Event $event The pre-save event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-1.1
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
	 * Merge the timestamp into the save when the status crosses the stamped set.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The pre-save event.
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
		if ($slug !== $this->schemaSlug()) {
			return;
		}

		$newStatus = (((array)$object->getObject())['status'] ?? null);
		$wasIn     = in_array($oldStatus, $this->stampedStatuses(), true);
		$isIn      = in_array($newStatus, $this->stampedStatuses(), true);
		if ($wasIn === $isIn) {
			return;
		}

		$stamp = null;
		if ($isIn === true) {
			$stamp = $this->timeFactory->getDateTime()->format(\DateTimeInterface::ATOM);
		}

		$event->setModifiedData(array_merge($event->getModifiedData(), [$this->timestampField() => $stamp]));
	}//end apply()
}//end class
