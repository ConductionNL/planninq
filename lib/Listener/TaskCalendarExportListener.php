<?php

/**
 * Planninq TaskCalendarExportListener
 *
 * Keeps the "Planninq" task lists in step with planninq (planning-calendar):
 * on every planninq task change it rewrites the whole VTODO for each person on
 * the task who switched the export on, removes it for anyone who left the
 * task, and removes it everywhere on delete. It never throws into
 * OpenRegister's save: a failed export is logged, and the next change to the
 * task rewrites the VTODO in full.
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
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Writes and removes task VTODOs on OpenRegister's task events.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
 */
class TaskCalendarExportListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param TaskScopeResolver         $scopeResolver Tells planninq tasks apart.
	 * @param TaskCalendarExportService $export        Writes the VTODOs.
	 * @param LoggerInterface           $logger        Diagnostics.
	 */
	public function __construct(
		private TaskScopeResolver $scopeResolver,
		private TaskCalendarExportService $export,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a created, updated or deleted object.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/planning-calendar/tasks.md#task-2.3
	 */
	public function handle(Event $event): void {
		try {
			if ($event instanceof ObjectCreatedEvent === true) {
				$this->onChange(object: $event->getObject(), old: null);
				return;
			}

			if ($event instanceof ObjectUpdatedEvent === true) {
				$old = null;
				if ($event->getOldObject() !== null) {
					$old = $event->getOldObject()->getObject();
				}

				$this->onChange(object: $event->getNewObject(), old: $old);
				return;
			}

			if ($event instanceof ObjectDeletedEvent === true) {
				$this->onDelete(object: $event->getObject());
			}
		} catch (\Throwable $e) {
			// OpenRegister's save must never fail because the export did.
			$this->logger->warning('Planninq: the task calendar export skipped an event', ['exception' => $e->getMessage()]);
		}//end try
	}//end handle()

	/**
	 * Rewrite the VTODO for the people on the task, remove it for the people who left.
	 *
	 * @param object                   $object The task object.
	 * @param array<string,mixed>|null $old    The task data before, or null on create.
	 *
	 * @return void
	 */
	private function onChange(object $object, ?array $old): void {
		$data = $this->taskData(object: $object);
		if ($data === null) {
			return;
		}

		if ($old !== null && $this->onlyUidChanged(old: $old, new: $data) === true) {
			return;
		}

		$taskId = (string)($object->getUuid() ?? '');
		$now    = $this->export->recipients(task: $data);
		if ($old !== null) {
			$left = array_values(array_diff($this->export->recipients(task: $old), $now));
			if ($left !== []) {
				$this->export->removeTask(taskId: $taskId, users: $left);
			}
		}

		$this->export->exportTask(taskId: $taskId, task: $data, users: $now);
	}//end onChange()

	/**
	 * Remove a deleted task's VTODO from every list that holds it.
	 *
	 * @param object $object The task object.
	 *
	 * @return void
	 */
	private function onDelete(object $object): void {
		$data = $this->taskData(object: $object);
		if ($data === null) {
			return;
		}

		$this->export->removeTask(taskId: (string)($object->getUuid() ?? ''), users: $this->export->recipients(task: $data));
	}//end onDelete()

	/**
	 * The task's data, or null when the object is not a planninq task.
	 *
	 * @param object $object The object.
	 *
	 * @return array<string,mixed>|null
	 */
	private function taskData(object $object): ?array {
		$registerId = (string)($object->getRegister() ?? '');
		$schemaId   = (string)($object->getSchema() ?? '');
		if ($this->scopeResolver->isPlanninqTask(registerId: $registerId, schemaId: $schemaId) === false) {
			return null;
		}

		$data = $object->getObject();
		if (is_array($data) === false) {
			return null;
		}

		return $data;
	}//end taskData()

	/**
	 * Whether an update changed nothing but calendarEventUid (the export's own write-back).
	 *
	 * @param array<string,mixed> $old The data before.
	 * @param array<string,mixed> $new The data after.
	 *
	 * @return bool
	 */
	private function onlyUidChanged(array $old, array $new): bool {
		if (($old['calendarEventUid'] ?? null) === ($new['calendarEventUid'] ?? null)) {
			return false;
		}

		unset($old['calendarEventUid'], $new['calendarEventUid'], $old['@self'], $new['@self']);

		return $this->canonical(value: $old) === $this->canonical(value: $new);
	}//end onlyUidChanged()

	/**
	 * A value as JSON with its object keys sorted, so key order does not count as a change.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 */
	private function canonical(mixed $value): string {
		$sort = static function (mixed $item) use (&$sort): mixed {
			if (is_array($item) === false) {
				return $item;
			}

			if (array_is_list($item) === false) {
				ksort($item);
			}

			return array_map($sort, $item);
		};

		return (string)json_encode($sort($value));
	}//end canonical()
}//end class
