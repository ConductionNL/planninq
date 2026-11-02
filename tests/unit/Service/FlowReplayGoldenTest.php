<?php

/**
 * Golden test for the flow replay and the flow history service.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\OpenRegister\Db\AuditTrailMapper;
use OCA\Planninq\Service\FlowBoard;
use OCA\Planninq\Service\FlowHistoryService;
use OCA\Planninq\Service\FlowReplay;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Replays seeded boards (shuffled columns, tasks with and without history,
 * done without completedAt, moves outside the board) directly and through
 * FlowHistoryService on a cold and a warm cache, and compares every result
 * with the fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Service\FlowReplay
 * @covers \OCA\Planninq\Service\FlowHistoryService
 * @covers \OCA\Planninq\Service\FlowBoard
 */
class FlowReplayGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/flow-replay.golden.json';

	/**
	 * The recorded results equal the fixture.
	 *
	 * @return void
	 */
	public function testReplayMatchesTheGoldenRecording(): void {
		$cases = [$this->recordEdgeCase()];
		for ($seed = 1; $seed <= 16; $seed++) {
			$cases[] = $this->recordCase(seed: $seed);
		}

		$json = json_encode($cases, JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			if (is_dir(dirname(self::FIXTURE)) === false) {
				mkdir(dirname(self::FIXTURE));
			}

			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testReplayMatchesTheGoldenRecording()

	/**
	 * One seeded board, replayed directly and through the service twice.
	 *
	 * @param int $seed The case seed.
	 *
	 * @return array<string,mixed>
	 */
	private function recordCase(int $seed): array {
		mt_srand($seed);
		$board = $this->board();
		$from  = $this->day(sprintf('2026-09-%02d', mt_rand(1, 6)));
		$to    = $this->day(sprintf('2026-09-%02d', mt_rand(8, 30)));
		$now   = sprintf('2026-09-%02d %02d:00:00', mt_rand(10, 30), mt_rand(0, 23));

		$direct  = $this->replayDays(board: $board, window: [$from, $to, new DateTimeImmutable($now, new DateTimeZone('UTC'))]);
		$service = $this->service(board: $board, now: $now);
		$cold    = $service->forProject(objectService: $this->objectService(board: $board), projectId: 'p1', from: $from, to: $to);
		$warm    = $service->forProject(objectService: $this->objectService(board: $board), projectId: 'p1', from: $from, to: $to);

		return ['seed' => $seed, 'direct' => $direct, 'cold' => $cold, 'warmSha1' => sha1((string)json_encode($warm))];
	}//end recordCase()

	/**
	 * A board with a task finished exactly at midnight and one finished
	 * earlier today, replayed directly and through the service twice.
	 *
	 * @return array<string,mixed>
	 */
	private function recordEdgeCase(): array {
		$columns   = [['id' => 'c-a', 'title' => 'A', 'order' => 0], ['id' => 'c-b', 'title' => 'B', 'order' => 1]];
		$tasks     = [
			['id' => 'M', 'title' => 'Midnight', 'key' => 'PLN-1', 'created' => '2026-09-01T08:00:00+00:00', 'column' => 'c-b', 'status' => 'done', 'completedAt' => '2026-09-10T00:00:00+00:00'],
			['id' => 'E', 'title' => 'Early', 'key' => 'PLN-2', 'created' => '2026-09-02T08:00:00+00:00', 'column' => 'c-b', 'status' => 'done', 'completedAt' => '2026-09-20T03:00:00+00:00'],
		];
		$histories = [
			'M' => [
				['created' => '2026-09-01 08:00:00', 'changed' => ['column' => ['old' => null, 'new' => 'c-a'], 'status' => ['old' => null, 'new' => 'open']]],
				['created' => '2026-09-10 00:00:00', 'changed' => ['column' => ['old' => 'c-a', 'new' => 'c-b'], 'status' => ['old' => 'open', 'new' => 'done'], 'completedAt' => ['old' => null, 'new' => '2026-09-10T00:00:00+00:00']]],
			],
			'E' => [
				['created' => '2026-09-02 08:00:00', 'changed' => ['column' => ['old' => null, 'new' => 'c-a'], 'status' => ['old' => null, 'new' => 'in_progress']]],
				['created' => '2026-09-20 03:00:00', 'changed' => ['column' => ['old' => 'c-a', 'new' => 'c-b'], 'status' => ['old' => 'in_progress', 'new' => 'done'], 'completedAt' => ['old' => null, 'new' => '2026-09-20T03:00:00+00:00']]],
			],
		];
		$board     = ['columns' => $columns, 'tasks' => $tasks, 'histories' => $histories];
		$now       = '2026-09-20 15:00:00';
		$from      = $this->day('2026-09-01');
		$to        = $this->day('2026-09-20');
		$direct    = $this->replayDays(board: $board, window: [$from, $to, new DateTimeImmutable($now, new DateTimeZone('UTC'))]);
		$service   = $this->service(board: $board, now: $now);
		$cold      = $service->forProject(objectService: $this->objectService(board: $board), projectId: 'p1', from: $from, to: $to);
		$warm      = $service->forProject(objectService: $this->objectService(board: $board), projectId: 'p1', from: $from, to: $to);

		return ['seed' => 0, 'direct' => $direct, 'cold' => $cold, 'warm' => $warm];
	}//end recordEdgeCase()

	/**
	 * Call FlowReplay::replay() for a board and a window of from, to and now.
	 *
	 * @param array<string,array> $board  Columns, tasks and histories.
	 * @param array<int,DateTimeImmutable> $window From, to and now.
	 *
	 * @return array<string,mixed>
	 */
	private function replayDays(array $board, array $window): array {
		return (new FlowReplay())->replay(
			board: new FlowBoard(columns: $board['columns'], tasks: $board['tasks'], histories: $board['histories']),
			window: ['from' => $window[0], 'to' => $window[1], 'now' => $window[2]]
		);
	}//end replayDays()

	/**
	 * A seeded board: columns, tasks and their audit rows.
	 *
	 * @return array<string,array>
	 */
	private function board(): array {
		$columns = [];
		$count   = mt_rand(2, 5);
		$orders  = range(0, ($count - 1));
		shuffle($orders);
		foreach ($orders as $i => $order) {
			$columns[] = ['id' => 'c-' . $i, 'title' => 'Column ' . $i, 'order' => $order, 'color' => (mt_rand(0, 1) === 0 ? null : '#00' . $i)];
		}

		$tasks     = [];
		$histories = [];
		for ($t = 1; $t <= mt_rand(1, 6); $t++) {
			$id       = 'T' . $t;
			$created  = mt_rand(1, 12);
			$column   = 'c-' . mt_rand(0, $count);
			$status   = ['open', 'in_progress', 'done'][mt_rand(0, 2)];
			$finished = ($status === 'done' && mt_rand(0, 2) > 0 ? sprintf('2026-09-%02dT%02d:00:00+00:00', mt_rand($created, 28), mt_rand(0, 23)) : null);
			$tasks[]  = [
				'id'          => $id,
				'title'       => 'Task ' . $t,
				'key'         => 'PLN-' . $t,
				'created'     => sprintf('2026-09-%02dT08:00:00+00:00', $created),
				'column'      => $column,
				'status'      => $status,
				'completedAt' => $finished,
				'estimate'    => (mt_rand(0, 1) === 0 ? null : mt_rand(1, 8)),
			];
			$histories[$id] = $this->history(created: $created, count: $count);
		}

		return ['columns' => $columns, 'tasks' => $tasks, 'histories' => $histories];
	}//end board()

	/**
	 * Seeded audit rows for one task, oldest first, sometimes none.
	 *
	 * @param int $created The day the task was created.
	 * @param int $count   The number of columns.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function history(int $created, int $count): array {
		if (mt_rand(0, 3) === 0) {
			return [];
		}

		$rows   = [];
		$day    = $created;
		$column = null;
		$status = null;
		for ($r = 0; $r < mt_rand(1, 5) && $day <= 29; $r++) {
			$changed = [];
			$next    = 'c-' . mt_rand(0, $count);
			if (mt_rand(0, 3) > 0) {
				$changed['column'] = ['old' => $column, 'new' => $next];
				$column            = $next;
			}

			$state = ['open', 'in_progress', 'done'][mt_rand(0, 2)];
			if (mt_rand(0, 2) > 0) {
				$changed['status'] = ['old' => $status, 'new' => $state];
				$status            = $state;
			}

			if ($state === 'done' && mt_rand(0, 1) === 0) {
				$changed['completedAt'] = ['old' => null, 'new' => sprintf('2026-09-%02dT%02d:00:00+00:00', $day, mt_rand(0, 23))];
			}

			if ($changed === []) {
				$changed['description'] = ['old' => null, 'new' => 'x'];
			}

			$rows[] = ['created' => sprintf('2026-09-%02d %02d:00:00', $day, mt_rand(0, 23)), 'changed' => $changed];
			$day   += mt_rand(0, 4);
		}

		return $rows;
	}//end history()

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
	 * The service under test with an in-memory cache, reading the board's histories.
	 *
	 * @param array<string,array> $board The board.
	 * @param string              $now   The current UTC moment.
	 *
	 * @return FlowHistoryService
	 */
	private function service(array $board, string $now): FlowHistoryService {
		$histories = $board['histories'];
		$mapper    = $this->createMock(originalClassName: AuditTrailMapper::class);
		$mapper->method('findChangesForObject')->willReturnCallback(
			static fn (string $objectUuid, int $limit = 1000): array => ($histories[$objectUuid] ?? [])
		);

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
	 * An ObjectService double serving the board's tasks and columns.
	 *
	 * @param array<string,array> $board The board.
	 *
	 * @return object
	 */
	private function objectService(array $board): object {
		$rows = ['task' => $board['tasks'], 'column' => $board['columns']];
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
