<?php

/**
 * Unit tests for the three timetable query/command events.
 *
 * Pins the carried request fields, the contract versions and the answer
 * bookkeeping — above all the mutual exclusion in the sessions-query event,
 * where an answer clears a prior error and an error clears a prior answer, and
 * the re-indexing the activities-query event does to its lists.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Event
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Event;

use OCA\Planninq\Event\TimetableActivitiesQueryEvent;
use OCA\Planninq\Event\TimetableSessionsQueryEvent;
use OCA\Planninq\Event\TimetableUpsertRequestedEvent;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the timetable events.
 *
 * @covers \OCA\Planninq\Event\TimetableActivitiesQueryEvent
 * @covers \OCA\Planninq\Event\TimetableSessionsQueryEvent
 * @covers \OCA\Planninq\Event\TimetableUpsertRequestedEvent
 */
class TimetableEventsTest extends TestCase {

	/**
	 * The sessions-query event carries the request and starts unanswered.
	 *
	 * @return void
	 */
	public function testSessionsQueryCarriesTheRequestAndStartsUnanswered(): void {
		$event = new TimetableSessionsQueryEvent(sourceApp: 'pipelinq', criteria: ['cohort' => 'c-1']);

		$this->assertInstanceOf(Event::class, $event);
		$this->assertSame('pipelinq', $event->getSourceApp());
		$this->assertSame(['cohort' => 'c-1'], $event->getCriteria());
		$this->assertNull($event->getSessions());
		$this->assertNull($event->getError());
		$this->assertFalse($event->isHandled());
		$this->assertSame(2, TimetableSessionsQueryEvent::CONTRACT_VERSION);
	}//end testSessionsQueryCarriesTheRequestAndStartsUnanswered()

	/**
	 * An answer and an error each clear the other, and either marks the event handled.
	 *
	 * @return void
	 */
	public function testSessionsQueryAnswerAndErrorAreMutuallyExclusive(): void {
		$event = new TimetableSessionsQueryEvent(sourceApp: 'pipelinq', criteria: []);

		$event->setSessions([['id' => 's-1']]);
		$this->assertSame([['id' => 's-1']], $event->getSessions());
		$this->assertNull($event->getError());
		$this->assertTrue($event->isHandled());

		$event->setError('OpenRegister is not available.');
		$this->assertSame('OpenRegister is not available.', $event->getError());
		$this->assertNull($event->getSessions());
		$this->assertTrue($event->isHandled());

		$event->setSessions([]);
		$this->assertSame([], $event->getSessions());
		$this->assertNull($event->getError());
		$this->assertTrue($event->isHandled());
	}//end testSessionsQueryAnswerAndErrorAreMutuallyExclusive()

	/**
	 * The activities-query event carries the year and starts unanswered.
	 *
	 * @return void
	 */
	public function testActivitiesQueryStartsUnanswered(): void {
		$event = new TimetableActivitiesQueryEvent(academicYear: '2026-2027');

		$this->assertSame('2026-2027', $event->getAcademicYear());
		$this->assertNull($event->getActivities());
		$this->assertSame([], $event->getRooms());
		$this->assertNull($event->getAnsweredBy());
		$this->assertSame(1, TimetableActivitiesQueryEvent::CONTRACT_VERSION);
	}//end testActivitiesQueryStartsUnanswered()

	/**
	 * answer() records who answered and re-indexes the activity and room lists.
	 *
	 * @return void
	 */
	public function testActivitiesQueryAnswerReindexesTheLists(): void {
		$event = new TimetableActivitiesQueryEvent(academicYear: '2026-2027');

		$event->answer(
			app: 'roostermaker',
			activities: [3 => ['id' => 'a'], 9 => ['id' => 'b']],
			rooms: ['x' => ['id' => 'r1']]
		);

		$this->assertSame('roostermaker', $event->getAnsweredBy());
		$this->assertSame([['id' => 'a'], ['id' => 'b']], $event->getActivities());
		$this->assertSame([['id' => 'r1']], $event->getRooms());
	}//end testActivitiesQueryAnswerReindexesTheLists()

	/**
	 * The upsert event carries the request, defaults the correlation id and starts unhandled.
	 *
	 * @return void
	 */
	public function testUpsertCarriesTheRequestAndDefaultsTheCorrelationId(): void {
		$sessions = [['externalRef' => 'zm-1']];
		$event    = new TimetableUpsertRequestedEvent(sourceApp: 'pipelinq', sourceSystem: 'roster-zermelo', sessions: $sessions);

		$this->assertSame('pipelinq', $event->getSourceApp());
		$this->assertSame('roster-zermelo', $event->getSourceSystem());
		$this->assertSame($sessions, $event->getSessions());
		$this->assertSame('', $event->getCorrelationId());
		$this->assertNull($event->getResult());
		$this->assertFalse($event->isHandled());
		$this->assertSame(1, TimetableUpsertRequestedEvent::CONTRACT_VERSION);
	}//end testUpsertCarriesTheRequestAndDefaultsTheCorrelationId()

	/**
	 * A set correlation id is kept, and a result marks the event handled.
	 *
	 * @return void
	 */
	public function testUpsertKeepsTheCorrelationIdAndRecordsTheResult(): void {
		$event = new TimetableUpsertRequestedEvent(
			sourceApp: 'pipelinq',
			sourceSystem: 'roster-zermelo',
			sessions: [],
			correlationId: 'corr-7'
		);

		$this->assertSame('corr-7', $event->getCorrelationId());

		$event->setResult(['created' => 2, 'updated' => 1]);
		$this->assertSame(['created' => 2, 'updated' => 1], $event->getResult());
		$this->assertTrue($event->isHandled());
	}//end testUpsertKeepsTheCorrelationIdAndRecordsTheResult()
}//end class
