<?php

/**
 * Planninq TaskReferenceGuardListener
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Holds what a task points at to the task's own project, on the server. The
 * board and the forms already keep to these rules, but OpenRegister accepts
 * any UUID and any user id, so a client could:
 *
 * - put a task under another project's column, whose automation rules then
 *   run on it, or link it to another project's epic, parent, phase or release;
 * - put an epic under an epic;
 * - make someone outside the project responsible for a task, or share it with
 *   them, so planninq notifies them of a task they cannot read.
 *
 * Only a reference the save sets or changes is checked, so a task whose stored
 * data predates a rule can still be edited. No session (occ, cron, a repair
 * step) and OpenRegister system operations pass. A refused save answers 422.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/tasks/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\ProjectRoles;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Refuses a task save whose references leave the task's project.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/tasks/spec.md
 */
class TaskReferenceGuardListener implements IEventListener {

	/**
	 * Error code of a refused save.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'planninq-task-reference';

	/**
	 * The reference fields that must point into the task's project, with the schema they point at.
	 *
	 * @var array<string,string>
	 */
	private const SAME_PROJECT = [
		'epic'    => 'task',
		'parent'  => 'task',
		'column'  => 'column',
		'release' => 'projectRelease',
		'phase'   => 'projectPhase',
	];

	/**
	 * The issue type of an epic.
	 *
	 * @var string
	 */
	private const EPIC = 'epic';

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads the project and the referenced objects as the system.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq task from any other object.
	 * @param IUserSession             $userSession   Tells a person's write from occ, cron and repair steps.
	 * @param IUserManager             $userManager   Finds the people a task is given to.
	 * @param IGroupManager            $groupManager  Their groups.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Check a task create or update.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/tasks/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->check(event: $event, run: new TaskReferenceRun(task: $event->getObject(), stored: []));
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->check(event: $event, run: new TaskReferenceRun(task: $event->getNewObject(), stored: (array)$event->getOldObject()?->getObject()));
		}
	}//end handle()

	/**
	 * Refuse the save when a reference it sets leaves the task's project.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The pre-save event.
	 * @param TaskReferenceRun                        $run   The save under check.
	 *
	 * @return void
	 */
	private function check(ObjectCreatingEvent|ObjectUpdatingEvent $event, TaskReferenceRun $run): void {
		if ($this->byPerson() === false || $this->isTask(object: $run->task) === false) {
			return;
		}

		if ($this->readProject(run: $run) === false) {
			// ProjectMemberAccessListener refuses a task without a project.
			return;
		}

		$problem = ($this->referenceProblem(run: $run) ?? $this->peopleProblem(run: $run));
		if ($problem !== null) {
			$event->setErrors(['message' => $problem, 'code' => self::ERROR_CODE, 'project' => $run->projectId]);
			$event->stopPropagation();
		}
	}//end check()

	/**
	 * Read the task's project into the run; false when the project is unknown.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return bool
	 */
	private function readProject(TaskReferenceRun $run): bool {
		$run->projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $run->data);
		$project        = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $run->projectId);
		if ($project === null) {
			return false;
		}

		$run->project = $project;
		return true;
	}//end readProject()

	/**
	 * Why a changed epic, parent, column, release or phase may not be saved, or null.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return string|null
	 */
	private function referenceProblem(TaskReferenceRun $run): ?string {
		foreach (array_keys(self::SAME_PROJECT) as $field) {
			$problem = $this->targetProblem(run: $run, field: $field);
			if ($problem !== null) {
				return $problem;
			}
		}

		return $this->epicNestingProblem(run: $run);
	}//end referenceProblem()

	/**
	 * Why the object a changed reference field points at may not be pointed at, or null.
	 *
	 * @param TaskReferenceRun $run   The save under check.
	 * @param string           $field The reference field.
	 *
	 * @return string|null
	 */
	private function targetProblem(TaskReferenceRun $run, string $field): ?string {
		if ($this->changedReference(run: $run, field: $field) === '') {
			return null;
		}

		$target = $this->projectTarget(run: $run, field: $field);
		if ($target === null) {
			return 'A task can only point at a ' . $field . ' of its own project.';
		}

		return $this->epicTargetProblem(field: $field, target: $target);
	}//end targetProblem()

	/**
	 * The object a changed reference field points at, or null when it is missing, the task itself or in another project.
	 *
	 * @param TaskReferenceRun $run   The save under check.
	 * @param string           $field The reference field.
	 *
	 * @return array<string,mixed>|null
	 */
	private function projectTarget(TaskReferenceRun $run, string $field): ?array {
		$targetId = $this->changedReference(run: $run, field: $field);
		$schema   = self::SAME_PROJECT[$field];
		$target   = $this->membership->objectData(schema: $schema, id: $targetId);
		if ($target === null || $targetId === $run->ownId || $this->membership->projectIdFor(schemaSlug: $schema, data: $target) !== $run->projectId) {
			return null;
		}

		return $target;
	}//end projectTarget()

	/**
	 * Why a task may not sit in the object its epic field points at, or null.
	 *
	 * @param string              $field  The reference field.
	 * @param array<string,mixed> $target The referenced object.
	 *
	 * @return string|null
	 */
	private function epicTargetProblem(string $field, array $target): ?string {
		if ($field === 'epic' && ($target['issueType'] ?? null) !== self::EPIC) {
			return 'Only an epic can hold tasks as an epic.';
		}

		return null;
	}//end epicTargetProblem()

	/**
	 * Why an epic whose epic or issue type changes may not be saved under an epic, or null.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return string|null
	 */
	private function epicNestingProblem(TaskReferenceRun $run): ?string {
		$isEpic      = (($run->data['issueType'] ?? null) === self::EPIC);
		$epicChanged = $this->epicOrTypeChanged(run: $run);
		if ($isEpic === true && $epicChanged === true && $this->referenceId(value: ($run->data['epic'] ?? null)) !== '') {
			return 'An epic cannot sit in another epic.';
		}

		return null;
	}//end epicNestingProblem()

	/**
	 * Whether a save changes the task's epic or its issue type.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return bool
	 */
	private function epicOrTypeChanged(TaskReferenceRun $run): bool {
		return ($this->changedReference(run: $run, field: 'epic') !== ''
			|| ($run->stored['issueType'] ?? null) !== ($run->data['issueType'] ?? null));
	}//end epicOrTypeChanged()

	/**
	 * Why a newly responsible or newly sharing person may not be saved, or null.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return string|null
	 */
	private function peopleProblem(TaskReferenceRun $run): ?string {
		foreach ($this->addedPeople(run: $run) as $uid) {
			if ($this->worksOn(project: $run->project, uid: (string)$uid) === false) {
				return 'Only members of the project can be responsible for a task or work on it.';
			}
		}

		return null;
	}//end peopleProblem()

	/**
	 * The people a save newly shares the task with, then a newly responsible person.
	 *
	 * @param TaskReferenceRun $run The save under check.
	 *
	 * @return array<int|string,mixed>
	 */
	private function addedPeople(TaskReferenceRun $run): array {
		$added = $this->membership->normalise(members: ($run->data['sharedWith'] ?? null));
		$added = array_diff($added, $this->membership->normalise(members: ($run->stored['sharedWith'] ?? null)));

		$assignee = ($run->data['assignedTo'] ?? null);
		if (is_string($assignee) === true && $assignee !== '' && $assignee !== ($run->stored['assignedTo'] ?? null)) {
			$added[] = $assignee;
		}

		return $added;
	}//end addedPeople()

	/**
	 * Whether a person writes the project's work: its owner, a manager or member, or in a writer group.
	 *
	 * @param array<string,mixed> $project The project.
	 * @param string              $uid     The user id.
	 *
	 * @return bool
	 */
	private function worksOn(array $project, string $uid): bool {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return false;
		}

		return (new ProjectRoles())->mayWrite(project: $project, uid: $uid, groupIds: $this->groupManager->getUserGroupIds($user));
	}//end worksOn()

	/**
	 * The reference a save sets in a field, or '' when it clears it or leaves it as stored.
	 *
	 * @param TaskReferenceRun $run   The save under check.
	 * @param string           $field The field.
	 *
	 * @return string
	 */
	private function changedReference(TaskReferenceRun $run, string $field): string {
		$sent = $this->referenceId(value: ($run->data[$field] ?? null));
		if ($sent === $this->referenceId(value: ($run->stored[$field] ?? null))) {
			return '';
		}

		return $sent;
	}//end changedReference()

	/**
	 * A reference as a UUID string: a plain string, or an object carrying `id`.
	 *
	 * @param mixed $value The reference.
	 *
	 * @return string
	 */
	private function referenceId(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? null);
		}

		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end referenceId()

	/**
	 * Whether the object is a planninq task.
	 *
	 * @param object $object The object.
	 *
	 * @return bool
	 */
	private function isTask(object $object): bool {
		return $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		) === 'task';
	}//end isTask()

	/**
	 * Whether a signed-in person writes, not occ, cron or a system operation.
	 *
	 * @return bool
	 */
	private function byPerson(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return ($this->userSession->getUser() !== null && (class_exists($class) === false || $class::isActive() === false));
	}//end byPerson()
}//end class
