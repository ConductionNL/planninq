<?php

/**
 * Planix Dependency Service
 *
 * Server-side management of directed task-to-task dependency edges
 * (blocker → blocked) with the one invariant OpenRegister cannot enforce:
 * the dependency graph of a project stays acyclic (a DAG).
 *
 * Reads (lists, board badge derivation) go straight to the OpenRegister API
 * per ADR-022. Only create/delete route through this service, because edge
 * creation needs graph validation (self/duplicate/cross-project/cycle) that
 * is genuine domain logic, not an ObjectService pass-through.
 *
 * @category Service
 * @package  OCA\Planix\Service
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

namespace OCA\Planix\Service;

use OCA\Planix\Exception\DependencyValidationException;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Domain service for task dependency edges.
 *
 * The graph algorithm (cycle detection, blocked-state derivation) is kept
 * pure and side-effect free so it can be unit tested without OpenRegister:
 * {@see wouldFormCycle()} and {@see deriveBlockedTaskIds()} operate purely on
 * edge/task arrays. The public {@see create()} / {@see delete()} methods wrap
 * them with the ObjectService persistence and the membership (IDOR) guard.
 *
 * Exceeds PHPMD's class-complexity threshold (83 vs 50): the bulk is the pure
 * graph algorithm (cycle detection and blocked-state derivation) described
 * above. Its branches are the algorithm, and keeping them in one unit-testable
 * class is precisely what makes the service provable without OpenRegister.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
class DependencyService
{

    /**
     * OpenRegister register slug owning the planix schemas.
     *
     * @var string
     */
    private const REGISTER = 'planix';

    /**
     * OpenRegister schema slug for dependency edges.
     *
     * @var string
     */
    private const SCHEMA = 'dependency';

    /**
     * Task statuses that count as "resolved" — a blocker in one of these
     * states no longer blocks anything.
     *
     * @var array<int,string>
     */
    private const RESOLVED_STATUSES = ['done', 'cancelled'];

    /**
     * Constructor for the DependencyService.
     *
     * @param ContainerInterface $container   The DI container (resolves the OR ObjectService at runtime).
     * @param IUserSession       $userSession The current user session (membership guard).
     * @param LoggerInterface    $logger      The logger.
     *
     * @return void
     */
    public function __construct(
        private ContainerInterface $container,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {

    }//end __construct()

    /**
     * Resolve the OpenRegister ObjectService from the container.
     *
     * Resolved by FQCN string so planix carries no compile-time dependency on
     * the openregister package (ADR-022).
     *
     * @return object The OR ObjectService.
     *
     * @throws DependencyValidationException When OpenRegister is unavailable.
     */
    private function objectService(): object
    {
        try {
            return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService unavailable', ['exception' => $e->getMessage()]);
            throw new DependencyValidationException(
                message: 'OpenRegister is not available.',
                code: DependencyValidationException::CODE_UNAVAILABLE
            );
        }
    }//end objectService()

    /**
     * Create a directed dependency edge (blocker → blocked).
     *
     * Validation chain (order matters — cheapest/safest first):
     *   1. distinct UUIDs (no self-edge);
     *   2. both tasks exist and share a project;
     *   3. the caller is a member of that project (IDOR guard);
     *   4. the edge is not already present (no duplicate);
     *   5. adding it would not close a cycle (DFS over the project's edges).
     * Only after all five pass is the edge saved through ObjectService.
     *
     * @param string $blocker UUID of the blocking task.
     * @param string $blocked UUID of the blocked task.
     *
     * @return array<string,mixed> The serialised stored edge.
     *
     * @throws DependencyValidationException On any validation failure (carries an HTTP-mappable code).
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function create(string $blocker, string $blocked): array
    {
        // 1. No self-edge.
        $this->assertDistinctTasks(blocker: $blocker, blocked: $blocked);

        $objectService = $this->objectService();

        // 2. Both tasks exist and share a project.
        $projectId = $this->resolveSharedProjectId(objectService: $objectService, blocker: $blocker, blocked: $blocked);

        // 3. Membership guard (IDOR) — caller must be a member of the project.
        $this->assertProjectMember(objectService: $objectService, projectId: $projectId);

        // 4. + 5. No duplicate edge; no cycle. Loads the project edges once.
        $edges = $this->fetchProjectEdges(objectService: $objectService, projectId: $projectId);
        $this->assertEdgeIsValid(objectService: $objectService, edges: $edges, blocker: $blocker, blocked: $blocked);

        $saved = $objectService->saveObject(
            object: ['blocker' => $blocker, 'blocked' => $blocked],
            register: self::REGISTER,
            schema: self::SCHEMA,
            _rbac: false
        );

        $this->logger->info(
            'Planix: dependency created',
            ['blocker' => $blocker, 'blocked' => $blocked, 'project' => $projectId]
        );

        return $saved->jsonSerialize();

    }//end create()

    /**
     * Assert the two task ids are both present and distinct (no self-edge).
     *
     * @param string $blocker UUID of the blocking task.
     * @param string $blocked UUID of the blocked task.
     *
     * @return void
     *
     * @throws DependencyValidationException When empty or equal.
     */
    private function assertDistinctTasks(string $blocker, string $blocked): void
    {
        if ($blocker === '' || $blocked === '') {
            throw new DependencyValidationException(
                message: 'Both blocker and blocked task are required.',
                code: DependencyValidationException::CODE_VALIDATION
            );
        }

        if ($blocker === $blocked) {
            throw new DependencyValidationException(
                message: 'A task cannot depend on itself.',
                code: DependencyValidationException::CODE_VALIDATION
            );
        }

    }//end assertDistinctTasks()

    /**
     * Verify both tasks exist and belong to the same project; return that project id.
     *
     * @param object $objectService The OR ObjectService.
     * @param string $blocker       UUID of the blocking task.
     * @param string $blocked       UUID of the blocked task.
     *
     * @return string The shared project UUID.
     *
     * @throws DependencyValidationException When a task is missing or the projects differ.
     */
    private function resolveSharedProjectId(object $objectService, string $blocker, string $blocked): string
    {
        $blockerTask = $this->fetchTask(objectService: $objectService, taskId: $blocker);
        $blockedTask = $this->fetchTask(objectService: $objectService, taskId: $blocked);

        if ($blockerTask === null || $blockedTask === null) {
            throw new DependencyValidationException(
                message: 'One or both tasks could not be found.',
                code: DependencyValidationException::CODE_NOT_FOUND
            );
        }

        $projectId = (string) ($blockerTask['project'] ?? '');
        if ($projectId === '' || $projectId !== (string) ($blockedTask['project'] ?? '')) {
            throw new DependencyValidationException(
                message: 'Dependencies can only link tasks within the same project.',
                code: DependencyValidationException::CODE_VALIDATION
            );
        }

        return $projectId;

    }//end resolveSharedProjectId()

    /**
     * Assert the proposed edge is neither a duplicate nor cycle-forming.
     *
     * @param object                         $objectService The OR ObjectService (for path rendering).
     * @param array<int,array<string,mixed>> $edges         The project's existing edges.
     * @param string                         $blocker       UUID of the blocking task.
     * @param string                         $blocked       UUID of the blocked task.
     *
     * @return void
     *
     * @throws DependencyValidationException On a duplicate or a cycle (message names the path).
     */
    private function assertEdgeIsValid(object $objectService, array $edges, string $blocker, string $blocked): void
    {
        foreach ($edges as $edge) {
            if ((string) ($edge['blocker'] ?? '') === $blocker
                && (string) ($edge['blocked'] ?? '') === $blocked
            ) {
                throw new DependencyValidationException(
                    message: 'This dependency already exists.',
                    code: DependencyValidationException::CODE_VALIDATION
                );
            }
        }

        $path = self::cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked);
        if ($path !== null) {
            $rendered = $this->renderPath(objectService: $objectService, path: $path);
            throw new DependencyValidationException(
                message: 'This dependency would create a cycle: '.$rendered,
                code: DependencyValidationException::CODE_VALIDATION
            );
        }

    }//end assertEdgeIsValid()

    /**
     * Delete a dependency edge after a project-membership guard.
     *
     * @param string $id UUID of the dependency edge to delete.
     *
     * @return void
     *
     * @throws DependencyValidationException When not found or the caller is not a project member.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function delete(string $id): void
    {
        $objectService = $this->objectService();

        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema(self::SCHEMA);
        $entity = $objectService->find(id: $id);
        if ($entity === null) {
            throw new DependencyValidationException(
                message: 'Dependency not found.',
                code: DependencyValidationException::CODE_NOT_FOUND
            );
        }

        $edge        = $entity->getObject();
        $blockerTask = $this->fetchTask(objectService: $objectService, taskId: (string) ($edge['blocker'] ?? ''));

        $projectId = '';
        if ($blockerTask !== null) {
            $projectId = (string) ($blockerTask['project'] ?? '');
        }

        // Membership guard. When the blocker task is gone the edge is an orphan;
        // fall back to the blocked task's project so orphan cleanup stays member-gated.
        if ($projectId === '') {
            $blockedTask = $this->fetchTask(objectService: $objectService, taskId: (string) ($edge['blocked'] ?? ''));
            if ($blockedTask !== null) {
                $projectId = (string) ($blockedTask['project'] ?? '');
            }
        }

        if ($projectId !== '') {
            $this->assertProjectMember(objectService: $objectService, projectId: $projectId);
        }

        $objectService->deleteObject(register: self::REGISTER, schema: self::SCHEMA, uuid: $id);

        $this->logger->info('Planix: dependency deleted', ['id' => $id]);

    }//end delete()

    /**
     * Cascade-remove every edge in which a task participates (as blocker or blocked).
     *
     * Called from the task delete flow and the move-to-another-project flow so
     * the same-project invariant holds by construction. Uses a server-trusted
     * delete (the caller has already been authorised to mutate the task).
     *
     * @param string $taskId UUID of the task being deleted or moved.
     *
     * @return int Number of edges removed.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function removeEdgesForTask(string $taskId): int
    {
        if ($taskId === '') {
            return 0;
        }

        $objectService = $this->objectService();
        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema(self::SCHEMA);

        $removed = 0;
        foreach ($this->fetchAllEdges(objectService: $objectService) as $edge) {
            $edgeId = (string) ($edge['id'] ?? '');
            if ($edgeId === '') {
                continue;
            }

            if ((string) ($edge['blocker'] ?? '') === $taskId
                || (string) ($edge['blocked'] ?? '') === $taskId
            ) {
                $objectService->deleteObject(register: self::REGISTER, schema: self::SCHEMA, uuid: $edgeId);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->logger->info('Planix: dependency edges cascaded', ['task' => $taskId, 'removed' => $removed]);
        }

        return $removed;

    }//end removeEdgesForTask()

    /**
     * Determine whether adding the edge blocker→blocked would close a cycle,
     * and if so, return the offending path; otherwise return null.
     *
     * Pure function over the edge list — no I/O. A cycle is closed when the
     * proposed `blocked` task can already reach the proposed `blocker` task by
     * following existing blocker→blocked edges. The returned path is rendered
     * as it would read once the edge is added:
     * `[blocker, blocked, …existing hops…, blocker]`.
     *
     * Self-edges (blocker === blocked) are reported as a one-hop cycle. The DFS
     * uses a visited set, so it terminates even if the existing graph already
     * contains a cycle (e.g. a concurrent-write artifact).
     *
     * @param array<int,array<string,mixed>> $edges   Existing edges (each with `blocker`/`blocked`).
     * @param string                         $blocker UUID of the proposed blocking task.
     * @param string                         $blocked UUID of the proposed blocked task.
     *
     * @return array<int,string>|null The cycle path of task UUIDs, or null when no cycle forms.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public static function cyclePath(array $edges, string $blocker, string $blocked): ?array
    {
        if ($blocker === $blocked) {
            return [$blocker, $blocker];
        }

        $adjacency = self::buildAdjacency(edges: $edges);

        // Does `blocked` already reach `blocker`? DFS following blocker→blocked.
        $visited = [];
        $stack   = [[$blocked, [$blocked]]];

        while ($stack !== []) {
            [$node, $trail] = array_pop($stack);

            if ($node === $blocker) {
                // Adding blocker→blocked closes the loop: blocker → blocked → … → blocker.
                return array_merge([$blocker], $trail);
            }

            if (isset($visited[$node]) === true) {
                continue;
            }

            $visited[$node] = true;

            foreach (($adjacency[$node] ?? []) as $next) {
                if (isset($visited[$next]) === true) {
                    continue;
                }

                $stack[] = [$next, array_merge($trail, [$next])];
            }
        }//end while

        return null;

    }//end cyclePath()

    /**
     * Build an adjacency map (blocker UUID → list of blocked UUIDs) from edges.
     *
     * @param array<int,array<string,mixed>> $edges Existing edges.
     *
     * @return array<string,array<int,string>>
     */
    private static function buildAdjacency(array $edges): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $from = (string) ($edge['blocker'] ?? '');
            $to   = (string) ($edge['blocked'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }

            $adjacency[$from][] = $to;
        }

        return $adjacency;

    }//end buildAdjacency()

    /**
     * Convenience boolean wrapper around {@see cyclePath()}.
     *
     * @param array<int,array<string,mixed>> $edges   Existing edges.
     * @param string                         $blocker UUID of the proposed blocking task.
     * @param string                         $blocked UUID of the proposed blocked task.
     *
     * @return bool True when the edge would form a cycle.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public static function wouldFormCycle(array $edges, string $blocker, string $blocked): bool
    {
        return self::cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked) !== null;

    }//end wouldFormCycle()

    /**
     * Derive the set of task UUIDs that are blocked, given edges and task statuses.
     *
     * Pure function — used by the backend for assertions and mirrored by the
     * frontend `isBlocked` helper. A task is blocked when at least one edge
     * names it as `blocked` whose `blocker` task exists in the supplied status
     * map and is not in a resolved (`done`/`cancelled`) status. Edges whose
     * blocker UUID does not resolve in the status map are ignored (tolerant
     * reads). The status map is keyed by task UUID.
     *
     * @param array<int,array<string,mixed>> $edges      Edge list.
     * @param array<string,string>           $statusById Map of task UUID → status string.
     *
     * @return array<int,string> Sorted, de-duplicated UUIDs of blocked tasks.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public static function deriveBlockedTaskIds(array $edges, array $statusById): array
    {
        $blockedIds = [];

        foreach ($edges as $edge) {
            $blockerId = (string) ($edge['blocker'] ?? '');
            $blockedId = (string) ($edge['blocked'] ?? '');
            if ($blockerId === '' || $blockedId === '') {
                continue;
            }

            // Tolerant read: ignore an edge whose blocker task no longer resolves.
            if (array_key_exists($blockerId, $statusById) === false) {
                continue;
            }

            if (in_array($statusById[$blockerId], self::RESOLVED_STATUSES, true) === false) {
                $blockedIds[$blockedId] = true;
            }
        }

        $ids = array_keys($blockedIds);
        sort($ids);

        return $ids;

    }//end deriveBlockedTaskIds()

    /**
     * Fetch a task object by UUID, returning its data array or null.
     *
     * @param object $objectService The OR ObjectService.
     * @param string $taskId        UUID of the task.
     *
     * @return array<string,mixed>|null
     */
    private function fetchTask(object $objectService, string $taskId): ?array
    {
        if ($taskId === '') {
            return null;
        }

        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema('task');
        $entity = $objectService->find(id: $taskId);
        if ($entity === null) {
            return null;
        }

        return $entity->getObject();

    }//end fetchTask()

    /**
     * Fetch all dependency edges that belong to a given project.
     *
     * An edge belongs to a project when its blocker task is in that project
     * (the same-project invariant guarantees the blocked task is too).
     *
     * @param object $objectService The OR ObjectService.
     * @param string $projectId     UUID of the project.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchProjectEdges(object $objectService, string $projectId): array
    {
        $taskIds = $this->fetchProjectTaskIds(objectService: $objectService, projectId: $projectId);
        if ($taskIds === []) {
            return [];
        }

        $taskIdSet = array_fill_keys($taskIds, true);
        $edges     = [];
        foreach ($this->fetchAllEdges(objectService: $objectService) as $edge) {
            $blockerId = (string) ($edge['blocker'] ?? '');
            if (isset($taskIdSet[$blockerId]) === true) {
                $edges[] = $edge;
            }
        }

        return $edges;

    }//end fetchProjectEdges()

    /**
     * Fetch the UUIDs of every task in a project.
     *
     * @param object $objectService The OR ObjectService.
     * @param string $projectId     UUID of the project.
     *
     * @return array<int,string>
     */
    private function fetchProjectTaskIds(object $objectService, string $projectId): array
    {
        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema('task');
        $results = $objectService->searchObjects(filters: ['project' => $projectId]);

        $ids = [];
        foreach ($this->normaliseResults(results: $results) as $row) {
            $id = $this->extractId(row: $row);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;

    }//end fetchProjectTaskIds()

    /**
     * Fetch every dependency edge in the register, normalised to plain arrays
     * carrying at least `id`, `blocker`, and `blocked`.
     *
     * @param object $objectService The OR ObjectService.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchAllEdges(object $objectService): array
    {
        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema(self::SCHEMA);
        $results = $objectService->searchObjects();

        $edges = [];
        foreach ($this->normaliseResults(results: $results) as $row) {
            $data       = $this->extractData(row: $row);
            $data['id'] = $this->extractId(row: $row);
            $edges[]    = $data;
        }

        return $edges;

    }//end fetchAllEdges()

    /**
     * Assert the current user is a member of the given project; throw otherwise.
     *
     * @param object $objectService The OR ObjectService.
     * @param string $projectId     UUID of the project.
     *
     * @return void
     *
     * @throws DependencyValidationException When unauthenticated or not a member.
     */
    private function assertProjectMember(object $objectService, string $projectId): void
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new DependencyValidationException(
                message: 'Authentication required.',
                code: DependencyValidationException::CODE_UNAUTHENTICATED
            );
        }

        $uid = $user->getUID();

        $objectService->setRegister(self::REGISTER);
        $objectService->setSchema('project');
        $entity = $objectService->find(id: $projectId);
        if ($entity === null) {
            throw new DependencyValidationException(
                message: 'Project not found.',
                code: DependencyValidationException::CODE_NOT_FOUND
            );
        }

        $project = $entity->getObject();
        $members = (array) ($project['members'] ?? []);
        if (in_array($uid, $members, true) === false) {
            throw new DependencyValidationException(
                message: 'You are not a member of this project.',
                code: DependencyValidationException::CODE_FORBIDDEN
            );
        }

    }//end assertProjectMember()

    /**
     * Render a cycle path of task UUIDs as a human-readable title chain.
     *
     * Falls back to the UUID when a task title cannot be resolved.
     *
     * @param object            $objectService The OR ObjectService.
     * @param array<int,string> $path          Ordered task UUIDs.
     *
     * @return string e.g. "Fix login → Deploy → QA → Fix login".
     */
    private function renderPath(object $objectService, array $path): string
    {
        $titles = [];
        foreach ($path as $uuid) {
            $task  = $this->fetchTask(objectService: $objectService, taskId: $uuid);
            $title = $uuid;
            if ($task !== null && ($task['title'] ?? '') !== '') {
                $title = (string) $task['title'];
            }

            $titles[] = $title;
        }

        return implode(' → ', $titles);

    }//end renderPath()

    /**
     * Normalise an ObjectService result set (which may be a paginated array
     * with a `results` key, a plain list, or a list of entity objects) to a
     * plain list of rows.
     *
     * @param mixed $results The raw ObjectService return value.
     *
     * @return array<int,mixed>
     */
    private function normaliseResults(mixed $results): array
    {
        if (is_array($results) === true && array_key_exists('results', $results) === true) {
            return (array) $results['results'];
        }

        if (is_array($results) === true) {
            return $results;
        }

        return [];

    }//end normaliseResults()

    /**
     * Extract the object UUID from an ObjectService row (entity or array).
     *
     * @param mixed $row An entity object or a plain array row.
     *
     * @return string
     */
    private function extractId(mixed $row): string
    {
        if (is_object($row) === true) {
            if (method_exists($row, 'getUuid') === true) {
                return (string) $row->getUuid();
            }

            if (method_exists($row, 'getId') === true) {
                return (string) $row->getId();
            }
        }

        if (is_array($row) === true) {
            if (isset($row['@self']['id']) === true) {
                return (string) $row['@self']['id'];
            }

            return (string) ($row['id'] ?? '');
        }

        return '';

    }//end extractId()

    /**
     * Extract the object data array from an ObjectService row (entity or array).
     *
     * @param mixed $row An entity object or a plain array row.
     *
     * @return array<string,mixed>
     */
    private function extractData(mixed $row): array
    {
        if (is_object($row) === true && method_exists($row, 'getObject') === true) {
            return (array) $row->getObject();
        }

        if (is_array($row) === true) {
            return $row;
        }

        return [];

    }//end extractData()
}//end class
