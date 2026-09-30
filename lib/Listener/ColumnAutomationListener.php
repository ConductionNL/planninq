<?php

/**
 * Planninq ColumnAutomationListener
 *
 * Runs the rules of the board column a task enters, in the same save as the
 * move: set the priority, assign a named project member, assign the person
 * who moved the card, remove the assignee, or add a label. It listens to
 * OpenRegister's pre-save events, so a move from the board, the API, a flow
 * or an import runs the same rules. Data merged by a pre-save listener is not
 * validated again, so every value is checked here; a rule that fails its
 * check is skipped and logged, and the move itself still succeeds.
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
 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Applies a column's "when a card enters this column" rules.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.2
 */
class ColumnAutomationListener implements IEventListener {

	/**
	 * The task schema's priority values (lib/Settings/planninq_register.json).
	 *
	 * @var array<int,string>
	 */
	public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

	/**
	 * The rule actions the column schema allows, in its order.
	 *
	 * @var array<int,string>
	 */
	public const ACTIONS = ['setPriority', 'assign', 'assignMover', 'unassign', 'addLabel'];

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership    Reads the column, the project's members and labels as the system.
	 * @param TaskScopeResolver        $scopeResolver Tells a planninq task from any other object.
	 * @param IUserSession             $userSession   The person who moved the card.
	 * @param LoggerInterface          $logger        The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private TaskScopeResolver $scopeResolver,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a pre-save event.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.2
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent === true) {
			$this->apply(event: $event, object: $event->getObject(), oldColumn: '');
			return;
		}

		if ($event instanceof ObjectUpdatingEvent === true) {
			$old       = $event->getOldObject();
			$oldColumn = '';
			if ($old !== null) {
				$oldColumn = $this->referenceId(value: (((array)$old->getObject())['column'] ?? null));
			}

			$this->apply(event: $event, object: $event->getNewObject(), oldColumn: $oldColumn);
		}
	}//end handle()

	/**
	 * Merge the entered column's rule results into the save.
	 *
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event     The event.
	 * @param object                                  $object    The task being saved.
	 * @param string                                  $oldColumn The stored column, '' on create or from the backlog.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.2
	 */
	private function apply(ObjectCreatingEvent|ObjectUpdatingEvent $event, object $object, string $oldColumn): void {
		$slug = $this->scopeResolver->planninqSchemaSlug(
			registerId: (string)($object->getRegister() ?? ''),
			schemaId: (string)($object->getSchema() ?? '')
		);
		if ($slug !== 'task') {
			return;
		}

		$task      = array_merge((array)$object->getObject(), $event->getModifiedData());
		$newColumn = $this->referenceId(value: ($task['column'] ?? null));
		if ($newColumn === '' || $newColumn === $oldColumn) {
			return;
		}

		$rules = ($this->membership->objectData(schema: 'column', id: $newColumn)['automation'] ?? []);
		if (is_array($rules) === false || $rules === []) {
			return;
		}

		$changes = $this->run(rules: array_values($rules), task: $task, column: $newColumn);
		if ($changes !== []) {
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end apply()

	/**
	 * Turn the rules into field values, in list order, a later rule winning.
	 *
	 * @param array<int,mixed>    $rules  The column's rules.
	 * @param array<string,mixed> $task   The task as it will be saved so far.
	 * @param string              $column The column UUID, for the log.
	 *
	 * @return array<string,mixed> The fields to merge; only those whose value changes.
	 *
	 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.3
	 */
	private function run(array $rules, array $task, string $column): array {
		$changes = [];
		foreach ($rules as $index => $rule) {
			$action = '';
			if (is_array($rule) === true && is_string($rule['action'] ?? null) === true) {
				$action = $rule['action'];
			}

			$value  = '';
			if (is_array($rule) === true && is_scalar($rule['value'] ?? null) === true) {
				$value = (string)$rule['value'];
			}

			$change = $this->ruleChange(action: $action, value: $value, task: array_merge($task, $changes));
			if ($change === null) {
				$this->logger->warning(
					'Planninq: skipped a column rule whose value is not valid',
					['column' => $column, 'rule' => $index, 'action' => $action]
				);
				continue;
			}

			$changes = array_merge($changes, $change);
		}

		foreach ($changes as $field => $newValue) {
			if (($task[$field] ?? null) === $newValue) {
				unset($changes[$field]);
			}
		}

		return $changes;
	}//end run()

	/**
	 * The field one rule sets, or null when the rule cannot run.
	 *
	 * @param string              $action The rule's action.
	 * @param string              $value  The rule's value, '' when it has none.
	 * @param array<string,mixed> $task   The task as it will be saved so far.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/boards-column-automation/tasks.md#task-1.3
	 */
	private function ruleChange(string $action, string $value, array $task): ?array {
		return match ($action) {
			'setPriority' => $this->when(valid: in_array($value, self::PRIORITIES, true), change: ['priority' => $value]),
			'assign'      => $this->when(valid: $this->isProjectMember(task: $task, uid: $value), change: ['assignedTo' => $value]),
			'assignMover' => $this->moverChange(),
			'unassign'    => ['assignedTo' => ''],
			'addLabel'    => $this->labelChange(labelId: $value, task: $task),
			default       => null,
		};
	}//end ruleChange()

	/**
	 * The change when the check passed, null otherwise.
	 *
	 * @param bool                $valid  Whether the rule's value passed its check.
	 * @param array<string,mixed> $change The change.
	 *
	 * @return array<string,mixed>|null
	 */
	private function when(bool $valid, array $change): ?array {
		if ($valid === false) {
			return null;
		}

		return $change;
	}//end when()

	/**
	 * Assign the person who moved the card; nothing without a session (a background job).
	 *
	 * @return array<string,mixed>|null
	 */
	private function moverChange(): ?array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		return ['assignedTo' => $user->getUID()];
	}//end moverChange()

	/**
	 * Append an existing label once.
	 *
	 * @param string              $labelId The label UUID.
	 * @param array<string,mixed> $task    The task as it will be saved so far.
	 *
	 * @return array<string,mixed>|null
	 */
	private function labelChange(string $labelId, array $task): ?array {
		if ($this->membership->objectData(schema: 'label', id: $labelId) === null) {
			return null;
		}

		$labels = ($task['labels'] ?? []);
		if (is_array($labels) === false) {
			$labels = [];
		}

		$labels = array_values($labels);
		if (in_array($labelId, $labels, true) === false) {
			$labels[] = $labelId;
		}

		return ['labels' => $labels];
	}//end labelChange()

	/**
	 * Whether a user is a member (or the owner) of the task's project.
	 *
	 * @param array<string,mixed> $task The task.
	 * @param string              $uid  The user id.
	 *
	 * @return bool
	 */
	private function isProjectMember(array $task, string $uid): bool {
		if ($uid === '') {
			return false;
		}

		$projectId = $this->membership->projectIdFor(schemaSlug: 'task', data: $task);
		$members   = $this->membership->membersOfProject(projectId: $projectId);

		return (is_array($members) === true && in_array($uid, $members, true) === true);
	}//end isProjectMember()

	/**
	 * A reference as a UUID string: a plain string, or an object carrying `id` or `uuid`.
	 *
	 * @param mixed $value The stored reference.
	 *
	 * @return string
	 */
	private function referenceId(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['uuid'] ?? null));
		}

		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end referenceId()
}//end class
