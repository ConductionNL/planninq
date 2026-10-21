<?php

/**
 * Planninq ProjectActionService
 *
 * The actions that write several objects at once, done on the server instead
 * of as a run of browser requests. The browser version stopped half-way on a
 * refused or failed write and left the data half-changed: a project without
 * its tasks but with its risks and finance lines (still readable by former
 * members), a release marked shipped with a back-dated client time, a parent
 * deleted with only some of its subtasks, a partial copy.
 *
 * Each action checks the caller's rights for the whole action first, then
 * writes as an OpenRegister system operation, so no per-object guard refuses
 * a write half-way. Writes run children first and the main object last, so a
 * failure leaves the main object in place and the action can simply be run
 * again.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/projects.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectActionException;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

/**
 * Server-side multi-object actions on projects, releases and tasks.
 *
 * @spec openspec/specs/projects.md
 */
class ProjectActionService {

	/**
	 * The schemas deleted with a project besides its tasks and columns, in order.
	 *
	 * @var array<int,string>
	 */
	private const PROJECT_CHILDREN = [
		'plannedTimeEntry',
		'forgeLink',
		'boardFilter',
		'projectLogEntry',
		'risk',
		'projectStatusReport',
		'financeLine',
		'projectRelease',
		'projectPhase',
	];

	/**
	 * Task statuses that count as finished when a release ships.
	 *
	 * @var array<int,string>
	 */
	private const FINISHED = ['done', 'cancelled'];

	/**
	 * The fields a copied task takes over from the original.
	 *
	 * @var array<int,string>
	 */
	private const COPIED_FIELDS = ['description', 'labels', 'column', 'columnOrder', 'priority', 'project'];

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership   Reads planninq objects as the system.
	 * @param ContainerInterface       $container    Resolves OpenRegister's ObjectService.
	 * @param IUserSession             $userSession  The caller.
	 * @param IGroupManager            $groupManager The caller's groups and admin status.
	 * @param ITimeFactory             $timeFactory  The server clock.
	 * @param IL10N                    $l10n         Names a copy.
	 * @param IAppManager              $appManager   Tells whether OpenRegister is installed.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
		private IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Delete a project and everything in it.
	 *
	 * Only the project's owner, a member of its owning group or an admin may.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return int The number of objects deleted, the project included.
	 *
	 * @throws ProjectActionException When the project does not resolve or the caller may not delete it.
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function deleteProject(string $projectId): int {
		$project = $this->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$user    = $this->caller();
		if ($this->isAdmin(user: $user) === false && $this->ownsProject(project: $project, user: $user) === false) {
			throw new ProjectActionException(
				reason: 'planninq-project-delete-not-allowed',
				status: 403,
				message: 'Only the project owner can delete this project.'
			);
		}

		$doomed = $this->projectObjects(projectId: $projectId);
		$this->asSystem(
			writes: function () use ($doomed): void {
				foreach ($doomed as [$schema, $id]) {
					$this->deleteAsSystem(schema: $schema, id: $id);
				}
			}
		);

		return count($doomed);
	}//end deleteProject()

	/**
	 * Every object of a project, in delete order: its children, its tasks, its columns, then the project.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return array<string|int,array{0:string,1:string}> Schema and UUID per object.
	 */
	private function projectObjects(string $projectId): array {
		$taskIds = array_column($this->membership->rows(schema: 'task', filters: ['project' => $projectId]), 'id');
		$doomed  = [];
		foreach (self::PROJECT_CHILDREN as $schema) {
			foreach ($this->membership->rows(schema: $schema, filters: ['project' => $projectId]) as $row) {
				$doomed[$schema . '/' . $row['id']] = [$schema, $row['id']];
			}
		}

		// Time entries that name only their task.
		foreach (array_chunk($taskIds, 500) as $chunk) {
			foreach ($this->membership->rows(schema: 'plannedTimeEntry', filters: ['task' => $chunk]) as $row) {
				$doomed['plannedTimeEntry/' . $row['id']] = ['plannedTimeEntry', $row['id']];
			}
		}

		foreach ($taskIds as $taskId) {
			$doomed['task/' . $taskId] = ['task', $taskId];
		}

		foreach ($this->membership->rows(schema: 'column', filters: ['project' => $projectId]) as $row) {
			$doomed['column/' . $row['id']] = ['column', $row['id']];
		}

		$doomed[] = [ProjectMembershipService::PROJECT_SCHEMA, $projectId];

		return $doomed;
	}//end projectObjects()

	/**
	 * Mark a release as released, at the server's time, after moving, clearing or keeping its unfinished tasks.
	 *
	 * @param string      $releaseId The release UUID.
	 * @param string      $choice    `move`, `clear` or `keep`.
	 * @param string|null $targetId  The release to move the tasks to, for `move`.
	 *
	 * @return array<string,mixed> The released release.
	 *
	 * @throws ProjectActionException When the caller may not, the choice is unknown or the target is not in the project.
	 *
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
	 */
	public function shipRelease(string $releaseId, string $choice, ?string $targetId): array {
		$release   = $this->readable(schema: 'projectRelease', id: $releaseId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'projectRelease', data: $release);
		$this->assertWrites(projectId: $projectId);
		$value = $this->shipTarget(choice: $choice, targetId: $targetId, projectId: $projectId, releaseId: $releaseId);

		$moves = [];
		if ($choice !== 'keep') {
			foreach ($this->membership->rows(schema: 'task', filters: ['release' => $releaseId]) as $task) {
				if (in_array(($task['data']['status'] ?? null), self::FINISHED, true) === false) {
					$moves[] = $task;
				}
			}
		}

		$released = array_merge($release, ['status' => 'released', 'releasedAt' => $this->timeFactory->getDateTime()->format(\DateTimeInterface::ATOM)]);
		unset($released['@self'], $released['id']);

		return $this->asSystem(
			writes: function () use ($moves, $value, $released, $releaseId): array {
				foreach ($moves as $task) {
					$this->save(schema: 'task', id: $task['id'], data: array_merge($task['data'], ['release' => $value]));
				}

				return $this->save(schema: 'projectRelease', id: $releaseId, data: $released);
			}
		);
	}//end shipRelease()

	/**
	 * Delete a parent task, and its subtasks too or keep them as separate tasks.
	 *
	 * Nothing is deleted when a task that would go has logged time, or when
	 * the caller may not delete one of them (its reporter, the project owner
	 * or an admin may).
	 *
	 * @param string $taskId The parent task UUID.
	 * @param string $mode   `delete` or `detach`.
	 *
	 * @return int The number of tasks deleted.
	 *
	 * @throws ProjectActionException When refused.
	 *
	 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.2
	 */
	public function deleteTaskTree(string $taskId, string $mode): int {
		if (in_array($mode, ['delete', 'detach'], true) === false) {
			throw new ProjectActionException(reason: 'planninq-invalid-choice', status: 400, message: 'Choose to delete or to keep the subtasks.');
		}

		$task     = $this->readable(schema: 'task', id: $taskId);
		$children = $this->membership->rows(schema: 'task', filters: ['parent' => $taskId]);
		$going    = [['id' => $taskId, 'data' => $task]];
		if ($mode === 'delete') {
			$going = array_merge($children, $going);
		}

		$owner = $this->membership->ownerOfProject(projectId: $this->membership->projectIdFor(schemaSlug: 'task', data: $task));
		$this->assertMayDeleteTasks(tasks: $going, owner: $owner);

		$this->asSystem(
			writes: function () use ($mode, $children, $going): void {
				if ($mode === 'detach') {
					foreach ($children as $child) {
						$this->save(schema: 'task', id: $child['id'], data: array_merge($child['data'], ['parent' => null]));
					}
				}

				foreach ($going as $one) {
					$this->deleteAsSystem(schema: 'task', id: $one['id']);
				}
			}
		);

		return count($going);
	}//end deleteTaskTree()

	/**
	 * Copy a task and its subtasks: no dates, people, time or done checklist items.
	 *
	 * @param string $taskId The task UUID.
	 *
	 * @return array<string,mixed> The copy.
	 *
	 * @throws ProjectActionException When the caller does not work on the task's project.
	 *
	 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
	 */
	public function duplicateTask(string $taskId): array {
		$task      = $this->readable(schema: 'task', id: $taskId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $task);
		$this->assertWrites(projectId: $projectId);
		$children = $this->membership->rows(schema: 'task', filters: ['parent' => $taskId]);
		$title    = str_replace('{title}', (string)($task['title'] ?? ''), $this->l10n->t('Copy of {title}'));
		$reporter = $this->caller()->getUID();

		return $this->asSystem(
			writes: function () use ($task, $title, $children, $reporter): array {
				$copy   = $this->save(schema: 'task', id: null, data: $this->copyOf(task: $task, title: $title, parent: null) + ['reporter' => $reporter]);
				$copyId = (string)$copy['id'];
				foreach ($children as $child) {
					$subtask = $this->copyOf(task: $child['data'], title: (string)($child['data']['title'] ?? ''), parent: $copyId);
					$this->save(schema: 'task', id: null, data: $subtask + ['reporter' => $reporter]);
				}

				return $copy;
			}
		);
	}//end duplicateTask()

	/**
	 * Move a phase one place up (-1) or down (+1); every phase gets a distinct order in one go.
	 *
	 * @param string $projectId The project UUID.
	 * @param string $phaseId   The phase to move.
	 * @param int    $step      -1 or +1.
	 *
	 * @return array<int,array{id:string,order:int}> The phases in their new order.
	 *
	 * @throws ProjectActionException When the caller does not work on the project or the phase is not in it.
	 *
	 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
	 */
	public function movePhase(string $projectId, string $phaseId, int $step): array {
		$this->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$this->assertWrites(projectId: $projectId);
		$phases = $this->membership->rows(schema: 'projectPhase', filters: ['project' => $projectId]);
		usort(
			$phases,
			static fn (array $one, array $two): int => [(int)($one['data']['order'] ?? 0), $one['id']] <=> [(int)($two['data']['order'] ?? 0), $two['id']]
		);

		$index = array_search($phaseId, array_column($phases, 'id'), true);
		if ($index === false || in_array($step, [-1, 1], true) === false) {
			throw new ProjectActionException(reason: 'planninq-invalid-choice', status: 400, message: 'Choose a phase of this project and a direction.');
		}

		if (isset($phases[$index + $step]) === true) {
			[$phases[$index], $phases[$index + $step]] = [$phases[$index + $step], $phases[$index]];
		}

		$this->asSystem(
			writes: function () use ($phases): void {
				foreach ($phases as $order => $phase) {
					if ((int)($phase['data']['order'] ?? -1) !== $order) {
						$this->save(schema: 'projectPhase', id: $phase['id'], data: array_merge($phase['data'], ['order' => $order]));
					}
				}
			}
		);

		return array_map(static fn (array $phase, int $order): array => ['id' => $phase['id'], 'order' => $order], $phases, array_keys($phases));
	}//end movePhase()

	/**
	 * Create an action task from a log entry and link it from the entry, as one action.
	 *
	 * The task is created with the caller's rights, so every task rule
	 * applies; the link is then written as the system. When the link cannot
	 * be written the task is removed again, so no unlinked action stays.
	 *
	 * @param string              $entryId The log entry UUID.
	 * @param array<string,mixed> $task    The task fields.
	 *
	 * @return array{task: array<string,mixed>, entry: array<string,mixed>}
	 *
	 * @throws ProjectActionException When the caller does not work on the entry's project.
	 *
	 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
	 */
	public function addAction(string $entryId, array $task): array {
		$entry     = $this->readable(schema: 'projectLogEntry', id: $entryId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'projectLogEntry', data: $entry);
		$this->assertWrites(projectId: $projectId);

		unset($task['@self'], $task['id']);
		$saved  = $this->objectService()->saveObject(
			object: array_merge($task, ['project' => $projectId, 'issueType' => 'action']),
			register: ProjectMembershipService::REGISTER,
			schema: 'task'
		);
		$taskId = (string)$saved->getUuid();

		try {
			$linked = $this->asSystem(
				writes: fn (): array => $this->save(
					schema: 'projectLogEntry',
					id: $entryId,
					data: array_merge($entry, ['actions' => array_values(array_merge((array)($entry['actions'] ?? []), [$taskId]))])
				)
			);
		} catch (\Throwable $e) {
			$this->asSystem(writes: fn () => $this->deleteAsSystem(schema: 'task', id: $taskId));
			throw $e;
		}

		return ['task' => ['id' => $taskId] + (array)$saved->getObject(), 'entry' => $linked];
	}//end addAction()

	/**
	 * The data of a copied task.
	 *
	 * @param array<string,mixed> $task   The original.
	 * @param string              $title  The copy's title.
	 * @param string|null         $parent The copy's parent, for a subtask.
	 *
	 * @return array<string,mixed>
	 */
	private function copyOf(array $task, string $title, ?string $parent): array {
		$copy = ['title' => $title, 'status' => 'open', 'priority' => 'normal'];
		foreach (self::COPIED_FIELDS as $field) {
			if (isset($task[$field]) === true && $task[$field] !== '' && $task[$field] !== []) {
				$copy[$field] = $task[$field];
			}
		}

		if (is_array($task['checklist'] ?? null) === true && $task['checklist'] !== []) {
			$copy['checklist'] = array_map(static fn ($item): array => array_merge((array)$item, ['done' => false]), $task['checklist']);
		}

		if ($parent !== null) {
			$copy['parent'] = $parent;
		}

		return $copy;
	}//end copyOf()

	/**
	 * The release value the shipped release's unfinished tasks get.
	 *
	 * @param string      $choice    `move`, `clear` or `keep`.
	 * @param string|null $targetId  The release to move to.
	 * @param string      $projectId The project.
	 * @param string      $releaseId The release being shipped.
	 *
	 * @return string|null
	 *
	 * @throws ProjectActionException For an unknown choice or a target outside the project.
	 */
	private function shipTarget(string $choice, ?string $targetId, string $projectId, string $releaseId): ?string {
		if (in_array($choice, ['move', 'clear', 'keep'], true) === false) {
			throw new ProjectActionException(reason: 'planninq-invalid-choice', status: 400, message: 'Choose to move, clear or keep the unfinished tasks.');
		}

		if ($choice !== 'move') {
			return null;
		}

		$target = $this->membership->objectData(schema: 'projectRelease', id: (string)$targetId);
		if ($target === null || $targetId === $releaseId || $this->membership->projectIdFor(schemaSlug: 'projectRelease', data: $target) !== $projectId) {
			throw new ProjectActionException(reason: 'planninq-invalid-target', status: 400, message: 'Choose another release of this project.');
		}

		return $targetId;
	}//end shipTarget()

	/**
	 * Refuse unless every task may be deleted by the caller and none has logged time.
	 *
	 * @param array<int,array{id:string,data:array<string,mixed>}> $tasks The tasks that would go.
	 * @param string|null                                          $owner The project owner.
	 *
	 * @return void
	 *
	 * @throws ProjectActionException When one may not go.
	 */
	private function assertMayDeleteTasks(array $tasks, ?string $owner): void {
		$user = $this->caller();
		$uid  = $user->getUID();
		foreach ($tasks as $task) {
			if ($this->isAdmin(user: $user) === false && $owner !== $uid && ($task['data']['reporter'] ?? null) !== $uid) {
				throw new ProjectActionException(
					reason: 'planninq-task-delete-not-allowed',
					status: 403,
					message: 'Only the reporter, the project owner or an admin can delete this task.'
				);
			}

			if ($this->membership->rows(schema: 'plannedTimeEntry', filters: ['task' => $task['id']]) !== []) {
				throw new ProjectActionException(reason: 'planninq-task-has-logged-time', status: 409, message: 'This task has logged time. Cancel it instead.');
			}
		}
	}//end assertMayDeleteTasks()

	/**
	 * Refuse unless the caller writes the project's work (or is an admin).
	 *
	 * @param string $projectId The project.
	 *
	 * @return void
	 *
	 * @throws ProjectActionException When they do not.
	 */
	private function assertWrites(string $projectId): void {
		$user    = $this->caller();
		$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		if ($this->isAdmin(user: $user) === true) {
			return;
		}

		$lists = $this->membership->childLists(project: ($project ?? []));
		$isWriter = (in_array($user->getUID(), $lists['members'], true) === true
			|| array_intersect($lists['memberGroups'], $this->groupManager->getUserGroupIds($user)) !== []);
		if ($project === null || $isWriter === false) {
			throw new ProjectActionException(reason: 'planninq-not-a-project-member', status: 403, message: 'You are not a member of this project.');
		}
	}//end assertWrites()

	/**
	 * An object the caller may read, or a 404 that does not say whether it exists.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws ProjectActionException When it does not resolve or the caller cannot read it.
	 */
	private function readable(string $schema, string $id): array {
		try {
			$entity = $this->objectService()->find(id: $id, register: ProjectMembershipService::REGISTER, schema: $schema);
		} catch (\Throwable $e) {
			$entity = null;
		}

		if ($entity === null) {
			throw new ProjectActionException(reason: 'planninq-not-found', status: 404, message: 'Not found.');
		}

		return (array)$entity->getObject();
	}//end readable()

	/**
	 * Whether the caller owns the project, in person or through its owning group.
	 *
	 * @param array<string,mixed> $project The project.
	 * @param IUser               $user    The caller.
	 *
	 * @return bool
	 */
	private function ownsProject(array $project, IUser $user): bool {
		if (($project['owner'] ?? null) === $user->getUID()) {
			return true;
		}

		$ownerGroups = $this->membership->normalise(members: ($project['ownerGroups'] ?? null));

		return array_intersect($ownerGroups, $this->groupManager->getUserGroupIds($user)) !== [];
	}//end ownsProject()

	/**
	 * Whether the caller is an admin.
	 *
	 * @param IUser $user The caller.
	 *
	 * @return bool
	 */
	private function isAdmin(IUser $user): bool {
		return $this->groupManager->isAdmin($user->getUID());
	}//end isAdmin()

	/**
	 * The signed-in caller.
	 *
	 * @return IUser
	 *
	 * @throws ProjectActionException Without a session.
	 */
	private function caller(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ProjectActionException(reason: 'planninq-not-logged-in', status: 401, message: 'Not logged in.');
		}

		return $user;
	}//end caller()

	/**
	 * Save one object as the system and return its data with its `id`.
	 *
	 * @param string              $schema The schema slug.
	 * @param string|null         $id     The UUID, null to create.
	 * @param array<string,mixed> $data   The data.
	 *
	 * @return array<string,mixed>
	 */
	private function save(string $schema, ?string $id, array $data): array {
		unset($data['@self'], $data['id']);
		$saved = $this->objectService()->saveObject(
			object: $data,
			register: ProjectMembershipService::REGISTER,
			schema: $schema,
			uuid: $id,
			_rbac: false,
			_multitenancy: false
		);

		return ['id' => (string)$saved->getUuid()] + (array)$saved->getObject();
	}//end save()

	/**
	 * Delete one object as the system.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return void
	 */
	private function deleteAsSystem(string $schema, string $id): void {
		$this->objectService()->deleteObject(uuid: $id, register: ProjectMembershipService::REGISTER, schema: $schema, _rbac: false, _multitenancy: false);
	}//end deleteAsSystem()

	/**
	 * Run writes inside an OpenRegister system operation.
	 *
	 * @param callable $writes The writes.
	 *
	 * @return mixed What the writes return.
	 */
	private function asSystem(callable $writes): mixed {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === true) {
			return $class::run($writes);
		}

		return $writes();
	}//end asSystem()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object
	 *
	 * @throws ProjectActionException When OpenRegister is not installed.
	 */
	private function objectService(): object {
		if ($this->appManager->isInstalled('openregister') === false) {
			throw new ProjectActionException(reason: 'planninq-openregister-missing', status: 503, message: 'OpenRegister is not installed.');
		}

		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()
}//end class
