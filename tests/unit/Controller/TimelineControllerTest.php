<?php

/**
 * Unit tests for TimelineController.
 *
 * Covers the read-only per-project timeline surface asserted by the
 * gantt-timeline-view spec: the scheduled/unscheduled split (dateless tasks are
 * returned flagged, not dropped), the RBAC gate (a caller who cannot read the
 * project gets 403 and no tasks), the dependency edges being echoed from
 * existing stored links (and filtered to the project's own tasks), date
 * windowing, the 401-when-unauthenticated guard, and the empty-project case.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\TimelineController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for TimelineController.
 */
class TimelineControllerTest extends TestCase
{

    /**
     * Mock request.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock user session.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Mock container.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

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
        $this->request     = $this->createMock(originalClassName: IRequest::class);
        $this->userSession = $this->createMock(originalClassName: IUserSession::class);
        $this->container   = $this->createMock(originalClassName: ContainerInterface::class);
        $this->logger      = $this->createMock(originalClassName: LoggerInterface::class);
    }//end setUp()

    /**
     * Build the controller with the current mocks.
     *
     * @return TimelineController
     */
    private function controller(): TimelineController
    {
        return new TimelineController(
            request: $this->request,
            userSession: $this->userSession,
            container: $this->container,
            logger: $this->logger,
        );
    }//end controller()

    /**
     * Set the current session user to a given uid.
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
     * An unauthenticated caller gets 401 and the container is never touched.
     *
     * @return void
     */
    public function testUnauthenticatedReturns401(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->container->expects(self::never())->method('get');

        $response = $this->controller()->forProject(projectId: 'p1');

        self::assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testUnauthenticatedReturns401()

    /**
     * When ObjectService cannot be resolved, the endpoint reports 503.
     *
     * @return void
     */
    public function testObjectServiceUnavailableReturns503(): void
    {
        $this->setUser('alice');
        $this->container->method('get')->willThrowException(new \RuntimeException('boom'));

        $response = $this->controller()->forProject(projectId: 'p1');

        self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
    }//end testObjectServiceUnavailableReturns503()

    /**
     * A project the caller cannot read (RBAC find returns null) yields 403 and
     * no task data — the RBAC gate is honoured.
     *
     * @return void
     */
    public function testInaccessibleProjectReturns403(): void
    {
        $this->setUser('mallory');
        // project find → null (RBAC denied); tasks never fetched.
        $objectService = $this->makeObjectService(projectsById: [], projectTasks: [], edges: []);
        $this->container->method('get')->willReturn($objectService);

        $response = $this->controller()->forProject(projectId: 'secret');

        self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        self::assertArrayNotHasKey('tasks', (array) $response->getData());
    }//end testInaccessibleProjectReturns403()

    /**
     * A permitted caller gets dated tasks positioned in time, and dateless
     * tasks split out as "unscheduled" rather than dropped.
     *
     * @return void
     */
    public function testScheduledAndUnscheduledSplit(): void
    {
        $this->setUser('alice');
        $objectService = $this->makeObjectService(
            projectsById: ['p1' => ['members' => ['alice']]],
            projectTasks: [
                ['@self' => ['id' => 't1'], 'title' => 'Dated', 'status' => 'open', 'startDate' => '2026-01-01', 'dueDate' => '2026-01-05', 'estimatedDuration' => 4],
                ['@self' => ['id' => 't2'], 'title' => 'Dateless', 'status' => 'open'],
                ['@self' => ['id' => 't3'], 'title' => 'DueOnly', 'status' => 'done', 'dueDate' => '2026-01-10'],
            ],
            edges: [],
        );
        $this->container->method('get')->willReturn($objectService);

        $response = $this->controller()->forProject(projectId: 'p1');
        self::assertSame(Http::STATUS_OK, $response->getStatus());

        $data = (array) $response->getData();
        $scheduledIds = array_column($data['tasks'], 'id');
        $unscheduledIds = array_column($data['unscheduled'], 'id');

        self::assertContains('t1', $scheduledIds);
        self::assertContains('t3', $scheduledIds);
        self::assertSame(['t2'], $unscheduledIds);

        // The dated task carries start/due/duration for bar rendering.
        $t1 = $data['tasks'][array_search('t1', $scheduledIds, true)];
        self::assertSame('2026-01-01', $t1['startDate']);
        self::assertSame('2026-01-05', $t1['dueDate']);
        self::assertSame(4, $t1['duration']);
        // Unscheduled rows carry no date/duration keys.
        self::assertArrayNotHasKey('startDate', $data['unscheduled'][0]);
    }//end testScheduledAndUnscheduledSplit()

    /**
     * Dependency edges are echoed from the stored links and filtered to the
     * project's own tasks — an edge whose blocker is a foreign task is dropped.
     *
     * @return void
     */
    public function testDependencyEdgesComeFromStoredLinks(): void
    {
        $this->setUser('alice');
        $objectService = $this->makeObjectService(
            projectsById: ['p1' => ['members' => ['alice']]],
            projectTasks: [
                ['@self' => ['id' => 't1'], 'title' => 'A', 'status' => 'open', 'startDate' => '2026-01-01', 'dueDate' => '2026-01-02'],
                ['@self' => ['id' => 't2'], 'title' => 'B', 'status' => 'open', 'startDate' => '2026-01-03', 'dueDate' => '2026-01-04'],
            ],
            edges: [
                ['@self' => ['id' => 'e1'], 'blocker' => 't1', 'blocked' => 't2'],
                // Foreign edge — blocker task belongs to another project → excluded.
                ['@self' => ['id' => 'e2'], 'blocker' => 'foreign', 'blocked' => 't2'],
            ],
        );
        $this->container->method('get')->willReturn($objectService);

        $data = (array) $this->controller()->forProject(projectId: 'p1')->getData();

        self::assertCount(1, $data['dependencies']);
        self::assertSame('e1', $data['dependencies'][0]['id']);
        self::assertSame('t1', $data['dependencies'][0]['blocker']);
        self::assertSame('t2', $data['dependencies'][0]['blocked']);
    }//end testDependencyEdgesComeFromStoredLinks()

    /**
     * A [from, to] window excludes scheduled tasks that fall entirely outside
     * it, while unscheduled tasks are always returned.
     *
     * @return void
     */
    public function testWindowingFiltersOutOfRangeTasks(): void
    {
        $this->setUser('alice');
        $objectService = $this->makeObjectService(
            projectsById: ['p1' => ['members' => ['alice']]],
            projectTasks: [
                ['@self' => ['id' => 'in'], 'title' => 'In', 'status' => 'open', 'startDate' => '2026-03-10', 'dueDate' => '2026-03-12'],
                ['@self' => ['id' => 'before'], 'title' => 'Before', 'status' => 'open', 'startDate' => '2026-01-01', 'dueDate' => '2026-01-02'],
                ['@self' => ['id' => 'after'], 'title' => 'After', 'status' => 'open', 'startDate' => '2026-12-01', 'dueDate' => '2026-12-02'],
                ['@self' => ['id' => 'none'], 'title' => 'None', 'status' => 'open'],
            ],
            edges: [],
        );
        $this->container->method('get')->willReturn($objectService);

        $data = (array) $this->controller()->forProject(projectId: 'p1', from: '2026-03-01', to: '2026-03-31')->getData();

        self::assertSame(['in'], array_column($data['tasks'], 'id'));
        self::assertSame(['none'], array_column($data['unscheduled'], 'id'));
    }//end testWindowingFiltersOutOfRangeTasks()

    /**
     * An accessible but empty project returns 200 with empty collections.
     *
     * @return void
     */
    public function testEmptyProjectReturnsEmptyTimeline(): void
    {
        $this->setUser('alice');
        $objectService = $this->makeObjectService(
            projectsById: ['p1' => ['members' => ['alice']]],
            projectTasks: [],
            edges: [],
        );
        $this->container->method('get')->willReturn($objectService);

        $data = (array) $this->controller()->forProject(projectId: 'p1')->getData();

        self::assertSame([], $data['tasks']);
        self::assertSame([], $data['unscheduled']);
        self::assertSame([], $data['dependencies']);
    }//end testEmptyProjectReturnsEmptyTimeline()

    /**
     * Build a stub ObjectService whose find/searchObjects behave per fixtures.
     *
     * find('project') returns the project entity when present (RBAC allowed) or
     * null (RBAC denied). searchObjects on the `task` schema returns the project
     * task rows; on the `dependency` schema returns the edge rows.
     *
     * @param array<string,array<string,mixed>> $projectsById Map of project UUID → project data (present = accessible).
     * @param array<int,array<string,mixed>>    $projectTasks Task rows returned for the task search.
     * @param array<int,array<string,mixed>>    $edges        Dependency edge rows.
     *
     * @return object
     */
    private function makeObjectService(array $projectsById, array $projectTasks, array $edges): object
    {
        return new class($projectsById, $projectTasks, $edges) {

            /** @var array<string,array<string,mixed>> */
            private array $projectsById;

            /** @var array<int,array<string,mixed>> */
            private array $projectTasks;

            /** @var array<int,array<string,mixed>> */
            private array $edges;

            private string $schema = '';

            /**
             * @param array<string,array<string,mixed>> $projectsById Project fixtures.
             * @param array<int,array<string,mixed>>    $projectTasks Task fixtures.
             * @param array<int,array<string,mixed>>    $edges        Edge fixtures.
             */
            public function __construct(array $projectsById, array $projectTasks, array $edges)
            {
                $this->projectsById = $projectsById;
                $this->projectTasks = $projectTasks;
                $this->edges        = $edges;
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
                if ($this->schema === 'project' && isset($this->projectsById[$id]) === true) {
                    return $this->entity($this->projectsById[$id]);
                }

                return null;
            }

            /**
             * @param array<string,mixed> $filters Optional filters (ignored — fixtures are pre-scoped).
             *
             * @return array<int,mixed>
             */
            public function searchObjects(array $filters=[]): array
            {
                if ($this->schema === 'task') {
                    return $this->projectTasks;
                }

                if ($this->schema === 'dependency') {
                    return $this->edges;
                }

                return [];
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
                };
            }
        };
    }//end makeObjectService()
}//end class
