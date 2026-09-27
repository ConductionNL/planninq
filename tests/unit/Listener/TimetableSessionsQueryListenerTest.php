<?php

/**
 * Unit tests for TimetableSessionsQueryListener.
 *
 * Built on the REAL event class and the REAL service over an in-memory
 * OpenRegister.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Event\TimetableSessionsQueryEvent;
use OCA\Planninq\Event\TimetableUpsertRequestedEvent;
use OCA\Planninq\Listener\TimetableSessionsQueryListener;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Tests the read door another app uses.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */
class TimetableSessionsQueryListenerTest extends TestCase {

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objectService;

	/**
	 * The listener under test.
	 *
	 * @var TimetableSessionsQueryListener
	 */
	private TimetableSessionsQueryListener $listener;

	/**
	 * Build the listener over the real service and an in-memory OpenRegister.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objectService = new InMemoryObjectService();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($this->objectService);

		$this->listener = new TimetableSessionsQueryListener(
			sessions: new TimetableSessionService(container: $container, logger: new NullLogger()),
			logger: new NullLogger()
		);

		foreach ([['s-2', 'zm-2', '2026-09-29T10:00:00+02:00'], ['s-1', 'zm-1', '2026-09-28T09:00:00+02:00'], ['s-9', 'zm-9', '2026-11-02T09:00:00+01:00']] as [$id, $ref, $start]) {
			$this->objectService->seed(
				schema: 'timetableSession',
				id: $id,
				data: [
					'externalRef' => $ref,
					'sourceSystem' => 'roster-zermelo',
					'subject' => 'Biologie',
					'startsAt' => $start,
					'endsAt' => date(DATE_ATOM, ((int)strtotime($start) + 3000)),
					'cohortId' => 'c-1',
					'teacherUserId' => 'klaas',
				]
			);
		}

	}//end setUp()

	/**
	 * A cohort's week is answered in time order.
	 *
	 * @return void
	 */
	public function testCohortWeekIsAnsweredInTimeOrder(): void {
		$event = new TimetableSessionsQueryEvent(
			sourceApp: 'learniq',
			criteria: ['cohortId' => 'c-1', 'from' => '2026-09-28T00:00:00+02:00', 'to' => '2026-10-04T23:59:59+02:00']
		);

		$this->listener->handle($event);

		self::assertTrue(condition: $event->isHandled());
		self::assertNull(actual: $event->getError());
		self::assertSame(expected: ['zm-1', 'zm-2'], actual: array_column($event->getSessions(), 'externalRef'));
		self::assertTrue(condition: end($this->objectService->searches)['rbac'], message: 'reads run with RBAC on');

	}//end testCohortWeekIsAnsweredInTimeOrder()

	/**
	 * A query without an identity filter is refused with an error and no sessions.
	 *
	 * @return void
	 */
	public function testQueryWithoutIdentityIsRefused(): void {
		$event = new TimetableSessionsQueryEvent(sourceApp: 'learniq', criteria: ['from' => '2026-09-28T00:00:00+02:00']);

		$this->listener->handle($event);

		self::assertTrue(condition: $event->isHandled());
		self::assertNull(actual: $event->getSessions());
		self::assertStringContainsString(needle: 'cohortId', haystack: (string)$event->getError());

	}//end testQueryWithoutIdentityIsRefused()

	/**
	 * A teacher with no lessons gets an empty, handled answer.
	 *
	 * @return void
	 */
	public function testUnknownTeacherGetsAnEmptyHandledAnswer(): void {
		$event = new TimetableSessionsQueryEvent(sourceApp: 'learniq', criteria: ['teacherUserId' => 'nobody']);

		$this->listener->handle($event);

		self::assertTrue(condition: $event->isHandled());
		self::assertSame(expected: [], actual: $event->getSessions());

	}//end testUnknownTeacherGetsAnEmptyHandledAnswer()

	/**
	 * Another event type is ignored.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$other = new TimetableUpsertRequestedEvent(sourceApp: 'integriq', sourceSystem: 'roster-zermelo', sessions: []);

		$this->listener->handle($other);

		self::assertFalse(condition: $other->isHandled());

	}//end testOtherEventsAreIgnored()
}//end class
