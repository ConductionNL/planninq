<?php

/**
 * Planninq Dependency Service
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
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\DependencyValidationException;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Domain service for task dependency edges.
 *
 * The graph algorithm (cycle detection, blocked-state derivation) is kept pure
 * and side-effect free so it can be unit tested without OpenRegister, and now
 * lives in its own class: {@see DependencyGraph::wouldFormCycle()} and
 * {@see DependencyGraph::deriveBlockedTaskIds()} operate purely on edge/task
 * arrays. The OpenRegister read plane likewise lives in
 * {@see DependencyRepository}. The public {@see create()} / {@see delete()}
 * methods wrap both with the ObjectService writes and the membership (IDOR)
 * guard, which is all this class still owns.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
class DependencyService {

	/**
	 * OpenRegister register slug owning the Planninq schemas.
	 *
	 * Moved from `planix` to `planninq` together with the MigrateRegisterSlug
	 * repair step, which renames the register ROW. This literal only resolves
	 * because that step runs first: OpenRegister looks a register up by slug and
	 * by nothing else, so a renamed slug here without the row rename would find
	 * no register at all.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * OpenRegister schema slug for dependency edges.
	 *
	 * @var string
	 */
	private const SCHEMA = 'dependency';

	/**
	 * The link types the dependency schema allows; only `blocks` blocks.
	 *
	 * @var array<int,string>
	 */
	public const LINK_TYPES = ['blocks', 'relates', 'duplicates', 'clones', 'splits', 'causes'];

	/**
	 * Constructor for the DependencyService.
	 *
	 * @param DependencyRepository $repository The OpenRegister read plane for edges/tasks/projects.
	 * @param DependencyGraph $graph The pure cycle-detection algorithms.
	 * @param IUserSession $userSession The current user session (membership guard).
	 * @param LoggerInterface $logger The logger.
	 * @param IGroupManager|null $groupManager The caller's groups, for a project shared with a group.
	 *
	 * @return void
	 */
	public function __construct(
		private DependencyRepository $repository,
		private DependencyGraph $graph,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private ?IGroupManager $groupManager=null,
	) {

	}//end __construct()

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
	 * A link that is not `blocks` (relates, duplicates and the other types the
	 * schema names) does not block and so is not checked for a cycle.
	 *
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 * @param string $type    The link type, `blocks` by default.
	 *
	 * @return array<string,mixed> The serialised stored edge.
	 *
	 * @throws DependencyValidationException On any validation failure (carries an HTTP-mappable code).
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.1
	 */
	public function create(string $blocker, string $blocked, string $type='blocks'): array {
		if (in_array($type, self::LINK_TYPES, true) === false) {
			throw new DependencyValidationException(
				message: 'Unknown link type.',
				code: DependencyValidationException::CODE_VALIDATION
			);
		}

		// 1. No self-edge.
		$this->assertDistinctTasks(blocker: $blocker, blocked: $blocked);

		$objectService = $this->repository->objectService();

		// 2. Both tasks exist and share a project.
		$projectId = $this->resolveSharedProjectId(objectService: $objectService, blocker: $blocker, blocked: $blocked);

		// 3. Membership guard (IDOR) — caller must be a member of the project.
		$this->assertProjectMember(objectService: $objectService, projectId: $projectId);

		// 4. + 5. No duplicate edge; no cycle. Loads the project edges once.
		$edges = $this->repository->fetchProjectEdges(objectService: $objectService, projectId: $projectId);
		$this->assertNotDuplicate(edges: $edges, blocker: $blocker, blocked: $blocked);
		if ($type === 'blocks') {
			$this->assertNoCycle(objectService: $objectService, edges: $edges, blocker: $blocker, blocked: $blocked);
		}

		$saved = $objectService->saveObject(
			object: ['blocker' => $blocker, 'blocked' => $blocked, 'type' => $type],
			register: self::REGISTER,
			schema: self::SCHEMA,
			_rbac: false
		);

		$this->logger->info(
			'Planninq: dependency created',
			['blocker' => $blocker, 'blocked' => $blocked, 'project' => $projectId]
		);

		return $saved->jsonSerialize();
	}//end create()

	/**
	 * Create the links of an imported plan in one project, with the same checks as create().
	 *
	 * The project's edges are read once and every accepted link joins them, so
	 * a plan's links are checked against each other too. A self link or one
	 * that would close a cycle of blocking links is refused and counted; an
	 * edge that already exists is counted as existing and not written again,
	 * which makes a rerun of the same import safe. The caller has already
	 * checked that the acting user may restructure this project (its owner or
	 * an admin), and every task id is one it created or matched in that
	 * project, so the membership step of create() is not repeated here.
	 *
	 * @param string                                                   $projectId The project UUID.
	 * @param list<array{blocker:string,blocked:string,type:string}> $links     The links.
	 *
	 * @return array{created:int,existing:int,refused:int}
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-2.1
	 */
	public function createImported(string $projectId, array $links): array {
		$objectService = $this->repository->objectService();
		$edges         = $this->repository->fetchProjectEdges(objectService: $objectService, projectId: $projectId);
		$result        = ['created' => 0, 'existing' => 0, 'refused' => 0];

		foreach ($links as $link) {
			$edge = ['blocker' => $link['blocker'], 'blocked' => $link['blocked'], 'type' => $link['type']];
			if ($this->hasEdge(edges: $edges, blocker: $edge['blocker'], blocked: $edge['blocked']) === true) {
				$result['existing']++;
				continue;
			}

			if ($edge['blocker'] === '' || $edge['blocker'] === $edge['blocked']
				|| ($edge['type'] === 'blocks' && $this->graph->cyclePath(edges: $edges, blocker: $edge['blocker'], blocked: $edge['blocked']) !== null)
			) {
				$result['refused']++;
				continue;
			}

			$objectService->saveObject(object: $edge, register: self::REGISTER, schema: self::SCHEMA, _rbac: false);
			$edges[] = $edge;
			$result['created']++;
		}

		$this->logger->info('Planninq: imported dependency links', ['project' => $projectId] + $result);

		return $result;
	}//end createImported()

	/**
	 * Whether the edge list already holds blocker → blocked.
	 *
	 * @param array<int,array<string,mixed>> $edges   The edges.
	 * @param string                         $blocker The blocking task.
	 * @param string                         $blocked The blocked task.
	 *
	 * @return bool
	 */
	private function hasEdge(array $edges, string $blocker, string $blocked): bool {
		foreach ($edges as $edge) {
			if ((string)($edge['blocker'] ?? '') === $blocker && (string)($edge['blocked'] ?? '') === $blocked) {
				return true;
			}
		}

		return false;
	}//end hasEdge()

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
	private function assertDistinctTasks(string $blocker, string $blocked): void {
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
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 *
	 * @return string The shared project UUID.
	 *
	 * @throws DependencyValidationException When a task is missing or the projects differ.
	 */
	private function resolveSharedProjectId(object $objectService, string $blocker, string $blocked): string {
		$blockerTask = $this->repository->fetchTask(objectService: $objectService, taskId: $blocker);
		$blockedTask = $this->repository->fetchTask(objectService: $objectService, taskId: $blocked);

		if ($blockerTask === null || $blockedTask === null) {
			throw new DependencyValidationException(
				message: 'One or both tasks could not be found.',
				code: DependencyValidationException::CODE_NOT_FOUND
			);
		}

		$projectId = (string)($blockerTask['project'] ?? '');
		if ($projectId === '' || $projectId !== (string)($blockedTask['project'] ?? '')) {
			throw new DependencyValidationException(
				message: 'Dependencies can only link tasks within the same project.',
				code: DependencyValidationException::CODE_VALIDATION
			);
		}

		return $projectId;
	}//end resolveSharedProjectId()

	/**
	 * Assert the proposed edge does not already exist.
	 *
	 * @param array<int,array<string,mixed>> $edges The project's existing edges.
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 *
	 * @return void
	 *
	 * @throws DependencyValidationException On a duplicate.
	 *
	 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.1
	 */
	private function assertNotDuplicate(array $edges, string $blocker, string $blocked): void {
		foreach ($edges as $edge) {
			if ((string)($edge['blocker'] ?? '') === $blocker
				&& (string)($edge['blocked'] ?? '') === $blocked
			) {
				throw new DependencyValidationException(
					message: 'This dependency already exists.',
					code: DependencyValidationException::CODE_VALIDATION
				);
			}
		}

	}//end assertNotDuplicate()

	/**
	 * Assert a blocking edge would not close a cycle.
	 *
	 * @param object $objectService The OR ObjectService (for path rendering).
	 * @param array<int,array<string,mixed>> $edges The project's existing edges.
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 *
	 * @return void
	 *
	 * @throws DependencyValidationException On a cycle (message names the path).
	 *
	 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.1
	 */
	private function assertNoCycle(object $objectService, array $edges, string $blocker, string $blocked): void {
		$path = $this->graph->cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked);
		if ($path !== null) {
			$rendered = $this->renderPath(objectService: $objectService, path: $path);
			throw new DependencyValidationException(
				message: 'This dependency would create a cycle: ' . $rendered,
				code: DependencyValidationException::CODE_VALIDATION
			);
		}

	}//end assertNoCycle()

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
	public function delete(string $id): void {
		$objectService = $this->repository->objectService();

		$objectService->setRegister(self::REGISTER);
		$objectService->setSchema(self::SCHEMA);
		$entity = $objectService->find(id: $id);
		if ($entity === null) {
			throw new DependencyValidationException(
				message: 'Dependency not found.',
				code: DependencyValidationException::CODE_NOT_FOUND
			);
		}

		$edge = $entity->getObject();
		$blockerTask = $this->repository->fetchTask(objectService: $objectService, taskId: (string)($edge['blocker'] ?? ''));

		$projectId = '';
		if ($blockerTask !== null) {
			$projectId = (string)($blockerTask['project'] ?? '');
		}

		// Membership guard. When the blocker task is gone the edge is an orphan;
		// fall back to the blocked task's project so orphan cleanup stays member-gated.
		if ($projectId === '') {
			$blockedTask = $this->repository->fetchTask(objectService: $objectService, taskId: (string)($edge['blocked'] ?? ''));
			if ($blockedTask !== null) {
				$projectId = (string)($blockedTask['project'] ?? '');
			}
		}

		if ($projectId !== '') {
			$this->assertProjectMember(objectService: $objectService, projectId: $projectId);
		}

		$objectService->deleteObject(register: self::REGISTER, schema: self::SCHEMA, uuid: $id);

		$this->logger->info('Planninq: dependency deleted', ['id' => $id]);

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
	public function removeEdgesForTask(string $taskId): int {
		if ($taskId === '') {
			return 0;
		}

		$objectService = $this->repository->objectService();
		$objectService->setRegister(self::REGISTER);
		$objectService->setSchema(self::SCHEMA);

		$removed = 0;
		foreach ($this->repository->fetchAllEdges(objectService: $objectService) as $edge) {
			$edgeId = (string)($edge['id'] ?? '');
			if ($edgeId === '') {
				continue;
			}

			if ((string)($edge['blocker'] ?? '') === $taskId
				|| (string)($edge['blocked'] ?? '') === $taskId
			) {
				$objectService->deleteObject(register: self::REGISTER, schema: self::SCHEMA, uuid: $edgeId);
				$removed++;
			}
		}

		if ($removed > 0) {
			$this->logger->info('Planninq: dependency edges cascaded', ['task' => $taskId, 'removed' => $removed]);
		}

		return $removed;
	}//end removeEdgesForTask()

	/**
	 * Assert the current user may change the given project's work (owner,
	 * manager or member, in person or through a group); throw otherwise.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $projectId UUID of the project.
	 *
	 * @return void
	 *
	 * @throws DependencyValidationException When unauthenticated or not a member.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.4
	 */
	private function assertProjectMember(object $objectService, string $projectId): void {
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

		$project  = $entity->getObject();
		$groupIds = [];
		if ($this->groupManager !== null) {
			$groupIds = $this->groupManager->getUserGroupIds($user);
		}

		if ((new ProjectRoles())->mayWrite(project: $project, uid: $uid, groupIds: $groupIds) === false) {
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
	 * @param object $objectService The OR ObjectService.
	 * @param array<int,string> $path Ordered task UUIDs.
	 *
	 * @return string e.g. "Fix login → Deploy → QA → Fix login".
	 */
	private function renderPath(object $objectService, array $path): string {
		$titles = [];
		foreach ($path as $uuid) {
			$task = $this->repository->fetchTask(objectService: $objectService, taskId: $uuid);
			$title = $uuid;
			if ($task !== null && ($task['title'] ?? '') !== '') {
				$title = (string)$task['title'];
			}

			$titles[] = $title;
		}

		return implode(' → ', $titles);
	}//end renderPath()
}//end class
