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
use OCA\Planix\Service\DependencyService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for DependencyService.
 */
class DependencyServiceTest extends TestCase
{

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
     * Set up fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->container   = $this->createMock(originalClassName: ContainerInterface::class);
        $this->userSession = $this->createMock(originalClassName: IUserSession::class);
        $this->logger      = $this->createMock(originalClassName: LoggerInterface::class);
    }//end setUp()

    /**
     * Build the service with the current mocks.
     *
     * @return DependencyService
     */
    private function service(): DependencyService
    {
        return new DependencyService(
            container: $this->container,
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
    public function testSelfEdgeIsACycle(): void
    {
        self::assertTrue(DependencyService::wouldFormCycle([], 'A', 'A'));
        self::assertSame(['A', 'A'], DependencyService::cyclePath([], 'A', 'A'));
    }//end testSelfEdgeIsACycle()

    /**
     * Two-node cycle: A→B exists, adding B→A closes it.
     *
     * @return void
     */
    public function testTwoNodeCycle(): void
    {
        $edges = [['blocker' => 'A', 'blocked' => 'B']];
        // Proposed edge B→A (B blocks A) — B can already reach A via A→B? No.
        // The existing edge is A→B (A reaches B). Adding B→A: does A (blocked) reach B (blocker)? yes via A→B.
        self::assertTrue(DependencyService::wouldFormCycle($edges, 'B', 'A'));
        $path = DependencyService::cyclePath($edges, 'B', 'A');
        self::assertNotNull($path);
        self::assertSame('B', $path[0]);
        self::assertSame('B', $path[count($path) - 1]);
    }//end testTwoNodeCycle()

    /**
     * N-node transitive cycle: A→B, B→C exist; adding C→A closes A→B→C→A.
     *
     * @return void
     */
    public function testTransitiveCycleReportsPath(): void
    {
        $edges = [
            ['blocker' => 'A', 'blocked' => 'B'],
            ['blocker' => 'B', 'blocked' => 'C'],
        ];
        // Add C→A (C blocks A). A (blocked) reaches C (blocker) via A→B→C → cycle.
        $path = DependencyService::cyclePath($edges, 'C', 'A');
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
    public function testDiamondIsNotACycle(): void
    {
        $edges = [
            ['blocker' => 'A', 'blocked' => 'B'],
            ['blocker' => 'A', 'blocked' => 'C'],
        ];
        self::assertFalse(DependencyService::wouldFormCycle($edges, 'B', 'D'));

        $edges[] = ['blocker' => 'B', 'blocked' => 'D'];
        self::assertFalse(DependencyService::wouldFormCycle($edges, 'C', 'D'));
    }//end testDiamondIsNotACycle()

    /**
     * A legal new edge in an acyclic graph returns null.
     *
     * @return void
     */
    public function testLegalEdgeIsNotACycle(): void
    {
        $edges = [['blocker' => 'A', 'blocked' => 'B']];
        self::assertNull(DependencyService::cyclePath($edges, 'B', 'C'));
    }//end testLegalEdgeIsNotACycle()

    /**
     * DFS terminates even if the existing graph already contains a cycle
     * (e.g. a concurrent-write artifact) and still reports the new closure.
     *
     * @return void
     */
    public function testTerminatesOnPreExistingCycle(): void
    {
        $edges = [
            ['blocker' => 'A', 'blocked' => 'B'],
            ['blocker' => 'B', 'blocked' => 'A'],
        ];
        // Should not infinite-loop; any answer that terminates is correct here.
        self::assertIsBool(DependencyService::wouldFormCycle($edges, 'A', 'C'));
    }//end testTerminatesOnPreExistingCycle()

    // ── Pure blocked-state derivation ────────────────────────────────────────

    /**
     * An open blocker blocks; a done/cancelled blocker does not; dangling edges ignored.
     *
     * @return void
     */
    public function testDeriveBlockedTaskIds(): void
    {
        $edges = [
            ['blocker' => 'A', 'blocked' => 'B'],     // A open → B blocked
            ['blocker' => 'C', 'blocked' => 'D'],     // C done → D not blocked
            ['blocker' => 'GHOST', 'blocked' => 'E'], // dangling → ignored
        ];
        $status = ['A' => 'open', 'B' => 'open', 'C' => 'done', 'D' => 'open', 'E' => 'open'];

        self::assertSame(['B'], DependencyService::deriveBlockedTaskIds($edges, $status));
    }//end testDeriveBlockedTaskIds()

    /**
     * A cancelled blocker does not block, and de-duplication holds.
     *
     * @return void
     */
    public function testCancelledBlockerDoesNotBlock(): void
    {
        $edges = [
            ['blocker' => 'A', 'blocked' => 'C'],
            ['blocker' => 'B', 'blocked' => 'C'],
        ];
        $status = ['A' => 'cancelled', 'B' => 'cancelled', 'C' => 'open'];
        self::assertSame([], DependencyService::deriveBlockedTaskIds($edges, $status));
    }//end testCancelledBlockerDoesNotBlock()

    // ── create() validation chain ────────────────────────────────────────────

    /**
     * Self-edge is rejected before any ObjectService call.
     *
     * @return void
     */
    public function testCreateRejectsSelfEdge(): void
    {
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
    public function testCreateRejectsCrossProject(): void
    {
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
    public function testCreateRejectsNonMember(): void
    {
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
    public function testCreateRejectsDuplicate(): void
    {
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
    public function testCreateRejectsCycleWithPath(): void
    {
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
    public function testCreateSavesLegalEdge(): void
    {
        $saved = new class {
            /**
             * @return array<string,mixed>
             */
            public function jsonSerialize(): array
            {
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
    public function testRemoveEdgesForTaskCascades(): void
    {
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

    // ── Mock helpers ─────────────────────────────────────────────────────────

    /**
     * Set the current user on the session mock.
     *
     * @param string $uid The user id.
     *
     * @return void
     */
    private function setUser(string $uid): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
    }//end setUser()

    /**
     * Build a stub ObjectService whose find/searchObjects/saveObject/deleteObject
     * behave according to the supplied fixtures.
     *
     * @param array<string,array<string,mixed>> $tasks          Map of task UUID → task data.
     * @param array<string,array<string,mixed>> $projects       Map of project UUID → project data.
     * @param array<int,string>                 $projectTaskIds Task UUIDs returned by the project search.
     * @param array<int,array<string,mixed>>    $edges          Dependency edge rows (each with id/blocker/blocked).
     * @param object|null                        $saveReturn     Object returned by saveObject.
     * @param array<int,string>                 $deleteSink     Reference array recording deleted UUIDs.
     *
     * @return object
     */
    private function makeObjectService(
        array $tasks=[],
        array $projects=[],
        array $projectTaskIds=[],
        array $edges=[],
        ?object $saveReturn=null,
        array &$deleteSink=[],
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
             * @param array<string,array<string,mixed>> $tasks          Task fixtures.
             * @param array<string,array<string,mixed>> $projects       Project fixtures.
             * @param array<int,string>                 $projectTaskIds Project task id list.
             * @param array<int,array<string,mixed>>    $edges          Edge fixtures.
             * @param object|null                        $saveReturn     saveObject return.
             * @param array<int,string>                 $deleteSink     Deleted-id recorder.
             */
            public function __construct(array $tasks, array $projects, array $projectTaskIds, array $edges, ?object $saveReturn, array &$deleteSink)
            {
                $this->tasks          = $tasks;
                $this->projects       = $projects;
                $this->projectTaskIds = $projectTaskIds;
                $this->edges          = $edges;
                $this->saveReturn     = $saveReturn;
                $this->deleteSink     = &$deleteSink;
            }

            /**
             * @param string $register Register slug (ignored).
             *
             * @return void
             */
            public function setRegister(string $register): void
            {
            }

            /**
             * @param string $schema Schema slug.
             *
             * @return void
             */
            public function setSchema(string $schema): void
            {
                $this->schema = $schema;
            }

            /**
             * @param string $id The object UUID.
             *
             * @return object|null
             */
            public function find(string $id): ?object
            {
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
             * @param array<string,mixed> $filters Optional filters (project for tasks).
             *
             * @return array<int,mixed>
             */
            public function searchObjects(array $filters=[]): array
            {
                if ($this->schema === 'task') {
                    return array_map(fn($id) => ['@self' => ['id' => $id]], $this->projectTaskIds);
                }
                // dependency
                return $this->edges;
            }

            /**
             * @param array<string,mixed> $object   The object payload.
             * @param string              $register Register slug.
             * @param string              $schema   Schema slug.
             * @param bool                $_rbac    RBAC flag.
             *
             * @return object
             */
            public function saveObject(array $object, string $register, string $schema, bool $_rbac=true): object
            {
                if ($this->saveReturn !== null) {
                    return $this->saveReturn;
                }
                return $this->entity($object);
            }

            /**
             * @param string $register Register slug.
             * @param string $schema   Schema slug.
             * @param string $uuid     The object UUID.
             *
             * @return void
             */
            public function deleteObject(string $register, string $schema, string $uuid): void
            {
                $this->deleteSink[] = $uuid;
            }

            /**
             * Wrap a data array in a minimal entity object.
             *
             * @param array<string,mixed> $data The object data.
             *
             * @return object
             */
            private function entity(array $data): object
            {
                return new class($data) {

                    /** @var array<string,mixed> */
                    private array $data;

                    /**
                     * @param array<string,mixed> $data Object data.
                     */
                    public function __construct(array $data)
                    {
                        $this->data = $data;
                    }

                    /**
                     * @return array<string,mixed>
                     */
                    public function getObject(): array
                    {
                        return $this->data;
                    }

                    /**
                     * @return array<string,mixed>
                     */
                    public function jsonSerialize(): array
                    {
                        return $this->data;
                    }
                };
            }
        };
    }//end makeObjectService()
}//end class
