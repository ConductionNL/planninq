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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;

/**
 * Server-side multi-object actions on projects, releases and task trees.
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
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership  Reads planninq objects as the system.
	 * @param ProjectActionSupport     $support     Checks the caller and writes as the system.
	 * @param ITimeFactory             $timeFactory The server clock.
	 * @param IL10N                    $l10n        Names a copy.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ProjectActionSupport $support,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
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
		$project = $this->support->readable(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		$user    = $this->support->caller();
		if ($this->support->isAdmin(user: $user) === false && $this->support->ownsProject(project: $project, user: $user) === false) {
			throw new ProjectActionException(
				reason: 'planninq-project-delete-not-allowed',
				status: 403,
				message: 'Only the project owner can delete this project.'
			);
		}

		$doomed = $this->projectObjects(projectId: $projectId);
		$this->support->asSystem(
			writes: function () use ($doomed): void {
				foreach ($doomed as [$schema, $id]) {
					$this->support->deleteAsSystem(schema: $schema, id: $id);
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
		$release   = $this->support->readable(schema: 'projectRelease', id: $releaseId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'projectRelease', data: $release);
		$this->support->assertWrites(projectId: $projectId);
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

		return $this->support->asSystem(
			writes: function () use ($moves, $value, $released, $releaseId): array {
				foreach ($moves as $task) {
					$this->support->save(schema: 'task', id: $task['id'], data: array_merge($task['data'], ['release' => $value]));
				}

				return $this->support->save(schema: 'projectRelease', id: $releaseId, data: $released);
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

		$task     = $this->support->readable(schema: 'task', id: $taskId);
		$children = $this->membership->rows(schema: 'task', filters: ['parent' => $taskId]);
		$going    = [['id' => $taskId, 'data' => $task]];
		if ($mode === 'delete') {
			$going = array_merge($children, $going);
		}

		$owner = $this->membership->ownerOfProject(projectId: $this->membership->projectIdFor(schemaSlug: 'task', data: $task));
		$this->assertMayDeleteTasks(tasks: $going, owner: $owner);

		$this->support->asSystem(
			writes: function () use ($mode, $children, $going): void {
				if ($mode === 'detach') {
					foreach ($children as $child) {
						$this->support->save(schema: 'task', id: $child['id'], data: array_merge($child['data'], ['parent' => null]));
					}
				}

				foreach ($going as $one) {
					$this->support->deleteAsSystem(schema: 'task', id: $one['id']);
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
		$task      = $this->support->readable(schema: 'task', id: $taskId);
		$projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $task);
		$this->support->assertWrites(projectId: $projectId);
		$children = $this->membership->rows(schema: 'task', filters: ['parent' => $taskId]);
		$title    = str_replace('{title}', (string)($task['title'] ?? ''), $this->l10n->t('Copy of {title}'));
		$reporter = $this->support->caller()->getUID();

		return $this->support->asSystem(
			writes: function () use ($task, $title, $children, $reporter): array {
				$copy   = $this->copyOf(task: $task, title: $title, parent: null);
				$copy   = $this->support->save(schema: 'task', id: null, data: $copy + ['reporter' => $reporter]);
				$copyId = (string)$copy['id'];
				foreach ($children as $child) {
					$subtask = $this->copyOf(task: $child['data'], title: (string)($child['data']['title'] ?? ''), parent: $copyId);
					$this->support->save(schema: 'task', id: null, data: $subtask + ['reporter' => $reporter]);
				}

				return $copy;
			}
		);
	}//end duplicateTask()

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
		$user = $this->support->caller();
		$uid  = $user->getUID();
		foreach ($tasks as $task) {
			if ($this->support->isAdmin(user: $user) === false && $owner !== $uid && ($task['data']['reporter'] ?? null) !== $uid) {
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

}//end class
