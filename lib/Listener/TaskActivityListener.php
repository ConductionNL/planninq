<?php

/**
 * Planninq TaskActivityListener.
 *
 * Listens for OpenRegister object events and publishes Planninq task lifecycle
 * events (created / status changed / assigned / due date changed / deleted)
 * into the Nextcloud Activity stream for the task's project members.
 *
 * This is an activity-stream record — NOT a notification (ADR-031 / gate-18).
 * It never calls the notification engine; it only renders history into the
 * Activity app via {@see \OCP\Activity\IManager}. The listener is scoped to the
 * Planninq register's `task` schema and is defensively wrapped so a malformed
 * event or unavailable dependency can never break OpenRegister's event
 * dispatch.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\AppInfo\Application;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Publishes Planninq task activity from OpenRegister object events.
 *
 * @implements IEventListener<Event>
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class TaskActivityListener implements IEventListener {
	/**
	 * The activity app type this app publishes under.
	 *
	 * Renamed with the app id, in lockstep with Activity\Filter::ACTIVITY_TYPE
	 * and the `app` column stamped in publish() below — see the note there.
	 *
	 * @var string
	 */
	private const ACTIVITY_TYPE = 'planninq_task';

	/**
	 * Tracked field => [subject, parameter], in precedence order.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const TRACKED = [
		'status'     => ['task_status_changed', 'status'],
		'assignedTo' => ['task_assigned_activity', 'assignee'],
		'dueDate'    => ['task_due_date_changed', 'dueDate'],
	];

	/**
	 * Constructor.
	 *
	 * @param IActivityManager $activityManager The NC activity manager.
	 * @param IUserSession $userSession The current user session.
	 * @param TaskScopeResolver $scopeResolver Resolves task scope + project members from OR.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private IActivityManager $activityManager,
		private IUserSession $userSession,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle an incoming OpenRegister object event.
	 *
	 * The whole body is wrapped so a malformed event, an unresolvable project,
	 * or an unavailable OR dependency logs-and-skips rather than throwing out of
	 * OpenRegister's dispatcher.
	 *
	 * @param Event $event The event to handle.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function handle(Event $event): void {
		try {
			$change = $this->changeOf(event: $event);
			if ($change !== null) {
				$this->onChange(change: $change);
			}
		} catch (\Throwable $e) {
			// OR's dispatch must never break because Planninq activity failed.
			$this->logger->warning(
				'Planninq: task activity listener skipped an event',
				['exception' => $e->getMessage()]
			);
		}//end try
	}//end handle()

	/**
	 * The change an event carries: the (new) object, the previous data and whether it is a deletion.
	 *
	 * @param Event $event The event.
	 *
	 * @return array{object:object,old:array<string,mixed>|null,deleted:bool}|null Null for any other event.
	 */
	private function changeOf(Event $event): ?array {
		if ($event instanceof ObjectCreatedEvent === true) {
			return ['object' => $event->getObject(), 'old' => null, 'deleted' => false];
		}

		if ($event instanceof ObjectUpdatedEvent === true) {
			return ['object' => $event->getNewObject(), 'old' => $event->getOldObject()?->getObject(), 'deleted' => false];
		}

		if ($event instanceof ObjectDeletedEvent === true) {
			return ['object' => $event->getObject(), 'old' => null, 'deleted' => true];
		}

		return null;
	}//end changeOf()

	/**
	 * Process one object change: scope to the Planninq task schema, pick the
	 * subject, resolve the audience and publish.
	 *
	 * @param array{object:object,old:array<string,mixed>|null,deleted:bool} $change The change.
	 *
	 * @return void
	 */
	private function onChange(array $change): void {
		$change['data'] = $this->taskData(object: $change['object']);
		if ($change['data'] === null) {
			return;
		}

		$subjectParams = $this->selectSubject(change: $change);
		if ($subjectParams === null) {
			// No activity-worthy change (e.g. an update that touched none of the
			// tracked fields).
			return;
		}

		$activity = $this->activityOf(subjectParams: $subjectParams, change: $change);
		foreach ($this->resolveAudience(taskData: $change['data'], actor: $activity['actor']) as $affectedUser) {
			$this->publish(activity: $activity, affectedUser: $affectedUser);
		}
	}//end onChange()

	/**
	 * The data of a Planninq task, null for any other object or data that is not a list.
	 *
	 * @param object $object The (new) object entity.
	 *
	 * @return array<string,mixed>|null
	 */
	private function taskData(object $object): ?array {
		$registerId = (string)($object->getRegister() ?? '');
		$schemaId   = (string)($object->getSchema() ?? '');
		if ($this->scopeResolver->isPlanninqTask(registerId: $registerId, schemaId: $schemaId) === false) {
			return null;
		}

		$data = $object->getObject();
		if (is_array($data) === false) {
			return null;
		}

		return $data;
	}//end taskData()

	/**
	 * The activity to publish: subject, acting user and the subject parameters with actor, title and task UUID.
	 *
	 * @param array{0:string,1:array<string,string>} $subjectParams The subject and its parameters.
	 * @param array<string,mixed>                    $change        The change with the task data.
	 *
	 * @return array{subject:string,actor:string,params:array<string,string>}
	 */
	private function activityOf(array $subjectParams, array $change): array {
		[$subject, $params] = $subjectParams;
		$actor              = $this->currentActor();

		$params['actor']    = $actor;
		$params['title']    = $this->stringify(value: ($change['data']['title'] ?? ''));
		$params['objectId'] = (string)($change['object']->getUuid() ?? '');

		return ['subject' => $subject, 'actor' => $actor, 'params' => $params];
	}//end activityOf()

	/**
	 * Pick the activity subject + params for a change by diffing tracked fields.
	 *
	 * Precedence on update: status > assignee > due date (one entry per event,
	 * matching the most significant change).
	 *
	 * @param array<string,mixed> $change The change with the new task data.
	 *
	 * @return array{0:string,1:array<string,string>}|null The subject + params, or null when nothing is activity-worthy.
	 */
	private function selectSubject(array $change): ?array {
		if ($change['deleted'] === true) {
			return ['task_deleted', []];
		}

		if ($change['old'] === null) {
			return ['task_created', []];
		}

		return $this->updateSubject(change: $change);
	}//end selectSubject()

	/**
	 * The subject + params of the most significant tracked field an update changes.
	 *
	 * @param array<string,mixed> $change The change with the new and the previous task data.
	 *
	 * @return array{0:string,1:array<string,string>}|null Null when the update changes none of them.
	 */
	private function updateSubject(array $change): ?array {
		foreach (self::TRACKED as $field => [$subject, $param]) {
			$new = $this->stringify(value: ($change['data'][$field] ?? ''));
			if ($new !== $this->stringify(value: ($change['old'][$field] ?? ''))) {
				return [$subject, [$param => $new]];
			}
		}

		return null;
	}//end updateSubject()

	/**
	 * Resolve the audience for a task event: the members of the task's project,
	 * minus the acting user (NC convention — no activity for your own change).
	 *
	 * Falls back to the task's assignee when the project cannot be resolved, so
	 * a member still gets the entry; returns an empty list (silently) when there
	 * is no resolvable audience. The people in `sharedWith` are added too.
	 *
	 * @param array  $taskData The task data (carries `project` UUID, `assignedTo` and `sharedWith`).
	 * @param string $actor    The acting user id (excluded from the result).
	 *
	 * @return string[] Distinct affected user ids.
	 *
	 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.3
	 */
	private function resolveAudience(array $taskData, string $actor): array {
		$distinct = [];
		foreach ($this->candidates(taskData: $taskData) as $member) {
			$member = (string)$member;
			if ($member === '' || $member === $actor || isset($distinct[$member]) === true) {
				continue;
			}

			$distinct[$member] = true;
		}

		return array_keys($distinct);
	}//end resolveAudience()

	/**
	 * Everyone a task event may reach, in order: project members, the assignee, the people it is shared with.
	 *
	 * @param array $taskData The task data.
	 *
	 * @return array<int,mixed>
	 */
	private function candidates(array $taskData): array {
		$members = $this->scopeResolver->projectMembers(
			projectId: $this->stringify(value: ($taskData['project'] ?? ''))
		);

		$assignee = $this->stringify(value: ($taskData['assignedTo'] ?? ''));
		if ($assignee !== '') {
			$members[] = $assignee;
		}

		// The people the task is shared with (tasks-assignment-priority-labels).
		foreach ((array)($taskData['sharedWith'] ?? []) as $sharer) {
			$members[] = $this->stringify(value: $sharer);
		}

		return $members;
	}//end candidates()

	/**
	 * Publish a single activity event to one affected user.
	 *
	 * @param array{subject:string,actor:string,params:array<string,string>} $activity     The subject, the author and the
	 *                                                                                     parameters (the task UUID under `objectId`).
	 * @param string                                                         $affectedUser The recipient user id.
	 *
	 * @return void
	 */
	private function publish(array $activity, string $affectedUser): void {
		try {
			$event = $this->activityManager->generateEvent();
			// Activity\Filter::allowedApps() narrows the stream on APP_ID, so the
			// app column reads the constant.
			$event->setApp(Application::APP_ID)
				->setType(self::ACTIVITY_TYPE)
				->setAuthor($activity['actor'])
				->setTimestamp(time())
				->setSubject(subject: $activity['subject'], parameters: $activity['params'])
				->setObject(objectType: self::ACTIVITY_TYPE, objectId: 0, objectName: ($activity['params']['title'] ?? ''))
				->setAffectedUser($affectedUser);

			$this->activityManager->publish($event);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: failed to publish task activity', ['exception' => $e->getMessage()]);
		}
	}//end publish()

	/**
	 * Resolve the acting user's id, or '' when there is no session user.
	 *
	 * @return string The current user id.
	 */
	private function currentActor(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}

		return $user->getUID();
	}//end currentActor()

	/**
	 * Coerce a (possibly translatable-map / array) value to a display string.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string The string value, or '' when not coercible.
	 */
	private function stringify(mixed $value): string {
		if (is_scalar($value) === true) {
			return (string)$value;
		}

		if (is_array($value) === true) {
			return $this->translated(value: $value);
		}

		return '';
	}//end stringify()

	/**
	 * The first scalar translation of a translatable map, in the order en, en_GB, en_US, nl, nl_NL.
	 *
	 * @param array<mixed> $value The map.
	 *
	 * @return string '' when it has none.
	 */
	private function translated(array $value): string {
		foreach (['en', 'en_GB', 'en_US', 'nl', 'nl_NL'] as $lang) {
			if (isset($value[$lang]) === true && is_scalar($value[$lang]) === true) {
				return (string)$value[$lang];
			}
		}

		return '';
	}//end translated()
}//end class
