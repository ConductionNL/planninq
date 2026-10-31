<?php

/**
 * Planninq Timetable Sessions Query Listener
 *
 * Answers {@see \OCA\Planninq\Event\TimetableSessionsQueryEvent} with the
 * sessions planninq holds for a cohort, group or teacher, read through
 * planninq's own service (ADR-041, ADR-022). The read runs with OpenRegister
 * RBAC off: only in-process server code can dispatch the event, and the
 * requesting app decides which cohort or teacher its user may see before it
 * asks (planninq#711). A refused query comes back handled with an error, never
 * as an empty list, so a consumer cannot mistake "you asked wrongly" for
 * "there are no lessons".
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
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\Planninq\Event\TimetableSessionsQueryEvent;
use OCA\Planninq\Service\TimetableSessionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads timetable sessions for the requesting app.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */
class TimetableSessionsQueryListener implements IEventListener {

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
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function handle(Event $event): void {
		if (($event instanceof TimetableSessionsQueryEvent) === false) {
			return;
		}

		try {
			$event->setSessions($this->sessions->listForApp(criteria: $event->getCriteria()));
		} catch (Throwable $e) {
			$this->logger->info(
				'[TimetableSessionsQueryListener] Query from {app} refused: {message}',
				['app' => $event->getSourceApp(), 'message' => $e->getMessage()]
			);
			$event->setError($e->getMessage());
		}
	}//end handle()
}//end class
