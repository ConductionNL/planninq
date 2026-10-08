<?php

/**
 * Unit tests for the course query and the lesson link (timetable contract v2).
 *
 * learniq asks planninq for the lessons of one course (an elective) and shows
 * a Join action from a lesson's online link (DECISIONS row 53, learniq live
 * pass D8). Built on the REAL event, listener, service, rows and query
 * classes over an in-memory OpenRegister, and every stored lesson is checked
 * against the REAL timetableSession fragment with Opis.
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

require_once __DIR__ . '/../Support/InMemoryTimetableObjectService.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Event\TimetableSessionsQueryEvent;
use OCA\Planninq\Listener\TimetableSessionsQueryListener;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryTimetableObjectService;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * A course query answers one course's lessons, and every lesson carries its course and link.
 *
 * @spec openspec/changes/timetable-course-query/specs/school-timetable/spec.md#requirement-another-app-reads-a-courses-lessons-and-their-online-link-req-007
 */
class TimetableCourseQueryTest extends TestCase {
	use RegisterSchemaValidation;

	private const COURSE_A = '4f1c2b7e-9a51-4c3e-8d2a-0b6e1f3a5c71';

	private const COURSE_B = '8b0d6e2f-3c47-4a19-9e85-7d2c1a4b6f90';

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryTimetableObjectService
	 */
	private InMemoryTimetableObjectService $objectService;

	/**
	 * The real service.
	 *
	 * @var TimetableSessionService
	 */
	private TimetableSessionService $sessions;

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

		$this->objectService = new InMemoryTimetableObjectService();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($this->objectService);

		$this->sessions = new TimetableSessionService(container: $container, logger: new NullLogger());
		$this->listener = new TimetableSessionsQueryListener(sessions: $this->sessions, logger: new NullLogger());

	}//end setUp()

	/**
	 * Deliver lessons through the real upsert, the way an import writes them.
	 *
	 * @param array<int,array<string,mixed>> $rows The rows.
	 *
	 * @return array<string,mixed> The upsert result.
	 */
	private function deliver(array $rows): array {
		return $this->sessions->upsert(sourceSystem: 'roster-zermelo', sessions: $rows);

	}//end deliver()

	/**
	 * One lesson row.
	 *
	 * @param string              $ref   The occurrence id.
	 * @param string              $start Start moment.
	 * @param array<string,mixed> $extra Extra fields.
	 *
	 * @return array<string,mixed>
	 */
	private function lesson(string $ref, string $start, array $extra=[]): array {
		return array_merge(
			[
				'externalRef' => $ref,
				'subject' => 'Spaans',
				'startsAt' => $start,
				'endsAt' => date(DATE_ATOM, ((int)strtotime($start) + 3000)),
				'cohortId' => 'c-1',
				'status' => 'scheduled',
			],
			$extra
		);

	}//end lesson()

	/**
	 * learniq reads CONTRACT_VERSION: below 2 it sends no course query.
	 *
	 * @return void
	 */
	public function testTheContractVersionSaysTheCourseQueryExists(): void {
		self::assertGreaterThanOrEqual(expected: 2, actual: TimetableSessionsQueryEvent::CONTRACT_VERSION);

	}//end testTheContractVersionSaysTheCourseQueryExists()

	/**
	 * A query naming only a course answers that course's lessons in the window, earliest first.
	 *
	 * @return void
	 */
	public function testACourseQueryAnswersThatCoursesLessons(): void {
		$result = $this->deliver(
			rows: [
				$this->lesson(ref: 'a-2', start: '2026-10-06T10:00:00+02:00', extra: ['courseId' => self::COURSE_A]),
				$this->lesson(ref: 'a-1', start: '2026-10-05T10:00:00+02:00', extra: ['courseId' => self::COURSE_A]),
				$this->lesson(ref: 'a-late', start: '2026-11-30T10:00:00+01:00', extra: ['courseId' => self::COURSE_A]),
				$this->lesson(ref: 'b-1', start: '2026-10-05T10:00:00+02:00', extra: ['courseId' => self::COURSE_B]),
				$this->lesson(ref: 'none', start: '2026-10-05T11:00:00+02:00'),
			]
		);
		self::assertSame(expected: [], actual: $result['rejected']);

		$event = new TimetableSessionsQueryEvent(
			sourceApp: 'learniq',
			criteria: ['courseId' => self::COURSE_A, 'limit' => 1000, 'from' => '2026-10-05T00:00:00+02:00', 'to' => '2026-10-11T23:59:59+02:00']
		);
		$this->listener->handle($event);

		self::assertNull(actual: $event->getError());
		self::assertTrue(condition: $event->isHandled());
		self::assertSame(expected: ['a-1', 'a-2'], actual: array_column((array)$event->getSessions(), 'externalRef'));
		self::assertSame(expected: [self::COURSE_A, self::COURSE_A], actual: array_column((array)$event->getSessions(), 'courseId'));

	}//end testACourseQueryAnswersThatCoursesLessons()

	/**
	 * Every lesson carries courseId and onlineMeetingUrl, null when unset, on a cohort query too.
	 *
	 * @return void
	 */
	public function testEveryLessonCarriesItsCourseAndLink(): void {
		$this->deliver(
			rows: [
				$this->lesson(ref: 'online', start: '2026-10-05T09:00:00+02:00', extra: ['courseId' => self::COURSE_A, 'onlineMeetingUrl' => 'https://meet.example.org/spaans-4b']),
				$this->lesson(ref: 'plain', start: '2026-10-05T10:00:00+02:00'),
			]
		);

		$event = new TimetableSessionsQueryEvent(sourceApp: 'learniq', criteria: ['cohortId' => 'c-1']);
		$this->listener->handle($event);

		$byRef = array_column((array)$event->getSessions(), null, 'externalRef');
		self::assertSame(expected: 'https://meet.example.org/spaans-4b', actual: $byRef['online']['onlineMeetingUrl']);
		self::assertSame(expected: self::COURSE_A, actual: $byRef['online']['courseId']);
		self::assertArrayHasKey(key: 'courseId', array: $byRef['plain']);
		self::assertArrayHasKey(key: 'onlineMeetingUrl', array: $byRef['plain']);
		self::assertNull(actual: $byRef['plain']['courseId']);
		self::assertNull(actual: $byRef['plain']['onlineMeetingUrl']);

	}//end testEveryLessonCarriesItsCourseAndLink()

	/**
	 * What the upsert stores is accepted by the real timetableSession fragment.
	 *
	 * @return void
	 */
	public function testTheStoredLessonFitsTheRegisterSchema(): void {
		$this->deliver(
			rows: [
				$this->lesson(ref: 'online', start: '2026-10-05T09:00:00+02:00', extra: ['courseId' => self::COURSE_A, 'onlineMeetingUrl' => 'https://meet.example.org/spaans-4b']),
				$this->lesson(ref: 'cleared', start: '2026-10-05T10:00:00+02:00', extra: ['courseId' => '', 'onlineMeetingUrl' => '']),
			]
		);

		self::assertCount(expectedCount: 2, haystack: $this->objectService->rows['timetableSession']);
		foreach ($this->objectService->rows['timetableSession'] as $data) {
			self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableSession', payload: $data), message: (string)$data['externalRef']);
		}

	}//end testTheStoredLessonFitsTheRegisterSchema()

	/**
	 * A course id that is not a uuid, or a link that is not an address, is refused before it is written.
	 *
	 * @return void
	 */
	public function testAMalformedCourseOrLinkIsRefused(): void {
		$result = $this->deliver(
			rows: [
				$this->lesson(ref: 'bad-course', start: '2026-10-05T09:00:00+02:00', extra: ['courseId' => 'spaans']),
				$this->lesson(ref: 'bad-link', start: '2026-10-05T10:00:00+02:00', extra: ['onlineMeetingUrl' => 'lokaal 12']),
			]
		);

		self::assertSame(
			expected: ['bad-course' => 'invalid-course-id', 'bad-link' => 'invalid-link'],
			actual: array_column($result['rejected'], 'errorCode', 'externalRef')
		);
		self::assertSame(expected: [], actual: ($this->objectService->rows['timetableSession'] ?? []));

	}//end testAMalformedCourseOrLinkIsRefused()

	/**
	 * The refusal for a query without identity names courseId too.
	 *
	 * @return void
	 */
	public function testTheRefusalNamesTheCourse(): void {
		$event = new TimetableSessionsQueryEvent(sourceApp: 'learniq', criteria: ['from' => '2026-10-05T00:00:00+02:00']);
		$this->listener->handle($event);

		self::assertNull(actual: $event->getSessions());
		self::assertStringContainsString(needle: 'courseId', haystack: (string)$event->getError());

	}//end testTheRefusalNamesTheCourse()
}//end class
