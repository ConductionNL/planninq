<?php

/**
 * Tests for the flow replay and FlowHistoryService (portfolio-flow-reports 1.1).
 *
 * The histories are shaped exactly as OpenRegister's
 * AuditTrailMapper::findChangesForObject() returns them: `created` as the
 * database's `Y-m-d H:i:s` (UTC) and `changed` as `{field: {old, new}}`, the
 * shape AuditTrailMapper::buildAuditTrail() writes from two jsonSerialize()d
 * objects (top-level fields, `@self` beside them).
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
 *
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\OpenRegister\Db\AuditTrailMapper;
use OCA\Planninq\Service\FlowHistoryService;
use OCA\Planninq\Service\FlowReplay;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Replays a fixed history into known counts, lead and cycle times.
 */
class FlowHistoryServiceTest extends TestCase {

	private const TODO = 'c-todo';
	private const DOING = 'c-doing';
	private const REVIEW = 'c-review';
	private const DONE = 'c-done';

	/**
	 * The four columns, given out of order to prove board order is used.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function columns(): array {
		return [
			['id' => self::DONE, 'title' => 'Done', 'order' => 3],
			['id' => self::TODO, 'title' => 'To do', 'order' => 0],
			['id' => self::REVIEW, 'title' => 'Review', 'order' => 2],
			['id' => self::DOING, 'title' => 'Doing', 'order' => 1],
		];
	}//end columns()

	/**
	 * Task A of the spec scenario, task B done without completedAt, task C without history.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function tasks(): array {
		return [
			['id' => 'A', 'title' => 'Export to CSV', 'key' => 'PLN-1', 'created' => '2026-09-01T10:00:00+00:00', 'column' => self::DONE, 'status' => 'done', 'completedAt' => '2026-09-08T10:00:00+00:00'],
			['id' => 'B', 'title' => 'Printer', 'key' => 'PLN-2', 'created' => '2026-09-02T09:00:00+00:00', 'column' => self::DONE, 'status' => 'done', 'completedAt' => null],
			['id' => 'C', 'title' => 'Old task', 'key' => 'PLN-3', 'created' => '2026-09-04T08:00:00+00:00', 'column' => self::REVIEW, 'status' => 'in_progress', 'completedAt' => null],
		];
	}//end tasks()

	/**
	 * Audit rows per task, as findChangesForObject() returns them.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function histories(): array {
		return [
			'A' => [
				['created' => '2026-09-01 10:00:00', 'changed' => ['title' => ['old' => null, 'new' => 'Export to CSV'], 'column' => ['old' => null, 'new' => self::TODO], 'status' => ['old' => null, 'new' => 'open'], '@self' => ['old' => null, 'new' => []]]],
				['created' => '2026-09-03 10:00:00', 'changed' => ['column' => ['old' => self::TODO, 'new' => self::DOING], 'status' => ['old' => 'open', 'new' => 'in_progress']]],
				['created' => '2026-09-05 12:00:00', 'changed' => ['description' => ['old' => null, 'new' => 'x']]],
				['created' => '2026-09-08 10:00:00', 'changed' => ['column' => ['old' => self::DOING, 'new' => self::DONE], 'status' => ['old' => 'in_progress', 'new' => 'done'], 'completedAt' => ['old' => null, 'new' => '2026-09-08T10:00:00+00:00']]],
			],
			'B' => [
				['created' => '2026-09-02 09:00:00', 'changed' => ['column' => ['old' => null, 'new' => self::TODO], 'status' => ['old' => null, 'new' => 'open']]],
				['created' => '2026-09-05 09:00:00', 'changed' => ['column' => ['old' => self::TODO, 'new' => self::DONE], 'status' => ['old' => 'open', 'new' => 'done']]],
			],
			'C' => [],
		];
	}//end histories()

	/**
	 * A UTC midnight.
	 *
	 * @param string $date Y-m-d
	 *
	 * @return DateTimeImmutable
	 */
	private function day(string $date): DateTimeImmutable {
		return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
	}//end day()

	/**
	 * Every day holds the tasks per column at its end, in board order.
	 *
	 * @return void
	 */
	public function testReplaysDailyColumnCounts(): void {
		$days = (new FlowReplay())->replay(
			columns: $this->columns(),
			tasks: $this->tasks(),
			histories: $this->histories(),
			from: $this->day('2026-09-01'),
			to: $this->day('2026-09-09'),
			now: new DateTimeImmutable('2026-09-30 12:00:00', new DateTimeZone('UTC'))
		);

		self::assertSame(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05', '2026-09-06', '2026-09-07', '2026-09-08', '2026-09-09'], array_keys($days));
		self::assertSame([self::TODO, self::DOING, self::REVIEW, self::DONE], array_keys($days['2026-09-01']['counts']));
		self::assertSame([self::TODO => 1, self::DOING => 0, self::REVIEW => 0, self::DONE => 0], $days['2026-09-01']['counts']);
		self::assertSame([self::TODO => 2, self::DOING => 0, self::REVIEW => 0, self::DONE => 0], $days['2026-09-02']['counts']);
		self::assertSame([self::TODO => 1, self::DOING => 1, self::REVIEW => 0, self::DONE => 0], $days['2026-09-03']['counts']);
		// C has no history: it counts in its current column from its creation, and is flagged.
		self::assertSame([self::TODO => 1, self::DOING => 1, self::REVIEW => 1, self::DONE => 0], $days['2026-09-04']['counts']);
		self::assertSame(1, $days['2026-09-04']['withoutHistory']);
		self::assertSame(0, $days['2026-09-03']['withoutHistory']);
		self::assertSame([self::TODO => 0, self::DOING => 1, self::REVIEW => 1, self::DONE => 1], $days['2026-09-05']['counts']);
		self::assertSame([self::TODO => 0, self::DOING => 0, self::REVIEW => 1, self::DONE => 2], $days['2026-09-09']['counts']);
	}//end testReplaysDailyColumnCounts()

	/**
	 * Created 1 Sep, to Doing 3 Sep, Done 8 Sep: lead 7 days, cycle 5 days
	 * (the spec scenario); a done task without completedAt is estimated.
	 *
	 * @return void
	 */
	public function testLeadAndCycleTimeAndTheEstimatedFallback(): void {
		$replay = new FlowReplay();
		$days = $replay->replay(
			columns: $this->columns(),
			tasks: $this->tasks(),
			histories: $this->histories(),
			from: $this->day('2026-09-01'),
			to: $this->day('2026-09-30'),
			now: new DateTimeImmutable('2026-09-30 12:00:00', new DateTimeZone('UTC'))
		);

		$a = $days['2026-09-08']['finished'][0];
		self::assertSame('A', $a['id']);
		self::assertSame(7.0, $a['leadDays']);
		self::assertSame(5.0, $a['cycleDays']);
		self::assertFalse($a['estimated']);

		// B: finish taken from the last change to done; it went straight
		// from the first column to done, so its cycle time is zero.
		$b = $days['2026-09-05']['finished'][0];
		self::assertSame('B', $b['id']);
		self::assertTrue($b['estimated']);
		self::assertSame(3.0, $b['leadDays']);
		self::assertSame(0.0, $b['cycleDays']);

		$summary = $replay->summarise(finished: [$a, $b]);
		self::assertSame(2, $summary['finished']);
		self::assertSame(1, $summary['estimated']);
		self::assertSame(['average' => 5.0, 'p85' => 7.0], $summary['lead']);
		self::assertSame(['average' => 2.5, 'p85' => 5.0], $summary['cycle']);
		self::assertSame(['A', 'B'], array_column($summary['slowest'], 'id'));
	}//end testLeadAndCycleTimeAndTheEstimatedFallback()

	/**
	 * A history that starts after creation replays from the first rows' `old` values.
	 *
	 * @return void
	 */
	public function testAHistoryStartingLateUsesTheOldValues(): void {
		$days = (new FlowReplay())->replay(
			columns: $this->columns(),
			tasks: [['id' => 'D', 'title' => 'D', 'created' => '2026-09-01T08:00:00+00:00', 'column' => self::DOING, 'status' => 'in_progress', 'completedAt' => null]],
			histories: ['D' => [['created' => '2026-09-03 08:00:00', 'changed' => ['column' => ['old' => self::REVIEW, 'new' => self::DOING]]]]],
			from: $this->day('2026-09-02'),
			to: $this->day('2026-09-03'),
			now: new DateTimeImmutable('2026-09-30 12:00:00', new DateTimeZone('UTC'))
		);

		self::assertSame(1, $days['2026-09-02']['counts'][self::REVIEW]);
		self::assertSame(1, $days['2026-09-03']['counts'][self::DOING]);
	}//end testAHistoryStartingLateUsesTheOldValues()

	/**
	 * The service reads every task's history once, then serves past days from
	 * the cache: a second read of the same window reads no history at all.
	 *
	 * @return void
	 */
	public function testPastDaysComeFromTheCacheTheSecondTime(): void {
		$histories = $this->histories();
		$mapper = $this->createMock(originalClassName: AuditTrailMapper::class);
		$mapper->expects(self::exactly(3))
			->method('findChangesForObject')
			->willReturnCallback(static fn (string $objectUuid, int $limit = 1000): array => $histories[$objectUuid]);

		$service = $this->service(mapper: $mapper, now: '2026-09-30 12:00:00');
		$first = $service->forProject(objectService: $this->objectService(), projectId: 'p1', from: $this->day('2026-09-01'), to: $this->day('2026-09-29'));
		$second = $service->forProject(objectService: $this->objectService(), projectId: 'p1', from: $this->day('2026-09-01'), to: $this->day('2026-09-29'));

		self::assertSame(0, $first['fromCache']);
		self::assertSame(29, $second['fromCache']);
		self::assertSame($first['days'], $second['days']);
		self::assertSame($first['summary'], $second['summary']);
		self::assertSame(['Export to CSV', 'Printer'], array_column($first['summary']['slowest'], 'title'));
		self::assertSame('2026-09-08T10:00:00+00:00', $first['finished'][1]['finishedAt']);
		self::assertSame(['id' => self::TODO, 'title' => 'To do', 'color' => null], $first['columns'][0]);
		self::assertSame(1, $first['withoutHistory']);
	}//end testPastDaysComeFromTheCacheTheSecondTime()

	/**
	 * Today is never cached: with every past day cached, only tasks that may
	 * have finished today have their history read.
	 *
	 * @return void
	 */
	public function testTodayIsRecomputedFromTheTasksThatMayFinishToday(): void {
		$histories = $this->histories();
		$read = [];
		$mapper = $this->createMock(originalClassName: AuditTrailMapper::class);
		$mapper->method('findChangesForObject')->willReturnCallback(
			static function (string $objectUuid, int $limit = 1000) use ($histories, &$read): array {
				$read[] = $objectUuid;
				return $histories[$objectUuid];
			}
		);

		$service = $this->service(mapper: $mapper, now: '2026-09-30 12:00:00');
		$service->forProject(objectService: $this->objectService(), projectId: 'p1', from: $this->day('2026-09-01'), to: $this->day('2026-09-30'));
		$read = [];
		$again = $service->forProject(objectService: $this->objectService(), projectId: 'p1', from: $this->day('2026-09-01'), to: $this->day('2026-09-30'));

		// B is done without completedAt, so it may have finished today; A finished on 8 Sep.
		self::assertSame(['B'], $read);
		self::assertSame(29, $again['fromCache']);
		self::assertSame('2026-09-30', end($again['days'])['date']);
		self::assertSame([self::TODO => 0, self::DOING => 0, self::REVIEW => 1, self::DONE => 2], end($again['days'])['counts']);
	}//end testTodayIsRecomputedFromTheTasksThatMayFinishToday()

	/**
	 * The service under test with an in-memory cache.
	 *
	 * @param AuditTrailMapper $mapper The audit trail mapper double
	 * @param string           $now    The current UTC moment
	 *
	 * @return FlowHistoryService
	 */
	private function service(AuditTrailMapper $mapper, string $now): FlowHistoryService {
		$store = [];
		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturnCallback(static function (string $key) use (&$store) {
			return ($store[$key] ?? null);
		});
		$cache->method('set')->willReturnCallback(static function (string $key, mixed $value, int $ttl = 0) use (&$store): bool {
			$store[$key] = $value;
			return true;
		});
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->with(AuditTrailMapper::class)->willReturn($mapper);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable($now, new DateTimeZone('UTC')));

		return new FlowHistoryService(
			container: $container,
			cacheFactory: $factory,
			timeFactory: $time,
			replay: new FlowReplay(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * An ObjectService double with OpenRegister's searchObjectsBySlug() signature.
	 *
	 * @return object
	 */
	private function objectService(): object {
		$rows = ['task' => $this->tasks(), 'column' => $this->columns()];
		return new class($rows) {
			/**
			 * @param array<string,array> $rows Rows per schema slug
			 */
			public function __construct(private array $rows) {
			}

			/**
			 * Rows of a schema, as plain arrays with an @self block.
			 *
			 * @param string $registerSlug  Register slug
			 * @param string $schemaSlug    Schema slug
			 * @param array  $filters       Filters
			 * @param bool   $_rbac         RBAC on
			 * @param bool   $_multitenancy Multitenancy on
			 *
			 * @return array
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array {
				if ($registerSlug !== 'planninq' || ($filters['project'] ?? null) !== 'p1' || $_rbac !== true) {
					return [];
				}

				return array_map(
					static function (array $row): array {
						$created = ($row['created'] ?? null);
						unset($row['created']);
						$row['@self'] = ['id' => $row['id'], 'created' => $created];
						return $row;
					},
					$this->rows[$schemaSlug]
				);
			}
		};
	}//end objectService()
}//end class
