<?php

/**
 * Unit tests for TimetableSessionService.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/InMemoryTimetableObjectService.php';

use InvalidArgumentException;
use OCA\Planninq\Service\TimetableSessionQuery;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryTimetableObjectService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests the upsert and read rules of the school timetable.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
 */
class TimetableSessionServiceTest extends TestCase {

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryTimetableObjectService
	 */
	private InMemoryTimetableObjectService $objectService;

	/**
	 * The service under test.
	 *
	 * @var TimetableSessionService
	 */
	private TimetableSessionService $service;

	/**
	 * Build the service over an in-memory OpenRegister.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objectService = new InMemoryTimetableObjectService();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($this->objectService);

		$this->service = new TimetableSessionService(container: $container, logger: new NullLogger());

	}//end setUp()

	/**
	 * A two-lesson Zermelo batch.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function batch(): array {
		return [
			[
				'externalRef' => 'zm-1001',
				'subject' => 'Wiskunde',
				'startsAt' => '2026-09-28T09:00:00+02:00',
				'endsAt' => '2026-09-28T09:50:00+02:00',
				'groupReference' => '3a',
				'teacherReference' => 'JAN',
				'roomReference' => 'A1.12',
				'roomLabel' => 'A1.12',
			],
			[
				'externalRef' => 'zm-1002',
				'subject' => 'Nederlands',
				'startsAt' => '2026-09-28T10:00:00+02:00',
				'endsAt' => '2026-09-28T10:50:00+02:00',
				'groupReference' => '3a',
				'teacherReference' => 'PIE',
				'roomReference' => 'B2.04',
			],
		];

	}//end batch()

	/**
	 * Delivering the same timetable twice creates no duplicates and saves nothing the second time.
	 *
	 * @return void
	 */
	public function testRedeliveryIsUnchangedAndSavesNothing(): void {
		$first = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: $this->batch());

		self::assertSame(expected: 1, actual: $first['contractVersion']);
		self::assertSame(expected: 2, actual: $first['created']);
		self::assertCount(expectedCount: 2, haystack: $first['sessionIds']);
		self::assertCount(expectedCount: 2, haystack: $this->objectService->saves);

		$second = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: $this->batch());

		self::assertSame(expected: 0, actual: $second['created']);
		self::assertSame(expected: 0, actual: $second['updated']);
		self::assertSame(expected: 2, actual: $second['unchanged']);
		self::assertSame(expected: $first['sessionIds'], actual: $second['sessionIds']);
		self::assertCount(expectedCount: 2, haystack: $this->objectService->saves, message: 'the second run must not save');
		self::assertCount(expectedCount: 2, haystack: $this->objectService->rows['timetableSession']);

	}//end testRedeliveryIsUnchangedAndSavesNothing()

	/**
	 * Created rows carry the defaults, the server-side stamp and the batch source, written without RBAC.
	 *
	 * @return void
	 */
	public function testCreateStampsImportedAtAndDefaults(): void {
		$this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->batch()[1]]);

		$save = $this->objectService->saves[0];
		self::assertSame(expected: 'timetableSession', actual: $save['schema']);
		self::assertNull(actual: $save['uuid']);
		self::assertFalse(condition: $save['rbac']);
		self::assertSame(expected: 'roster-zermelo', actual: $save['object']['sourceSystem']);
		self::assertSame(expected: 'Nederlands', actual: $save['object']['title']);
		self::assertSame(expected: 'scheduled', actual: $save['object']['status']);
		self::assertNotFalse(condition: strtotime($save['object']['importedAt']));

	}//end testCreateStampsImportedAtAndDefaults()

	/**
	 * A moved lesson is updated in place under its own id.
	 *
	 * @return void
	 */
	public function testMovedLessonIsUpdatedInPlace(): void {
		$this->objectService->seed(
			schema: 'timetableSession',
			id: 'stored-1',
			data: [
				'externalRef' => 'zm-1001',
				'sourceSystem' => 'roster-zermelo',
				'subject' => 'Wiskunde',
				'title' => 'Wiskunde',
				'startsAt' => '2026-09-28T09:00:00+02:00',
				'endsAt' => '2026-09-28T09:50:00+02:00',
				'roomReference' => 'A1.12',
				'cohortId' => 'cohort-kept',
			]
		);

		$moved = $this->batch()[0];
		$moved['roomReference'] = 'B2.04';
		$result = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$moved]);

		self::assertSame(expected: 1, actual: $result['updated']);
		self::assertSame(expected: ['stored-1'], actual: $result['sessionIds']);
		self::assertSame(expected: 'stored-1', actual: $this->objectService->saves[0]['uuid']);
		self::assertSame(expected: 'B2.04', actual: $this->objectService->rows['timetableSession']['stored-1']['roomReference']);
		self::assertSame(
			expected: 'cohort-kept',
			actual: $this->objectService->rows['timetableSession']['stored-1']['cohortId'],
			message: 'a field the delivery does not carry keeps its stored value'
		);

	}//end testMovedLessonIsUpdatedInPlace()

	/**
	 * The same times written with another offset are the same moment, so the row is unchanged.
	 *
	 * @return void
	 */
	public function testSameMomentInAnotherOffsetIsUnchanged(): void {
		$this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->batch()[0]]);

		$utc = $this->batch()[0];
		$utc['startsAt'] = '2026-09-28T07:00:00Z';
		$utc['endsAt'] = '2026-09-28T07:50:00Z';
		$result = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$utc]);

		self::assertSame(expected: 1, actual: $result['unchanged']);

	}//end testSameMomentInAnotherOffsetIsUnchanged()

	/**
	 * The same externalRef from another source is a different lesson.
	 *
	 * @return void
	 */
	public function testSameRefFromAnotherSourceCreatesAnotherLesson(): void {
		$this->objectService->seed(
			schema: 'timetableSession',
			id: 'untis-1',
			data: [
				'externalRef' => '1001',
				'sourceSystem' => 'roster-untis-oneroster',
				'subject' => 'Engels',
				'startsAt' => '2026-09-28T09:00:00+02:00',
				'endsAt' => '2026-09-28T09:50:00+02:00',
			]
		);

		$row = $this->batch()[0];
		$row['externalRef'] = '1001';
		$result = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$row]);

		self::assertSame(expected: 1, actual: $result['created']);
		self::assertSame(expected: 'Engels', actual: $this->objectService->rows['timetableSession']['untis-1']['subject']);
		self::assertCount(expectedCount: 2, haystack: $this->objectService->rows['timetableSession']);

	}//end testSameRefFromAnotherSourceCreatesAnotherLesson()

	/**
	 * If OpenRegister ignored the key filter, the PHP re-check still never overwrites another lesson.
	 *
	 * @return void
	 */
	public function testAnIgnoredFilterNeverOverwritesAnotherLesson(): void {
		$this->objectService->seed(
			schema: 'timetableSession',
			id: 'other-lesson',
			data: [
				'externalRef' => 'zm-9999',
				'sourceSystem' => 'roster-zermelo',
				'subject' => 'Aardrijkskunde',
				'startsAt' => '2026-09-28T13:00:00+02:00',
				'endsAt' => '2026-09-28T13:50:00+02:00',
			]
		);
		$this->objectService->ignoreFilters = true;

		$result = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->batch()[0]]);

		self::assertSame(expected: 1, actual: $result['created']);
		self::assertNull(actual: $this->objectService->saves[0]['uuid']);
		self::assertSame(expected: 'Aardrijkskunde', actual: $this->objectService->rows['timetableSession']['other-lesson']['subject']);

	}//end testAnIgnoredFilterNeverOverwritesAnotherLesson()

	/**
	 * Invalid rows are rejected with their codes before anything is written; the valid rows land.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
	 */
	public function testInvalidRowsAreRejectedAndTheRestLands(): void {
		$batch = $this->batch();
		$noSubject = $batch[0];
		$noSubject['externalRef'] = 'zm-2001';
		unset($noSubject['subject']);
		$reversed = $batch[0];
		$reversed['externalRef'] = 'zm-2002';
		$reversed['endsAt'] = '2026-09-28T08:00:00+02:00';
		$badStatus = $batch[0];
		$badStatus['externalRef'] = 'zm-2003';
		$badStatus['status'] = 'moved';
		$earlierDuplicate = $batch[1];
		$earlierDuplicate['roomReference'] = 'OLD';

		$result = $this->service->upsert(
			sourceSystem: 'roster-zermelo',
			sessions: [$noSubject, $reversed, $badStatus, 'not a row', $earlierDuplicate, $batch[0], $batch[1]]
		);

		$codes = array_column($result['rejected'], 'errorCode', 'externalRef');
		self::assertSame(expected: 'missing-fields', actual: $codes['zm-2001']);
		self::assertSame(expected: 'invalid-dates', actual: $codes['zm-2002']);
		self::assertSame(expected: 'invalid-status', actual: $codes['zm-2003']);
		self::assertSame(expected: 'duplicate-external-ref', actual: $codes['zm-1002']);
		self::assertSame(expected: 2, actual: $result['created']);
		self::assertSame(expected: 7, actual: $result['processed']);
		self::assertSame(
			expected: $result['processed'],
			actual: $result['created'] + $result['updated'] + $result['unchanged'] + count($result['rejected'])
		);

		$stored = array_column($this->objectService->rows['timetableSession'], 'roomReference', 'externalRef');
		self::assertSame(expected: 'B2.04', actual: $stored['zm-1002'], message: 'the later duplicate wins');
		self::assertArrayNotHasKey(key: 'zm-2001', array: $stored);

	}//end testInvalidRowsAreRejectedAndTheRestLands()

	/**
	 * A save OpenRegister refuses is reported as save-failed, not counted as created.
	 *
	 * @return void
	 */
	public function testRefusedSaveIsReportedAsSaveFailed(): void {
		$this->objectService->failSaves = true;

		$result = $this->service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->batch()[0]]);

		self::assertSame(expected: 0, actual: $result['created']);
		self::assertSame(expected: 'save-failed', actual: $result['rejected'][0]['errorCode']);

	}//end testRefusedSaveIsReportedAsSaveFailed()

	/**
	 * An empty source system is refused.
	 *
	 * @return void
	 */
	public function testEmptySourceSystemIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->upsert(sourceSystem: '  ', sessions: $this->batch());

	}//end testEmptySourceSystemIsRefused()

	/**
	 * A batch of only invalid rows never needs OpenRegister.
	 *
	 * @return void
	 */
	public function testAllInvalidBatchDoesNotTouchOpenRegister(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects($this->never())->method('get');
		$service = new TimetableSessionService(container: $container, logger: new NullLogger());

		$result = $service->upsert(sourceSystem: 'roster-zermelo', sessions: [['externalRef' => 'x']]);

		self::assertSame(expected: 'missing-fields', actual: $result['rejected'][0]['errorCode']);

	}//end testAllInvalidBatchDoesNotTouchOpenRegister()

	/**
	 * A cohort's week comes back in time order, bounded to the window, read with RBAC on.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function testListReturnsACohortsWeekInTimeOrder(): void {
		$this->seedCohortWeek();

		$sessions = $this->service->list(
			criteria: ['cohortId' => 'c-1', 'from' => '2026-09-28T00:00:00+02:00', 'to' => '2026-10-04T23:59:59+02:00']
		);

		self::assertSame(expected: ['early', 'late'], actual: array_column($sessions, 'externalRef'));
		self::assertSame(expected: 'Wiskunde', actual: $sessions[0]['title'], message: 'an empty title falls back to the subject');
		self::assertNull(actual: $sessions[0]['roomLabel']);

		$search = end($this->objectService->searches);
		self::assertTrue(condition: $search['rbac']);
		self::assertSame(expected: 'c-1', actual: $search['filters']['cohortId']);
		self::assertSame(expected: TimetableSessionQuery::DEFAULT_LIMIT, actual: $search['filters']['_limit']);

	}//end testListReturnsACohortsWeekInTimeOrder()

	/**
	 * Another app's read runs with RBAC off and returns the same rows, re-checked.
	 *
	 * The HTTP read stays with RBAC on (above). Only the in-process query event
	 * reads as the system, because the requesting app has already decided that
	 * its user may see this cohort (planninq#711).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function testListForAppReadsWithRbacOffAndTheSameRecheck(): void {
		$this->seedCohortWeek();

		$sessions = $this->service->listForApp(
			criteria: ['cohortId' => 'c-1', 'from' => '2026-09-28T00:00:00+02:00', 'to' => '2026-10-04T23:59:59+02:00']
		);

		self::assertSame(expected: ['early', 'late'], actual: array_column($sessions, 'externalRef'));

		$search = end($this->objectService->searches);
		self::assertFalse(condition: $search['rbac']);
		self::assertSame(expected: 'c-1', actual: $search['filters']['cohortId']);

	}//end testListForAppReadsWithRbacOffAndTheSameRecheck()

	/**
	 * The PHP re-check keeps other cohorts and out-of-window rows out even when OpenRegister ignores the filters.
	 *
	 * @return void
	 */
	public function testListRechecksWhatOpenRegisterAnswered(): void {
		$this->seedCohortWeek();
		$this->objectService->ignoreFilters = true;

		$sessions = $this->service->list(
			criteria: [
				'cohortId' => 'c-1',
				'from' => '2026-09-28T00:00:00+02:00',
				'to' => '2026-10-04T23:59:59+02:00',
				'includeCancelled' => false,
			]
		);

		self::assertSame(expected: ['early'], actual: array_column($sessions, 'externalRef'));

	}//end testListRechecksWhatOpenRegisterAnswered()

	/**
	 * A lesson at the window edge, stored in another offset than the window, is still found:
	 * the store compares strings, so the prefilter is wider and PHP applies the exact window.
	 *
	 * @return void
	 */
	public function testEdgeLessonInAnotherOffsetIsFound(): void {
		$this->objectService->seed(
			schema: 'timetableSession',
			id: 's-edge',
			data: [
				'externalRef' => 'edge',
				'sourceSystem' => 'roster-zermelo',
				'subject' => 'Avondles',
				'startsAt' => '2026-10-04T23:30:00+02:00',
				'endsAt' => '2026-10-05T00:20:00+02:00',
				'teacherUserId' => 'jan',
			]
		);

		$sessions = $this->service->list(criteria: ['teacherUserId' => 'jan', 'to' => '2026-10-04T22:00:00Z']);

		self::assertSame(expected: ['edge'], actual: array_column($sessions, 'externalRef'));

	}//end testEdgeLessonInAnotherOffsetIsFound()

	/**
	 * A read without an identity filter, or with a reversed window, is refused.
	 *
	 * @return void
	 */
	public function testListRefusesMissingIdentityAndReversedWindow(): void {
		try {
			$this->service->list(criteria: ['from' => '2026-09-28T00:00:00+02:00']);
			self::fail('a read without an identity filter must be refused');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString(needle: 'cohortId', haystack: $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->list(criteria: ['teacherUserId' => 'jan', 'from' => '2026-10-04', 'to' => '2026-09-28']);

	}//end testListRefusesMissingIdentityAndReversedWindow()

	/**
	 * The limit is capped at the maximum.
	 *
	 * @return void
	 */
	public function testListCapsTheLimit(): void {
		$this->service->list(criteria: ['teacherReference' => 'JAN', 'limit' => 50000]);

		$search = end($this->objectService->searches);
		self::assertSame(expected: TimetableSessionQuery::MAX_LIMIT, actual: $search['filters']['_limit']);

	}//end testListCapsTheLimit()

	/**
	 * Without OpenRegister the service says so instead of answering empty.
	 *
	 * @return void
	 */
	public function testMissingOpenRegisterIsAnError(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('no such service'));
		$service = new TimetableSessionService(container: $container, logger: new NullLogger());

		$this->expectException(RuntimeException::class);
		$service->list(criteria: ['cohortId' => 'c-1']);

	}//end testMissingOpenRegisterIsAnError()

	/**
	 * Seed a cohort week: two lessons inside it (out of order), one outside, one for another cohort, one cancelled.
	 *
	 * @return void
	 */
	private function seedCohortWeek(): void {
		$lesson = static fn (string $ref, string $cohort, string $start, string $end, string $status = 'scheduled'): array => [
			'externalRef' => $ref,
			'sourceSystem' => 'roster-zermelo',
			'subject' => 'Wiskunde',
			'title' => '',
			'startsAt' => $start,
			'endsAt' => $end,
			'cohortId' => $cohort,
			'status' => $status,
		];

		$this->objectService->seed(schema: 'timetableSession', id: 's-late', data: $lesson('late', 'c-1', '2026-09-30T11:00:00+02:00', '2026-09-30T11:50:00+02:00', 'cancelled'));
		$this->objectService->seed(schema: 'timetableSession', id: 's-early', data: $lesson('early', 'c-1', '2026-09-28T09:00:00+02:00', '2026-09-28T09:50:00+02:00'));
		$this->objectService->seed(schema: 'timetableSession', id: 's-next', data: $lesson('next-week', 'c-1', '2026-10-06T09:00:00+02:00', '2026-10-06T09:50:00+02:00'));
		$this->objectService->seed(schema: 'timetableSession', id: 's-other', data: $lesson('other', 'c-2', '2026-09-28T09:00:00+02:00', '2026-09-28T09:50:00+02:00'));

	}//end seedCohortWeek()
}//end class
