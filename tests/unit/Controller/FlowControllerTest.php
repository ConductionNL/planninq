<?php

/**
 * Tests for FlowController (portfolio-flow-reports 1.2): 403 on a project the
 * caller cannot read, the 180-day and 50-project limits, and a past day
 * served from the cache.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use DateTimeImmutable;
use DateTimeZone;
use OCA\OpenRegister\Db\AuditTrailMapper;
use OCA\Planninq\Controller\FlowController;
use OCA\Planninq\Service\FlowHistoryService;
use OCA\Planninq\Service\FlowReplay;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The flow endpoints' guards and limits.
 */
class FlowControllerTest extends TestCase {

	/** @var array<string,mixed> */
	private array $store = [];

	/** @var string[] */
	private array $historyReads = [];

	/**
	 * The controller with a fake ObjectService and an in-memory cache.
	 *
	 * @param object|null $objectService The ObjectService double
	 * @param bool        $signedIn      Whether a user is signed in
	 *
	 * @return FlowController
	 */
	private function controller(?object $objectService, bool $signedIn = true): FlowController {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($signedIn === true ? $this->createMock(originalClassName: IUser::class) : null);

		$mapper = $this->createMock(originalClassName: AuditTrailMapper::class);
		$mapper->method('findChangesForObject')->willReturnCallback(
			function (string $objectUuid, int $limit = 1000): array {
				$this->historyReads[] = $objectUuid;
				return [['created' => '2026-09-01 10:00:00', 'changed' => ['column' => ['old' => null, 'new' => 'c1'], 'status' => ['old' => null, 'new' => 'open']]]];
			}
		);
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($objectService, $mapper): object {
				if ($id === AuditTrailMapper::class) {
					return $mapper;
				}

				if ($objectService === null) {
					throw new \RuntimeException('OpenRegister is not installed');
				}

				return $objectService;
			}
		);

		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key) => ($this->store[$key] ?? null));
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl = 0): bool {
			$this->store[$key] = $value;
			return true;
		});
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-09-30 12:00:00', new DateTimeZone('UTC')));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new FlowController(
			request: $this->createMock(originalClassName: IRequest::class),
			userSession: $session,
			container: $container,
			flow: new FlowHistoryService(container: $container, cacheFactory: $factory, timeFactory: $time, replay: new FlowReplay(), logger: $logger),
			logger: $logger,
		);
	}//end controller()

	/**
	 * An ObjectService double with OpenRegister's find(), clearCurrents() and
	 * searchObjectsBySlug() signatures. Readable projects: those in $readable.
	 *
	 * @param string[]             $readable   Project ids the caller can read
	 * @param array<string,string> $portfolios Project id to portfolio id
	 *
	 * @return object
	 */
	private function objectService(array $readable, array $portfolios = []): object {
		return new class($readable, $portfolios) {
			/** @var array<int,array> */
			public array $searches = [];

			/**
			 * @param string[]             $readable   Readable project ids
			 * @param array<string,string> $portfolios Project id to portfolio id
			 */
			public function __construct(private array $readable, private array $portfolios) {
			}

			/**
			 * Forget the register and schema an earlier caller left.
			 *
			 * @return void
			 */
			public function clearCurrents(): void {
			}

			/**
			 * Find one object with RBAC on; null when the caller cannot read it.
			 *
			 * @param int|string      $id       The id
			 * @param array|null      $_extend  Extend
			 * @param bool            $files    Files
			 * @param string|int|null $register Register
			 * @param string|int|null $schema   Schema
			 * @param bool            $_rbac    RBAC on
			 *
			 * @return array|null
			 */
			public function find(int|string $id, ?array $_extend = [], bool $files = false, string|int|null $register = null, string|int|null $schema = null, bool $_rbac = true): ?array {
				if ($schema === 'projectPortfolio') {
					return $id === 'pf1' ? ['id' => 'pf1', 'title' => 'Ruimte'] : null;
				}

				return in_array((string)$id, $this->readable, true) === true ? ['id' => $id, 'title' => 'Project ' . $id] : null;
			}

			/**
			 * Rows of a schema with RBAC on.
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
				$this->searches[] = [$schemaSlug, $filters];
				if ($schemaSlug === 'project') {
					$rows = [];
					foreach ($this->portfolios as $projectId => $portfolioId) {
						if ($portfolioId === ($filters['portfolio'] ?? null) && in_array($projectId, $this->readable, true) === true) {
							$rows[] = ['@self' => ['id' => $projectId], 'title' => 'Project ' . $projectId, 'portfolio' => $portfolioId];
						}
					}

					return ['results' => $rows];
				}

				if ($schemaSlug === 'column') {
					return [['@self' => ['id' => 'c1'], 'title' => 'To do', 'order' => 0, 'project' => $filters['project']]];
				}

				return [['@self' => ['id' => 't-' . $filters['project'], 'created' => '2026-09-01T10:00:00+00:00'], 'title' => 'Task', 'column' => 'c1', 'status' => 'open', 'project' => $filters['project']]];
			}
		};
	}//end objectService()

	/**
	 * No signed-in user: 401.
	 *
	 * @return void
	 */
	public function testUnauthenticatedGets401(): void {
		$response = $this->controller(objectService: $this->objectService(readable: ['p1']), signedIn: false)->forProject(projectId: 'p1');

		self::assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testUnauthenticatedGets401()

	/**
	 * A project the caller cannot read: 403 and no counts.
	 *
	 * @return void
	 */
	public function testAnOutsiderGetsNoFlow(): void {
		$objectService = $this->objectService(readable: []);
		$response = $this->controller(objectService: $objectService)->forProject(projectId: 'p1');

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertArrayNotHasKey('days', $response->getData());
		self::assertSame([], $objectService->searches);
		self::assertSame([], $this->historyReads);
	}//end testAnOutsiderGetsNoFlow()

	/**
	 * A window longer than 180 days, a reversed window and a bad date: 400.
	 *
	 * @return void
	 */
	public function testTheWindowIsAtMost180Days(): void {
		$controller = $this->controller(objectService: $this->objectService(readable: ['p1']));

		self::assertSame(Http::STATUS_BAD_REQUEST, $controller->forProject(projectId: 'p1', from: '2026-01-01', to: '2026-09-30')->getStatus());
		self::assertSame(Http::STATUS_BAD_REQUEST, $controller->forProject(projectId: 'p1', from: '2026-09-30', to: '2026-09-01')->getStatus());
		self::assertSame(Http::STATUS_BAD_REQUEST, $controller->forProject(projectId: 'p1', from: 'yesterday')->getStatus());

		$ok = $controller->forProject(projectId: 'p1', from: '2026-04-04', to: '2026-09-30');
		self::assertSame(Http::STATUS_OK, $ok->getStatus());
		self::assertCount(180, $ok->getData()['days']);
	}//end testTheWindowIsAtMost180Days()

	/**
	 * Without dates the window is the last 30 days up to today.
	 *
	 * @return void
	 */
	public function testTheDefaultWindowIsTheLast30Days(): void {
		$data = $this->controller(objectService: $this->objectService(readable: ['p1']))->forProject(projectId: 'p1')->getData();

		self::assertSame(['from' => '2026-09-01', 'to' => '2026-09-30'], $data['window']);
		self::assertSame('2026-09-01', $data['days'][0]['date']);
		self::assertSame(['c1' => 1], $data['days'][0]['counts']);
	}//end testTheDefaultWindowIsTheLast30Days()

	/**
	 * The second read of a window serves its past days from the cache and
	 * reads no task history for them.
	 *
	 * @return void
	 */
	public function testAPastDayIsServedFromTheCache(): void {
		$controller = $this->controller(objectService: $this->objectService(readable: ['p1']));
		$controller->forProject(projectId: 'p1', from: '2026-09-01', to: '2026-09-29');
		self::assertSame(['t-p1'], $this->historyReads);

		$this->historyReads = [];
		$again = $controller->forProject(projectId: 'p1', from: '2026-09-01', to: '2026-09-29')->getData();

		self::assertSame(29, $again['fromCache']);
		self::assertSame([], $this->historyReads);
	}//end testAPastDayIsServedFromTheCache()

	/**
	 * A portfolio: only the projects the caller can read, at most 50, with
	 * one combined summary.
	 *
	 * @return void
	 */
	public function testAPortfolioCoversItsReadableProjectsUpTo50(): void {
		$portfolios = [];
		$readable = [];
		for ($i = 1; $i <= 53; $i++) {
			$portfolios['p' . $i] = 'pf1';
			$readable[] = 'p' . $i;
		}

		$portfolios['secret'] = 'pf1';
		$data = $this->controller(objectService: $this->objectService(readable: $readable, portfolios: $portfolios))
			->forPortfolio(portfolioId: 'pf1', from: '2026-09-28', to: '2026-09-29')
			->getData();

		self::assertCount(50, $data['projects']);
		self::assertSame(3, $data['skipped']);
		self::assertNotContains('secret', array_column($data['projects'], 'projectId'));
		self::assertSame('Project p1', $data['projects'][0]['title']);
		self::assertArrayHasKey('cycle', $data['summary']);
	}//end testAPortfolioCoversItsReadableProjectsUpTo50()

	/**
	 * A portfolio the caller cannot read: 403.
	 *
	 * @return void
	 */
	public function testAnUnreadablePortfolioGets403(): void {
		$response = $this->controller(objectService: $this->objectService(readable: ['p1']))->forPortfolio(portfolioId: 'nope');

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testAnUnreadablePortfolioGets403()

	/**
	 * OpenRegister missing: 503.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheAnswerIs503(): void {
		$response = $this->controller(objectService: null)->forProject(projectId: 'p1');

		self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
	}//end testWithoutOpenRegisterTheAnswerIs503()
}//end class
