<?php

/**
 * Planninq TaskReferenceGuardListener
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
 * @spec openspec/specs/tasks.md
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
 * @spec openspec/specs/tasks.md
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
	 * @spec openspec/specs/tasks.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->check(event: $event, object: $event->getObject(), stored: []);
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$this->check(event: $event, object: $event->getNewObject(), stored: (array)$event->getOldObject()?->getObject());
		}
	}//end handle()

	/**
	 * Refuse the save when a reference it sets leaves the task's project.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event  The pre-save event.
	 * @param object                                  $object The task being written.
	 * @param array<string,mixed>                     $stored The stored task, empty on a create.
	 *
	 * @return void
	 */
	private function check(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, array $stored): void {
		if ($this->byPerson() === false || $this->isTask(object: $object) === false) {
			return;
		}

		$data      = (array)$object->getObject();
		$projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $data);
		$project   = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		if ($project === null) {
			// ProjectMemberAccessListener refuses a task without a project.
			return;
		}

		$problem = $this->referenceProblem(data: $data, stored: $stored, projectId: $projectId, ownId: (string)($object->getUuid() ?? ''));
		if ($problem === null) {
			$problem = $this->peopleProblem(data: $data, stored: $stored, project: $project);
		}

		if ($problem !== null) {
			$event->setErrors(['message' => $problem, 'code' => self::ERROR_CODE, 'project' => $projectId]);
			$event->stopPropagation();
		}
	}//end check()

	/**
	 * Why a changed epic, parent, column, release or phase may not be saved, or null.
	 *
	 * @param array<string,mixed> $data      The task being written.
	 * @param array<string,mixed> $stored    The stored task, empty on a create.
	 * @param string              $projectId The task's project.
	 * @param string              $ownId     The task's own UUID, '' on a create.
	 *
	 * @return string|null
	 */
	private function referenceProblem(array $data, array $stored, string $projectId, string $ownId): ?string {
		foreach (self::SAME_PROJECT as $field => $schema) {
			$targetId = $this->changedReference(data: $data, stored: $stored, field: $field);
			if ($targetId === '') {
				continue;
			}

			$problem = $this->targetProblem(field: $field, schema: $schema, targetId: $targetId, projectId: $projectId, ownId: $ownId);
			if ($problem !== null) {
				return $problem;
			}
		}

		$isEpic      = (($data['issueType'] ?? null) === self::EPIC);
		$epicChanged = ($this->changedReference(data: $data, stored: $stored, field: 'epic') !== ''
			|| ($stored['issueType'] ?? null) !== ($data['issueType'] ?? null));
		if ($isEpic === true && $epicChanged === true && $this->referenceId(value: ($data['epic'] ?? null)) !== '') {
			return 'An epic cannot sit in another epic.';
		}

		return null;
	}//end referenceProblem()

	/**
	 * Why one referenced object may not be pointed at, or null.
	 *
	 * @param string $field     The reference field.
	 * @param string $schema    The schema it points at.
	 * @param string $targetId  The referenced UUID.
	 * @param string $projectId The task's project.
	 * @param string $ownId     The task's own UUID.
	 *
	 * @return string|null
	 */
	private function targetProblem(string $field, string $schema, string $targetId, string $projectId, string $ownId): ?string {
		$target = $this->membership->objectData(schema: $schema, id: $targetId);
		if ($target === null || $targetId === $ownId || $this->membership->projectIdFor(schemaSlug: $schema, data: $target) !== $projectId) {
			return 'A task can only point at a ' . $field . ' of its own project.';
		}

		if ($field === 'epic' && ($target['issueType'] ?? null) !== self::EPIC) {
			return 'Only an epic can hold tasks as an epic.';
		}

		return null;
	}//end targetProblem()

	/**
	 * Why a newly responsible or newly sharing person may not be saved, or null.
	 *
	 * @param array<string,mixed> $data    The task being written.
	 * @param array<string,mixed> $stored  The stored task, empty on a create.
	 * @param array<string,mixed> $project The task's project.
	 *
	 * @return string|null
	 */
	private function peopleProblem(array $data, array $stored, array $project): ?string {
		$added = $this->membership->normalise(members: ($data['sharedWith'] ?? null));
		$added = array_diff($added, $this->membership->normalise(members: ($stored['sharedWith'] ?? null)));

		$assignee = ($data['assignedTo'] ?? null);
		if (is_string($assignee) === true && $assignee !== '' && $assignee !== ($stored['assignedTo'] ?? null)) {
			$added[] = $assignee;
		}

		foreach ($added as $uid) {
			if ($this->worksOn(project: $project, uid: (string)$uid) === false) {
				return 'Only members of the project can be responsible for a task or work on it.';
			}
		}

		return null;
	}//end peopleProblem()

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
	 * @param array<string,mixed> $data   The task being written.
	 * @param array<string,mixed> $stored The stored task.
	 * @param string              $field  The field.
	 *
	 * @return string
	 */
	private function changedReference(array $data, array $stored, string $field): string {
		$sent = $this->referenceId(value: ($data[$field] ?? null));
		if ($sent === $this->referenceId(value: ($stored[$field] ?? null))) {
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
