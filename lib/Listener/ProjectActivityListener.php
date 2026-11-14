<?php

/**
 * Planninq ProjectActivityListener
 *
 * Publishes project events to the Nextcloud activity stream: a project
 * created, archived, restored or deleted, and a person added to or removed
 * from it. The audience is the project's people (owner, managers and
 * members, and a person just removed, who should learn of it); the person who
 * made the change gets no entry. The entries carry the Planninq app id, so
 * they show under the Planninq filter beside the task events.
 *
 * Bound to the `project` schema of the planninq register only (Application),
 * and re-checked here, as TaskActivityListener does for tasks.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
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
 * Project created, archived, restored, deleted and membership changes, to Activity.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
 */
class ProjectActivityListener implements IEventListener {
	/**
	 * The activity type of project events; the Planninq filter lists it.
	 *
	 * @var string
	 */
	public const ACTIVITY_TYPE = 'planninq_project';

	/**
	 * The status of an archived project.
	 *
	 * @var string
	 */
	private const ARCHIVED = 'archived';

	/**
	 * Constructor.
	 *
	 * @param IActivityManager  $activityManager Publishes the entries
	 * @param IUserSession      $userSession     Who made the change
	 * @param TaskScopeResolver $scopeResolver   Tells a planninq project from any other object
	 * @param LoggerInterface   $logger          Logs a skipped event
	 *
	 * @return void
	 */
	public function __construct(
		private IActivityManager $activityManager,
		private IUserSession $userSession,
		private TaskScopeResolver $scopeResolver,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Publish the activity entries one project write stands for.
	 *
	 * A failure is logged and swallowed: the activity stream must never fail
	 * the write that caused it.
	 *
	 * @param Event $event The OpenRegister object event
	 *
	 * @return void
	 *
	 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
	 */
	public function handle(Event $event): void {
		try {
			[$object, $old, $deleted] = $this->changeOf(event: $event);
			if ($object === null || $this->scopeResolver->isObjectOfSchema(entity: $object, schema: TaskScopeResolver::PROJECT_SCHEMA_SLUG) === false) {
				return;
			}

			$data = $object->getObject();
			if (is_array($data) === false) {
				return;
			}

			$actor = $this->currentActor();
			foreach ($this->entriesFor(data: $data, old: $old, deleted: $deleted) as [$subject, $extra, $audience]) {
				$params = [
					'actor'    => $actor,
					'title'    => $this->text(value: ($data['title'] ?? '')),
					'objectId' => (string) ($object->getUuid() ?? ''),
				] + $extra;
				foreach ($this->without(people: $audience, actor: $actor) as $affectedUser) {
					$this->publish(subject: $subject, params: $params, affectedUser: $affectedUser);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: project activity listener skipped an event', ['exception' => $e->getMessage()]);
		}//end try
	}//end handle()

	/**
	 * The entries a change stands for: subject, extra parameters and audience.
	 *
	 * @param array<string, mixed>      $data    The project as it is now (or was, when deleted)
	 * @param array<string, mixed>|null $old     The project before an update, null on create or delete
	 * @param bool                      $deleted Whether it was deleted
	 *
	 * @return list<array{0: string, 1: array<string, string>, 2: list<string>}>
	 *
	 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
	 */
	public function entriesFor(array $data, ?array $old, bool $deleted): array {
		$people = $this->peopleOf(data: $data);
		if ($deleted === true) {
			return [['project_deleted', [], $people]];
		}

		if ($old === null) {
			return [['project_created', [], $people]];
		}

		$entries = [];
		$wasArchived = $this->text(value: ($old['status'] ?? '')) === self::ARCHIVED;
		$isArchived = $this->text(value: ($data['status'] ?? '')) === self::ARCHIVED;
		if ($isArchived === true && $wasArchived === false) {
			$entries[] = ['project_archived', [], $people];
		}

		if ($isArchived === false && $wasArchived === true) {
			$entries[] = ['project_restored', [], $people];
		}

		$before = $this->members(data: $old);
		$after = $this->members(data: $data);
		foreach (array_values(array_diff($after, $before)) as $added) {
			$entries[] = ['project_member_added', ['member' => $added], $people];
		}

		foreach (array_values(array_diff($before, $after)) as $removed) {
			$entries[] = ['project_member_removed', ['member' => $removed], array_merge($people, [$removed])];
		}

		return $entries;
	}//end entriesFor()

	/**
	 * Who belongs to the project: its owner, managers and members.
	 *
	 * @param array<string, mixed> $data The project
	 *
	 * @return list<string>
	 */
	private function peopleOf(array $data): array {
		$people = array_merge([$this->text(value: ($data['owner'] ?? ''))], $this->list(value: ($data['managers'] ?? [])), $this->members(data: $data));

		return array_values(array_unique(array_filter($people, static fn (string $uid): bool => $uid !== '')));
	}//end peopleOf()

	/**
	 * The project's members as user ids.
	 *
	 * @param array<string, mixed> $data The project
	 *
	 * @return list<string>
	 */
	private function members(array $data): array {
		return $this->list(value: ($data['members'] ?? []));
	}//end members()

	/**
	 * A list of user ids, without blanks or repeats.
	 *
	 * @param mixed $value The stored value
	 *
	 * @return list<string>
	 */
	private function list(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		$out = [];
		foreach ($value as $item) {
			$uid = $this->text(value: $item);
			if ($uid !== '') {
				$out[$uid] = $uid;
			}
		}

		return array_values($out);
	}//end list()

	/**
	 * The audience without the person who made the change.
	 *
	 * @param list<string> $people The audience
	 * @param string       $actor  Who made the change
	 *
	 * @return list<string>
	 */
	private function without(array $people, string $actor): array {
		return array_values(array_unique(array_filter($people, static fn (string $uid): bool => $uid !== '' && $uid !== $actor)));
	}//end without()

	/**
	 * Publish one entry to one user.
	 *
	 * @param string                $subject      The subject
	 * @param array<string, string> $params       Its parameters
	 * @param string                $affectedUser Whose stream it goes to
	 *
	 * @return void
	 */
	private function publish(string $subject, array $params, string $affectedUser): void {
		try {
			$event = $this->activityManager->generateEvent();
			$event->setApp(Application::APP_ID)
				->setType(self::ACTIVITY_TYPE)
				->setAuthor($params['actor'])
				->setTimestamp(time())
				->setSubject(subject: $subject, parameters: $params)
				->setObject(objectType: self::ACTIVITY_TYPE, objectId: 0, objectName: $params['title'])
				->setAffectedUser($affectedUser);

			$this->activityManager->publish($event);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: failed to publish project activity', ['exception' => $e->getMessage()]);
		}
	}//end publish()

	/**
	 * The object, its data before an update, and whether it was deleted.
	 *
	 * @param Event $event The event
	 *
	 * @return array{0: object|null, 1: array<string, mixed>|null, 2: bool}
	 */
	private function changeOf(Event $event): array {
		if ($event instanceof ObjectCreatedEvent === true) {
			return [$event->getObject(), null, false];
		}

		if ($event instanceof ObjectUpdatedEvent === true) {
			$old = $event->getOldObject()?->getObject();
			if (is_array($old) === false) {
				$old = [];
			}

			return [$event->getNewObject(), $old, false];
		}

		if ($event instanceof ObjectDeletedEvent === true) {
			return [$event->getObject(), null, true];
		}

		return [null, null, false];
	}//end changeOf()

	/**
	 * The signed-in user, or '' for a system write.
	 *
	 * @return string
	 */
	private function currentActor(): string {
		return (string) ($this->userSession->getUser()?->getUID() ?? '');
	}//end currentActor()

	/**
	 * A scalar as text; anything else is ''.
	 *
	 * @param mixed $value The value
	 *
	 * @return string
	 */
	private function text(mixed $value): string {
		if (is_scalar($value) === true) {
			return trim((string) $value);
		}

		return '';
	}//end text()
}//end class
