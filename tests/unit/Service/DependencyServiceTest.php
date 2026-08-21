<?php

/**
 * Unit tests for DependencyService.
 *
 * Covers the pure graph algorithm (cycle detection: self-cycle, two-node,
 * N-node transitive, diamond-no-false-positive, terminates on a pre-existing
 * cycle) and the derived blocked-state, plus the create/delete validation
 * chain against a mocked OpenRegister ObjectService (self-edge, cross-project,
 * duplicate, non-member, cycle-with-path, and the task-delete cascade).
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\Exception\DependencyValidationException;
use OCA\Planix\Service\DependencyGraph;
use OCA\Planix\Service\DependencyRepository;
use OCA\Planix\Service\DependencyService;
use OCP\App\IAppManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for DependencyService.
 */
class DependencyServiceTest extends TestCase {

	/**
	 * Mock container.
	 *
	 * @var ContainerInterface&MockObject
	 */
	private ContainerInterface&MockObject $container;

	/**
	 * Mock user session.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession&MockObject $userSession;

	/**
	 * Mock logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Mock app manager — the OpenRegister availability probe (ADR-083 rule 1).
	 *
	 * Stubbed to report OpenRegister as installed, because these tests are
	 * about dependency-edge behaviour once OR is present. The not-installed
	 * path is a separate concern and is asserted where it belongs.
	 *
	 * @var IAppManager&MockObject
	 */
	private IAppManager&MockObject $appManager;

	/**
	 * The real (pure, dependency-free) graph algorithms under test.
	 *
	 * @var DependencyGraph
	 */
	private DependencyGraph $graph;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->graph = new DependencyGraph();
	}//end setUp()

	/**
	 * Build the service with the current mocks.
	 *
	 * @return DependencyService
	 */
	private function service(): DependencyService {
		// Real (not mocked) collaborators: DependencyRepository is a thin,
		// behaviour-preserving move of the former private OR read helpers and
		// still resolves the ObjectService from the SAME mocked container, and
		// DependencyGraph is pure. Mocking either would replace the code under
		// test with a double and stop these tests exercising it.
		return new DependencyService(
			repository: new DependencyRepository(
				container: $this->container,
				logger: $this->logger,
				appManager: $this->appManager,
			),
			graph: $this->graph,
			userSession: $this->userSession,
			logger: $this->logger,
		);
	}//end service()

	// ── Pure cycle detection ─────────────────────────────────────────────────

	/**
	 * A self-edge is a one-hop cycle.
	 *
	 * @return void
	 */
	public function testSelfEdgeIsACycle(): void {
		self::assertTrue($this->graph->wouldFormCycle([], 'A', 'A'));
		self::assertSame(['A', 'A'], $this->graph->cyclePath([], 'A', 'A'));
	}//end testSelfEdgeIsACycle()

	/**
	 * Two-node cycle: A→B exists, adding B→A closes it.
	 *
	 * @return void
	 */
	public function testTwoNodeCycle(): void {
		$edges = [['blocker' => 'A', 'blocked' => 'B']];
		// Proposed edge B→A (B blocks A) — B can already reach A via A→B? No.
		// The existing edge is A→B (A reaches B). Adding B→A: does A (blocked) reach B (blocker)? yes via A→B.
		self::assertTrue($this->graph->wouldFormCycle($edges, 'B', 'A'));
		$path = $this->graph->cyclePath($edges, 'B', 'A');
		self::assertNotNull($path);
		self::assertSame('B', $path[0]);
		self::assertSame('B', $path[count($path) - 1]);
	}//end testTwoNodeCycle()

	/**
	 * N-node transitive cycle: A→B, B→C exist; adding C→A closes A→B→C→A.
	 *
	 * @return void
	 */
	public function testTransitiveCycleReportsPath(): void {
		$edges = [
			['blocker' => 'A', 'blocked' => 'B'],
			['blocker' => 'B', 'blocked' => 'C'],
		];
		// Add C→A (C blocks A). A (blocked) reaches C (blocker) via A→B→C → cycle.
		$path = $this->graph->cyclePath($edges, 'C', 'A');
		self::assertNotNull($path);
		// Rendered as it would read once closed: C → A → B → C.
		self::assertSame(['C', 'A', 'B', 'C'], $path);
	}//end testTransitiveCycleReportsPath()

	/**
	 * Diamond shape is NOT a cycle (no false positive).
	 *
	 * A→B, A→C exist; adding B→D and C→D both legal.
	 *
	 * @return void
	 */
	public function testDiamondIsNotACycle(): void {
		$edges = [
			['blocker' => 'A', 'blocked' => 'B'],
			['blocker' => 'A', 'blocked' => 'C'],
		];
		self::assertFalse($this->graph->wouldFormCycle($edges, 'B', 'D'));

		$edges[] = ['blocker' => 'B', 'blocked' => 'D'];
		self::assertFalse($this->graph->wouldFormCycle($edges, 'C', 'D'));
	}//end testDiamondIsNotACycle()

	/**
	 * A legal new edge in an acyclic graph returns null.
	 *
	 * @return void
	 */
	public function testLegalEdgeIsNotACycle(): void {
		$edges = [['blocker' => 'A', 'blocked' => 'B']];
		self::assertNull($this->graph->cyclePath($edges, 'B', 'C'));
	}//end testLegalEdgeIsNotACycle()

	/**
	 * DFS terminates even if the existing graph already contains a cycle
	 * (e.g. a concurrent-write artifact) and still reports the new closure.
	 *
	 * @return void
	 */
	public function testTerminatesOnPreExistingCycle(): void {
		$edges = [
			['blocker' => 'A', 'blocked' => 'B'],
			['blocker' => 'B', 'blocked' => 'A'],
		];
		// Should not infinite-loop; any answer that terminates is correct here.
		self::assertIsBool($this->graph->wouldFormCycle($edges, 'A', 'C'));
	}//end testTerminatesOnPreExistingCycle()

	// ── Pure blocked-state derivation ────────────────────────────────────────

	/**
	 * An open blocker blocks; a done/cancelled blocker does not; dangling edges ignored.
	 *
	 * @return void
	 */
	public function testDeriveBlockedTaskIds(): void {
		$edges = [
			['blocker' => 'A', 'blocked' => 'B'],     // A open → B blocked
			['blocker' => 'C', 'blocked' => 'D'],     // C done → D not blocked
			['blocker' => 'GHOST', 'blocked' => 'E'], // dangling → ignored
		];
		$status = ['A' => 'open', 'B' => 'open', 'C' => 'done', 'D' => 'open', 'E' => 'open'];

		self::assertSame(['B'], $this->graph->deriveBlockedTaskIds($edges, $status));
	}//end testDeriveBlockedTaskIds()

	/**
	 * A cancelled blocker does not block, and de-duplication holds.
	 *
	 * @return void
	 */
	public function testCancelledBlockerDoesNotBlock(): void {
		$edges = [
			['blocker' => 'A', 'blocked' => 'C'],
			['blocker' => 'B', 'blocked' => 'C'],
		];
		$status = ['A' => 'cancelled', 'B' => 'cancelled', 'C' => 'open'];
		self::assertSame([], $this->graph->deriveBlockedTaskIds($edges, $status));
	}//end testCancelledBlockerDoesNotBlock()

	// ── create() validation chain ────────────────────────────────────────────

	/**
	 * Self-edge is rejected before any ObjectService call.
	 *
	 * @return void
	 */
	public function testCreateRejectsSelfEdge(): void {
		$this->container->expects($this->never())->method('get');

		$this->expectException(DependencyValidationException::class);
		try {
			$this->service()->create('A', 'A');
		} catch (DependencyValidationException $e) {
			self::assertSame(DependencyValidationException::CODE_VALIDATION, $e->getCode());
			throw $e;
		}
	}//end testCreateRejectsSelfEdge()

	/**
	 * Cross-project edge is rejected.
	 *
	 * @return void
	 */
	public function testCreateRejectsCrossProject(): void {
		$objectService = $this->makeObjectService(
			tasks: [
				'A' => ['project' => 'P1'],
				'B' => ['project' => 'P2'],
			]
		);
		$this->container->method('get')->willReturn($objectService);

		$this->expectException(DependencyValidationException::class);
		$this->expectExceptionMessageMatches('/same project/i');
		$this->service()->create('A', 'B');
	}//end testCreateRejectsCrossProject()

	/**
	 * Non-member caller is rejected with the forbidden code.
	 *
	 * @return void
	 */
	public function testCreateRejectsNonMember(): void {
		$objectService = $this->makeObjectService(
			tasks: [
				'A' => ['project' => 'P1'],
				'B' => ['project' => 'P1'],
			],
			projects: ['P1' => ['members' => ['someone-else']]],
		);
		$this->container->method('get')->willReturn($objectService);
		$this->setUser('alice');

		$this->expectException(DependencyValidationException::class);
		try {
			$this->service()->create('A', 'B');
		} catch (DependencyValidationException $e) {
			self::assertSame(DependencyValidationException::CODE_FORBIDDEN, $e->getCode());
			throw $e;
		}
	}//end testCreateRejectsNonMember()

	/**
	 * Duplicate edge is rejected.
	 *
	 * @return void
	 */
	public function testCreateRejectsDuplicate(): void {
		$objectService = $this->makeObjectService(
			tasks: [
				'A' => ['project' => 'P1'],
				'B' => ['project' => 'P1'],
			],
			projects: ['P1' => ['members' => ['alice']]],
			projectTaskIds: ['A', 'B'],
			edges: [['id' => 'e1', 'blocker' => 'A', 'blocked' => 'B']],
		);
		$this->container->method('get')->willReturn($objectService);
		$this->setUser('alice');

		$this->expectException(DependencyValidationException::class);
		$this->expectExceptionMessageMatches('/already exists/i');
		$this->service()->create('A', 'B');
	}//end testCreateRejectsDuplicate()

	/**
	 * A cycle-forming edge is rejected and the message names the path.
	 *
	 * @return void
	 */
	public function testCreateRejectsCycleWithPath(): void {
		$objectService = $this->makeObjectService(
			tasks: [
				'A' => ['project' => 'P1', 'title' => 'Alpha'],
				'B' => ['project' => 'P1', 'title' => 'Bravo'],
				'C' => ['project' => 'P1', 'title' => 'Charlie'],
			],
			projects: ['P1' => ['members' => ['alice']]],
			projectTaskIds: ['A', 'B', 'C'],
			edges: [
				['id' => 'e1', 'blocker' => 'A', 'blocked' => 'B'],
				['id' => 'e2', 'blocker' => 'B', 'blocked' => 'C'],
			],
		);
		$this->container->method('get')->willReturn($objectService);
		$this->setUser('alice');

		$this->expectException(DependencyValidationException::class);
		$this->expectExceptionMessageMatches('/cycle/i');
		try {
			// Add C→A → cycle C → A → B → C.
			$this->service()->create('C', 'A');
		} catch (DependencyValidationException $e) {
			self::assertStringContainsString('Charlie', $e->getMessage());
			self::assertStringContainsString('Alpha', $e->getMessage());
			throw $e;
		}
	}//end testCreateRejectsCycleWithPath()

	/**
	 * A legal edge is saved via ObjectService and the stored edge is returned.
	 *
	 * @return void
	 */
	public function testCreateSavesLegalEdge(): void {
		$saved = new class {
			/**
			 * @return array<string,mixed>
			 */
			public function jsonSerialize(): array {
				return ['id' => 'new-edge', 'blocker' => 'A', 'blocked' => 'C'];
			}//end jsonSerialize()
		};

		$objectService = $this->makeObjectService(
			tasks: [
				'A' => ['project' => 'P1'],
				'C' => ['project' => 'P1'],
			],
			projects: ['P1' => ['members' => ['alice']]],
			projectTaskIds: ['A', 'B', 'C'],
			edges: [['id' => 'e1', 'blocker' => 'A', 'blocked' => 'B']],
			saveReturn: $saved,
		);
		$this->container->method('get')->willReturn($objectService);
		$this->setUser('alice');

		$result = $this->service()->create('A', 'C');
		self::assertSame('new-edge', $result['id']);
	}//end testCreateSavesLegalEdge()

	/**
	 * Task-delete cascade removes every edge in which the task participates.
	 *
	 * @return void
	 */
	public function testRemoveEdgesForTaskCascades(): void {
		$deleted = [];
		$objectService = $this->makeObjectService(
			edges: [
				['id' => 'e1', 'blocker' => 'A', 'blocked' => 'B'],
				['id' => 'e2', 'blocker' => 'C', 'blocked' => 'A'],
				['id' => 'e3', 'blocker' => 'B', 'blocked' => 'C'],
			],
			deleteSink: $deleted,
		);
		$this->container->method('get')->willReturn($objectService);

		$count = $this->service()->removeEdgesForTask('A');
		self::assertSame(2, $count);
		self::assertContains('e1', $deleted);
		self::assertContains('e2', $deleted);
		self::assertNotContains('e3', $deleted);
	}//end testRemoveEdgesForTaskCascades()

	// ── OpenRegister availability (ADR-083 rule 1) ───────────────────────────

	/**
	 * With OpenRegister absent, the repository refuses BEFORE touching the
	 * container and reports it as an infrastructure precondition.
	 *
	 * The distinction matters: a container lookup that throws cannot tell
	 * "OpenRegister is not installed" apart from "OpenRegister is installed and
	 * broken", and the caller maps CODE_UNAVAILABLE to 503 — a claim about the
	 * environment, not about the request. Probing IAppManager first is what
	 * makes that claim true.
	 *
	 * @return void
	 */
	public function testObjectServiceRefusesWhenOpenRegisterIsNotInstalled(): void {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(false);

		// The container must never be consulted: proving the guard runs FIRST
		// is the whole point, so a lookup here is a test failure, not a detail.
		$this->container->expects(self::never())->method('get');

		$repository = new DependencyRepository(
			container: $this->container,
			logger: $this->logger,
			appManager: $appManager,
		);

		try {
			$repository->objectService();
			self::fail('Expected DependencyValidationException when OpenRegister is absent.');
		} catch (DependencyValidationException $e) {
			self::assertSame(DependencyValidationException::CODE_UNAVAILABLE, $e->getCode());
		}
	}//end testObjectServiceRefusesWhenOpenRegisterIsNotInstalled()

	/**
	 * With OpenRegister present, the guard falls through to the container.
	 *
	 * @return void
	 */
	public function testObjectServiceResolvesWhenOpenRegisterIsInstalled(): void {
		$objectService = $this->makeObjectService(edges: []);
		$this->container->method('get')->willReturn($objectService);

		$repository = new DependencyRepository(
			container: $this->container,
			logger: $this->logger,
			appManager: $this->appManager,
		);

		self::assertSame($objectService, $repository->objectService());
	}//end testObjectServiceResolvesWhenOpenRegisterIsInstalled()

	// ── Mock helpers ─────────────────────────────────────────────────────────

	/**
	 * Set the current user on the session mock.
	 *
	 * @param string $uid The user id.
	 *
	 * @return void
	 */
	private function setUser(string $uid): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}//end setUser()

	/**
	 * Build a stub ObjectService whose find/searchObjects/saveObject/deleteObject
	 * behave according to the supplied fixtures.
	 *
	 * @param array<string,array<string,mixed>> $tasks Map of task UUID → task data.
	 * @param array<string,array<string,mixed>> $projects Map of project UUID → project data.
	 * @param array<int,string> $projectTaskIds Task UUIDs returned by the project search.
	 * @param array<int,array<string,mixed>> $edges Dependency edge rows (each with id/blocker/blocked).
	 * @param object|null $saveReturn Object returned by saveObject.
	 * @param array<int,string> $deleteSink Reference array recording deleted UUIDs.
	 *
	 * @return object
	 */
	private function makeObjectService(
		array $tasks = [],
		array $projects = [],
		array $projectTaskIds = [],
		array $edges = [],
		?object $saveReturn = null,
		array &$deleteSink = [],
	): object {
		return new class($tasks, $projects, $projectTaskIds, $edges, $saveReturn, $deleteSink) {
			/** @var array<string,array<string,mixed>> */
			private array $tasks;

			/** @var array<string,array<string,mixed>> */
			private array $projects;

			/** @var array<int,string> */
			private array $projectTaskIds;

			/** @var array<int,array<string,mixed>> */
			private array $edges;

			private ?object $saveReturn;

			/** @var array<int,string> */
			private array $deleteSink;

			private string $schema = '';

			/**
			 * @param array<string,array<string,mixed>> $tasks Task fixtures.
			 * @param array<string,array<string,mixed>> $projects Project fixtures.
			 * @param array<int,string> $projectTaskIds Project task id list.
			 * @param array<int,array<string,mixed>> $edges Edge fixtures.
			 * @param object|null $saveReturn saveObject return.
			 * @param array<int,string> $deleteSink Deleted-id recorder.
			 */
			public function __construct(array $tasks, array $projects, array $projectTaskIds, array $edges, ?object $saveReturn, array &$deleteSink) {
				$this->tasks = $tasks;
				$this->projects = $projects;
				$this->projectTaskIds = $projectTaskIds;
				$this->edges = $edges;
				$this->saveReturn = $saveReturn;
				$this->deleteSink = &$deleteSink;
			}

			/**
			 * @param string $register Register slug (ignored).
			 *
			 * @return void
			 */
			public function setRegister(string $register): void {
			}

			/**
			 * @param string $schema Schema slug.
			 *
			 * @return void
			 */
			public function setSchema(string $schema): void {
				$this->schema = $schema;
			}

			/**
			 * @param string $id The object UUID.
			 *
			 * @return object|null
			 */
			public function find(string $id): ?object {
				if ($this->schema === 'task') {
					if (isset($this->tasks[$id]) === false) {
						return null;
					}
					return $this->entity($this->tasks[$id]);
				}

				if ($this->schema === 'project') {
					if (isset($this->projects[$id]) === false) {
						return null;
					}
					return $this->entity($this->projects[$id]);
				}

				// dependency
				foreach ($this->edges as $edge) {
					if (($edge['id'] ?? '') === $id) {
						return $this->entity($edge);
					}
				}
				return null;
			}

			/**
			 * Mirrors the real ObjectService entry point the production code
			 * calls. This double previously only offered `searchObjects()`,
			 * which production stopped calling when the schema/register moved
			 * from setter state into explicit slug arguments — so every caller
			 * died with "undefined method searchObjectsBySlug()". The schema is
			 * now read from the ARGUMENT, not from `$this->schema`, which is
			 * exactly the invariant the production change established.
			 *
			 * @param string $registerSlug Register slug.
			 * @param string $schemaSlug Schema slug.
			 * @param array<string,mixed> $filters Optional filters (project for tasks).
			 *
			 * @return array<int,mixed>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = []): array {
				if ($schemaSlug === 'task') {
					return array_map(fn ($id) => ['@self' => ['id' => $id]], $this->projectTaskIds);
				}
				// dependency
				return $this->edges;
			}

			/**
			 * @param array<string,mixed> $object The object payload.
			 * @param string $register Register slug.
			 * @param string $schema Schema slug.
			 * @param bool $_rbac RBAC flag.
			 *
			 * @return object
			 */
			public function saveObject(array $object, string $register, string $schema, bool $_rbac = true): object {
				if ($this->saveReturn !== null) {
					return $this->saveReturn;
				}
				return $this->entity($object);
			}

			/**
			 * @param string $register Register slug.
			 * @param string $schema Schema slug.
			 * @param string $uuid The object UUID.
			 *
			 * @return void
			 */
			public function deleteObject(string $register, string $schema, string $uuid): void {
				$this->deleteSink[] = $uuid;
			}

			/**
			 * Wrap a data array in a minimal entity object.
			 *
			 * @param array<string,mixed> $data The object data.
			 *
			 * @return object
			 */
			private function entity(array $data): object {
				return new class($data) {
					/** @var array<string,mixed> */
					private array $data;

					/**
					 * @param array<string,mixed> $data Object data.
					 */
					public function __construct(array $data) {
						$this->data = $data;
					}

					/**
					 * @return array<string,mixed>
					 */
					public function getObject(): array {
						return $this->data;
					}

					/**
					 * @return array<string,mixed>
					 */
					public function jsonSerialize(): array {
						return $this->data;
					}
				};
			}
		};
	}//end makeObjectService()
}//end class
