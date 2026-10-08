<?php

/**
 * Planninq Flow History Service
 *
 * Reads what the Flow tab needs for one project: its columns, its tasks and
 * each task's OpenRegister audit trail, all server-side, and hands them to
 * FlowReplay. Finished days are cached per project and day, because a past
 * day cannot change; today is always recomputed.
 *
 * The caller has already found the project through ObjectService with RBAC
 * on (FlowController), and the tasks and columns are read with RBAC on too.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cumulative flow and task timings of a project, from its audit trail.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
 */
class FlowHistoryService {

	/**
	 * The longest window, in days.
	 *
	 * @var int
	 */
	public const MAX_DAYS = 180;

	/**
	 * Most tasks or columns read per project.
	 *
	 * @var int
	 */
	public const MAX_ROWS = 5000;

	/**
	 * Most audit rows read per task.
	 *
	 * @var int
	 */
	public const MAX_HISTORY = 1000;

	/**
	 * Days in the window when none is given.
	 *
	 * @var int
	 */
	public const DEFAULT_DAYS = 30;

	private const REGISTER = 'planninq';

	private const AUDIT_MAPPER = 'OCA\\OpenRegister\\Db\\AuditTrailMapper';

	private const CACHE_TTL = 30 * 86400;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container    Resolves OpenRegister's audit trail mapper
	 * @param ICacheFactory      $cacheFactory Cache for finished days
	 * @param ITimeFactory       $timeFactory  The current moment
	 * @param FlowReplay         $replay       The pure replay
	 * @param LoggerInterface    $logger       Logger
	 */
	public function __construct(
		private ContainerInterface $container,
		private ICacheFactory $cacheFactory,
		private ITimeFactory $timeFactory,
		private FlowReplay $replay,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The flow of one project over the days `$from` to `$to` (UTC midnights).
	 *
	 * @param object            $objectService OpenRegister's ObjectService
	 * @param string            $projectId     The project's UUID (already read with RBAC on)
	 * @param DateTimeImmutable $from          First day
	 * @param DateTimeImmutable $to            Last day
	 *
	 * @return array<string,mixed> columns, days, finished, summary, withoutHistory, fromCache
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.1
	 */
	public function forProject(object $objectService, string $projectId, DateTimeImmutable $from, DateTimeImmutable $to): array {
		$now = $this->timeFactory->now();
		$now = new DateTimeImmutable('@' . $now->getTimestamp());
		$today = $now->setTime(0, 0);
		$columns = $this->rows(objectService: $objectService, schema: 'column', projectId: $projectId);
		$tasks = $this->rows(objectService: $objectService, schema: 'task', projectId: $projectId);

		$cache = $this->cacheFactory->createDistributed('planninq-flow');
		$pastDays = $this->pastDays(from: $from, to: $to, today: $today);
		$cached = $this->cachedDays(cache: $cache, projectId: $projectId, days: $pastDays);

		if (count($cached) === count($pastDays)) {
			$days = $cached;
			if ($to >= $today) {
				$days = array_merge($days, $this->today(columns: $columns, tasks: $tasks, today: $today, now: $now, previous: end($cached)));
			}

			return $this->shape(projectId: $projectId, columns: $columns, days: $days, fromCache: count($cached));
		}

		$histories = $this->histories(tasks: $tasks);
		$days = $this->replay->replay(columns: $columns, tasks: $tasks, histories: $histories, from: $from, to: $to, now: $now);
		$this->storeDays(cache: $cache, projectId: $projectId, days: $days, today: $today);

		return $this->shape(projectId: $projectId, columns: $columns, days: $days, fromCache: 0);
	}//end forProject()

	/**
	 * The window as two UTC midnights, or null when a date is unreadable, the
	 * window is reversed or it is longer than MAX_DAYS days. Without dates it
	 * is the last DEFAULT_DAYS days up to today.
	 *
	 * @param string|null $from First day, Y-m-d
	 * @param string|null $to   Last day, Y-m-d
	 *
	 * @return DateTimeImmutable[]|null
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
	 */
	public function window(?string $from, ?string $to): ?array {
		$today = (new DateTimeImmutable('@' . $this->timeFactory->now()->getTimestamp()))->setTime(0, 0);
		$end = $this->date(value: $to, fallback: $today);
		$start = null;
		if ($end !== null) {
			$start = $this->date(value: $from, fallback: $end->modify('-' . (self::DEFAULT_DAYS - 1) . ' days'));
		}

		if ($start === null || $end === null || $start > $end) {
			return null;
		}

		if ((int)$start->diff($end)->days + 1 > self::MAX_DAYS) {
			return null;
		}

		return [$start, $end];
	}//end window()

	/**
	 * A Y-m-d date as a UTC midnight; the fallback when empty; null when it
	 * is not a calendar date.
	 *
	 * @param string|null       $value    The value
	 * @param DateTimeImmutable $fallback Used when the value is empty
	 *
	 * @return DateTimeImmutable|null
	 */
	private function date(?string $value, DateTimeImmutable $fallback): ?DateTimeImmutable {
		if ($value === null || $value === '') {
			return $fallback;
		}

		$parts = [];
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
			return null;
		}

		if (checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) === false) {
			return null;
		}

		return new DateTimeImmutable($value . ' 00:00:00', new DateTimeZone('UTC'));
	}//end date()

	/**
	 * Average, 85th percentile and slowest tasks over several projects' finished tasks.
	 *
	 * @param array<int,array<string,mixed>> $finished Finished rows from forProject()
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
	 */
	public function summarise(array $finished): array {
		return $this->replay->summarise(finished: $finished);
	}//end summarise()

	/**
	 * The response for one project.
	 *
	 * @param string                         $projectId The project
	 * @param array<int,array<string,mixed>> $columns   Its columns
	 * @param array<string,array>            $days      Day entries keyed by Y-m-d
	 * @param int                            $fromCache Days served from the cache
	 *
	 * @return array<string,mixed>
	 */
	private function shape(string $projectId, array $columns, array $days, int $fromCache): array {
		usort($columns, static fn (array $a, array $b): int => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
		$finished = [];
		$withoutHistory = 0;
		$list = [];
		foreach ($days as $date => $entry) {
			$list[] = ['date' => $date, 'counts' => $entry['counts']];
			$withoutHistory = max($withoutHistory, (int)$entry['withoutHistory']);
			foreach ($entry['finished'] as $row) {
				$row['finishedAt'] = gmdate('c', (int)$row['finishedAt']);
				$finished[] = $row;
			}
		}

		return [
			'projectId' => $projectId,
			'columns' => array_map(
				static fn (array $column): array => [
					'id' => (string)$column['id'],
					'title' => (string)($column['title'] ?? ''),
					'color' => ($column['color'] ?? null),
				],
				$columns
			),
			'days' => $list,
			'finished' => $finished,
			'summary' => $this->replay->summarise(finished: $finished),
			'withoutHistory' => $withoutHistory,
			'fromCache' => $fromCache,
		];
	}//end shape()

	/**
	 * Today, when every past day is cached: tasks per column now, and the
	 * tasks that may have finished today, whose histories alone are read.
	 *
	 * A task "may have finished today" when its completedAt is today or it
	 * is done without completedAt. Tasks without history do not change
	 * between yesterday and today, so yesterday's count of them carries over.
	 *
	 * @param array<int,array<string,mixed>> $columns  The columns
	 * @param array<int,array<string,mixed>> $tasks    The tasks
	 * @param DateTimeImmutable              $today    Today's midnight
	 * @param DateTimeImmutable              $now      Now
	 * @param array|false                    $previous Yesterday's cached entry, if any
	 *
	 * @return array<string,array>
	 */
	private function today(array $columns, array $tasks, DateTimeImmutable $today, DateTimeImmutable $now, array|false $previous): array {
		$start = $today->getTimestamp();
		$candidates = array_values(
			array_filter(
				$tasks,
				function (array $task) use ($start): bool {
					$completed = $this->replay->seconds(value: $task['completedAt'] ?? null);
					return ($completed !== null && $completed >= $start) || ($completed === null && ($task['status'] ?? null) === 'done');
				}
			)
		);
		$histories = $this->histories(tasks: $candidates);
		$days = $this->replay->replay(columns: $columns, tasks: $tasks, histories: $histories, from: $today, to: $today, now: $now);
		$date = $today->format('Y-m-d');
		$days[$date]['withoutHistory'] = (int)($previous['withoutHistory'] ?? 0);

		return $days;
	}//end today()

	/**
	 * The past days of the window, as Y-m-d.
	 *
	 * @param DateTimeImmutable $from  First day
	 * @param DateTimeImmutable $to    Last day
	 * @param DateTimeImmutable $today Today's midnight
	 *
	 * @return string[]
	 */
	private function pastDays(DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $today): array {
		$days = [];
		for ($day = $from; $day <= $to && $day < $today; $day = $day->modify('+1 day')) {
			$days[] = $day->format('Y-m-d');
		}

		return $days;
	}//end pastDays()

	/**
	 * The cached entries of the given days; a missing day is left out.
	 *
	 * @param ICache   $cache     The cache
	 * @param string   $projectId The project
	 * @param string[] $days      Y-m-d dates
	 *
	 * @return array<string,array>
	 */
	private function cachedDays(ICache $cache, string $projectId, array $days): array {
		$found = [];
		foreach ($days as $date) {
			$value = $cache->get($projectId . ':' . $date);
			if (is_string($value) === false) {
				continue;
			}

			$entry = json_decode($value, true);
			if (is_array($entry) === true) {
				$found[$date] = $entry;
			}
		}

		return $found;
	}//end cachedDays()

	/**
	 * Cache every finished day of a replay.
	 *
	 * @param ICache              $cache     The cache
	 * @param string              $projectId The project
	 * @param array<string,array> $days      Day entries keyed by Y-m-d
	 * @param DateTimeImmutable   $today     Today's midnight
	 *
	 * @return void
	 */
	private function storeDays(ICache $cache, string $projectId, array $days, DateTimeImmutable $today): void {
		foreach ($days as $date => $entry) {
			if ($date < $today->format('Y-m-d')) {
				$cache->set($projectId . ':' . $date, json_encode($entry, JSON_PRESERVE_ZERO_FRACTION), self::CACHE_TTL);
			}
		}
	}//end storeDays()

	/**
	 * The audit rows of each task, read server-side from OpenRegister.
	 *
	 * @param array<int,array<string,mixed>> $tasks The tasks
	 *
	 * @return array<string,array<int,array<string,mixed>>> Task id to rows, oldest first
	 */
	private function histories(array $tasks): array {
		if ($tasks === []) {
			return [];
		}

		try {
			$mapper = $this->container->get(self::AUDIT_MAPPER);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq flow: the audit trail is not available', ['exception' => $e->getMessage()]);
			return [];
		}

		$histories = [];
		foreach ($tasks as $task) {
			$histories[(string)$task['id']] = $mapper->findChangesForObject((string)$task['id'], self::MAX_HISTORY);
		}

		return $histories;
	}//end histories()

	/**
	 * A project's rows of one schema, read with RBAC on, as plain arrays with
	 * `id` and `created` set.
	 *
	 * @param object $objectService OpenRegister's ObjectService
	 * @param string $schema        `task` or `column`
	 * @param string $projectId     The project
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function rows(object $objectService, string $schema, string $projectId): array {
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: $schema,
			filters: ['project' => $projectId, '_limit' => self::MAX_ROWS]
		);
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$rows = [];
		foreach ((array)$results as $result) {
			$row = $this->plainRow(result: $result);
			if ($row['id'] !== '') {
				$rows[] = $row;
			}
		}

		return $rows;
	}//end rows()

	/**
	 * An ObjectEntity or array row as a plain array with `id` and `created`.
	 *
	 * ObjectEntity's accessors are magic (`__call`), so they are tested with
	 * is_callable(), not method_exists().
	 *
	 * @param mixed $result The row
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
	 */
	public function plainRow(mixed $result): array {
		if (is_object($result) === true) {
			return $this->plainEntity(entity: $result);
		}

		$data = (array)$result;
		$data['id'] = (string)($data['@self']['id'] ?? ($data['id'] ?? ''));
		$data['created'] = ($data['@self']['created'] ?? null);

		return $data;
	}//end plainRow()

	/**
	 * An ObjectEntity as a plain array with `id` and `created`.
	 *
	 * @param object $entity The entity
	 *
	 * @return array<string,mixed>
	 */
	private function plainEntity(object $entity): array {
		$data = [];
		if (is_callable([$entity, 'getObject']) === true) {
			$data = (array)$entity->getObject();
		}

		$data['id'] = (string)($data['id'] ?? '');
		if (is_callable([$entity, 'getUuid']) === true) {
			$data['id'] = (string)$entity->getUuid();
		}

		$data['created'] = null;
		$created = null;
		if (is_callable([$entity, 'getCreated']) === true) {
			$created = $entity->getCreated();
		}

		if ($created instanceof DateTimeInterface) {
			$data['created'] = $created->format('c');
		}

		return $data;
	}//end plainEntity()
}//end class
