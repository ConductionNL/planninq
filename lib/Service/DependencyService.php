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
	 * Constructor for the DependencyService.
	 *
	 * @param DependencyRepository $repository The OpenRegister read plane for edges/tasks/projects.
	 * @param DependencyGraph $graph The pure cycle-detection algorithms.
	 * @param IUserSession $userSession The current user session (membership guard).
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function __construct(
		private DependencyRepository $repository,
		private DependencyGraph $graph,
		private IUserSession $userSession,
		private LoggerInterface $logger,
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
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 *
	 * @return array<string,mixed> The serialised stored edge.
	 *
	 * @throws DependencyValidationException On any validation failure (carries an HTTP-mappable code).
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function create(string $blocker, string $blocked): array {
		// 1. No self-edge.
		$this->assertDistinctTasks(blocker: $blocker, blocked: $blocked);

		$objectService = $this->repository->objectService();

		// 2. Both tasks exist and share a project.
		$projectId = $this->resolveSharedProjectId(objectService: $objectService, blocker: $blocker, blocked: $blocked);

		// 3. Membership guard (IDOR) — caller must be a member of the project.
		$this->assertProjectMember(objectService: $objectService, projectId: $projectId);

		// 4. + 5. No duplicate edge; no cycle. Loads the project edges once.
		$edges = $this->repository->fetchProjectEdges(objectService: $objectService, projectId: $projectId);
		$this->assertEdgeIsValid(objectService: $objectService, edges: $edges, blocker: $blocker, blocked: $blocked);

		$saved = $objectService->saveObject(
			object: ['blocker' => $blocker, 'blocked' => $blocked],
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
	 * Assert the proposed edge is neither a duplicate nor cycle-forming.
	 *
	 * @param object $objectService The OR ObjectService (for path rendering).
	 * @param array<int,array<string,mixed>> $edges The project's existing edges.
	 * @param string $blocker UUID of the blocking task.
	 * @param string $blocked UUID of the blocked task.
	 *
	 * @return void
	 *
	 * @throws DependencyValidationException On a duplicate or a cycle (message names the path).
	 */
	private function assertEdgeIsValid(object $objectService, array $edges, string $blocker, string $blocked): void {
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

		$path = $this->graph->cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked);
		if ($path !== null) {
			$rendered = $this->renderPath(objectService: $objectService, path: $path);
			throw new DependencyValidationException(
				message: 'This dependency would create a cycle: ' . $rendered,
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
	 * Assert the current user is a member of the given project; throw otherwise.
	 *
	 * @param object $objectService The OR ObjectService.
	 * @param string $projectId UUID of the project.
	 *
	 * @return void
	 *
	 * @throws DependencyValidationException When unauthenticated or not a member.
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

		$project = $entity->getObject();
		$members = (array)($project['members'] ?? []);
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
