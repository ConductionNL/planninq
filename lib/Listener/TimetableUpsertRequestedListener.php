<?php

/**
 * Planninq Timetable Upsert Requested Listener
 *
 * Answers {@see \OCA\Planninq\Event\TimetableUpsertRequestedEvent}: runs the
 * upsert through planninq's own service, inside planninq's own DI context
 * (ADR-041), and writes the result back into the event before dispatch
 * returns. Always answers: a batch refused as a whole (no source system,
 * OpenRegister unavailable) still comes back handled, carrying `error`, so a
 * consumer can tell "planninq refused" from "planninq is not installed".
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\Planninq\Event\TimetableUpsertRequestedEvent;
use OCA\Planninq\Service\TimetableSessionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Upserts a delivered timetable batch and answers the requesting app.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004
 */
class TimetableUpsertRequestedListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param TimetableSessionService $sessions The timetable rules.
	 * @param LoggerInterface         $logger   PSR logger.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly TimetableSessionService $sessions,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle the event.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004
	 */
	public function handle(Event $event): void {
		if (($event instanceof TimetableUpsertRequestedEvent) === false) {
			return;
		}

		try {
			$result = $this->sessions->upsert(sourceSystem: $event->getSourceSystem(), sessions: $event->getSessions());
		} catch (Throwable $e) {
			$this->logger->warning(
				'[TimetableUpsertRequestedListener] Batch from {app} ({source}) refused: {message}',
				['app' => $event->getSourceApp(), 'source' => $event->getSourceSystem(), 'message' => $e->getMessage()]
			);
			$result = [
				'contractVersion' => TimetableUpsertRequestedEvent::CONTRACT_VERSION,
				'sourceSystem' => $event->getSourceSystem(),
				'processed' => count($event->getSessions()),
				'created' => 0,
				'updated' => 0,
				'unchanged' => 0,
				'rejected' => [],
				'sessionIds' => [],
				'error' => $e->getMessage(),
			];
		}

		$this->logger->info(
			'[TimetableUpsertRequestedListener] {app} delivered {n} row(s) from {source} ({correlation}): '
				. '{created} created, {updated} updated, {unchanged} unchanged, {rejected} rejected.',
			[
				'app' => $event->getSourceApp(),
				'n' => $result['processed'],
				'source' => $event->getSourceSystem(),
				'correlation' => $event->getCorrelationId(),
				'created' => $result['created'],
				'updated' => $result['updated'],
				'unchanged' => $result['unchanged'],
				'rejected' => count($result['rejected']),
			]
		);

		$event->setResult($result);
	}//end handle()
}//end class
