<?php

/**
 * Unit tests for TimetableUpsertRequestedListener.
 *
 * Built on the REAL event class and the REAL service over an in-memory
 * OpenRegister, so a wrong accessor or a wrong result key fails here instead
 * of in the consuming app.
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
use OCA\Planninq\Listener\TimetableUpsertRequestedListener;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests the upsert door another app uses.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-delivers-a-batch-through-a-typed-event-req-004
 */
class TimetableUpsertRequestedListenerTest extends TestCase {

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objectService;

	/**
	 * The listener under test.
	 *
	 * @var TimetableUpsertRequestedListener
	 */
	private TimetableUpsertRequestedListener $listener;

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

		$this->listener = new TimetableUpsertRequestedListener(
			sessions: new TimetableSessionService(container: $container, logger: new NullLogger()),
			logger: new NullLogger()
		);

	}//end setUp()

	/**
	 * One valid lesson row.
	 *
	 * @param string $ref The occurrence id.
	 *
	 * @return array<string,string>
	 */
	private function row(string $ref): array {
		return [
			'externalRef' => $ref,
			'subject' => 'Wiskunde',
			'startsAt' => '2026-09-28T09:00:00+02:00',
			'endsAt' => '2026-09-28T09:50:00+02:00',
			'groupReference' => '3a',
		];

	}//end row()

	/**
	 * A dispatched batch comes back handled with the contract's result.
	 *
	 * @return void
	 */
	public function testBatchIsAnsweredWithTheResult(): void {
		$event = new TimetableUpsertRequestedEvent(
			sourceApp: 'integriq',
			sourceSystem: 'roster-zermelo',
			sessions: [$this->row('zm-1'), $this->row('zm-2')],
			correlationId: 'run-42'
		);

		self::assertFalse(condition: $event->isHandled());
		$this->listener->handle($event);

		self::assertTrue(condition: $event->isHandled());
		$result = $event->getResult();
		self::assertSame(expected: TimetableUpsertRequestedEvent::CONTRACT_VERSION, actual: $result['contractVersion']);
		self::assertSame(expected: 2, actual: $result['created']);
		self::assertSame(expected: 'roster-zermelo', actual: $result['sourceSystem']);
		self::assertArrayNotHasKey(key: 'error', array: $result);

	}//end testBatchIsAnsweredWithTheResult()

	/**
	 * A row's own sourceSystem cannot redirect the batch.
	 *
	 * @return void
	 */
	public function testTheEventsSourceWinsOverARowsSource(): void {
		$row = $this->row('zm-3');
		$row['sourceSystem'] = 'roster-xedule';

		$this->listener->handle(new TimetableUpsertRequestedEvent(sourceApp: 'integriq', sourceSystem: 'roster-zermelo', sessions: [$row]));

		self::assertSame(expected: 'roster-zermelo', actual: $this->objectService->saves[0]['object']['sourceSystem']);

	}//end testTheEventsSourceWinsOverARowsSource()

	/**
	 * A batch refused as a whole is still answered, carrying an error.
	 *
	 * @return void
	 */
	public function testRefusedBatchIsAnsweredWithAnError(): void {
		$event = new TimetableUpsertRequestedEvent(sourceApp: 'integriq', sourceSystem: '', sessions: [$this->row('zm-4')]);

		$this->listener->handle($event);

		self::assertTrue(condition: $event->isHandled());
		self::assertSame(expected: 0, actual: $event->getResult()['created']);
		self::assertSame(expected: 1, actual: $event->getResult()['processed']);
		self::assertNotEmpty(actual: $event->getResult()['error']);

	}//end testRefusedBatchIsAnsweredWithAnError()

	/**
	 * Without OpenRegister the batch is answered with an error, not dropped.
	 *
	 * @return void
	 */
	public function testMissingOpenRegisterIsAnsweredWithAnError(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('absent'));
		$listener = new TimetableUpsertRequestedListener(
			sessions: new TimetableSessionService(container: $container, logger: new NullLogger()),
			logger: new NullLogger()
		);
		$event = new TimetableUpsertRequestedEvent(sourceApp: 'integriq', sourceSystem: 'roster-zermelo', sessions: [$this->row('zm-5')]);

		$listener->handle($event);

		self::assertSame(expected: 'OpenRegister is not available.', actual: $event->getResult()['error']);

	}//end testMissingOpenRegisterIsAnsweredWithAnError()

	/**
	 * Another event type is ignored.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$other = new TimetableSessionsQueryEvent(sourceApp: 'learniq', criteria: ['cohortId' => 'c-1']);

		$this->listener->handle($other);

		self::assertFalse(condition: $other->isHandled());
		self::assertSame(expected: [], actual: $this->objectService->searches);

	}//end testOtherEventsAreIgnored()
}//end class
