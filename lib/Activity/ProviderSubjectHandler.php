<?php

/**
 * Planninq Activity ProviderSubjectHandler.
 *
 * Applies the human-readable subject text and rich parameters to a Planninq task
 * activity event, keyed by the event subject. Pulled out of {@see Provider} so
 * the subject rendering can be unit-tested in isolation.
 *
 * @category Activity
 * @package  OCA\Planninq\Activity
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Activity;

use OCP\Activity\IEvent;

/**
 * Applies subject text and rich parameters to Planninq task activity events.
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class ProviderSubjectHandler {
	/**
	 * Apply the parsed + rich subject for the event's subject key.
	 *
	 * The actor and task title are always rendered as rich parameters; the
	 * status / assignee / due-date variants append the changed value. Unknown
	 * subjects are left untouched (the {@see Provider} only forwards the five
	 * handled keys). The subject parameters are read from the event.
	 *
	 * @param IEvent $event The event to mutate.
	 * @param object $l The l10n translator (IL10N).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function applySubjectText(IEvent $event, object $l): void {
		if (str_starts_with($event->getSubject(), 'project_') === true) {
			$this->applyProjectSubject(event: $event, l: $l);
			return;
		}

		$rich  = $this->buildRichParams(event: $event);
		$texts = $this->subjectTexts(event: $event, l: $l);
		if ($texts === null) {
			return;
		}

		$event->setParsedSubject($texts['parsed']);
		$event->setRichSubject(subject: $texts['rich'], parameters: $rich);
	}//end applySubjectText()

	/**
	 * The parsed and rich texts of a created or deleted task, else of a change.
	 *
	 * @param IEvent $event The event.
	 * @param object $l The l10n translator (IL10N).
	 *
	 * @return array{parsed:string,rich:string}|null Null for an unhandled subject.
	 */
	private function subjectTexts(IEvent $event, object $l): ?array {
		[$actor, $title] = $this->actorAndTitle(event: $event);

		return match ($event->getSubject()) {
			'task_created' => [
				'parsed' => $l->t('%1$s created task %2$s', [$actor, $title]),
				'rich' => $l->t('{actor} created task {task}'),
			],
			'task_deleted' => [
				'parsed' => $l->t('%1$s deleted task %2$s', [$actor, $title]),
				'rich' => $l->t('{actor} deleted task {task}'),
			],
			default => $this->changeTexts(event: $event, l: $l),
		};
	}//end subjectTexts()

	/**
	 * The parsed and rich texts of a status or assignee change, else of a due-date change.
	 *
	 * Each text appends the changed value from the subject parameters.
	 *
	 * @param IEvent $event The event.
	 * @param object $l The l10n translator (IL10N).
	 *
	 * @return array{parsed:string,rich:string}|null Null for an unhandled subject.
	 */
	private function changeTexts(IEvent $event, object $l): ?array {
		[$actor, $title] = $this->actorAndTitle(event: $event);
		$params = $event->getSubjectParameters();

		return match ($event->getSubject()) {
			'task_status_changed' => [
				'parsed' => $l->t('%1$s changed the status of %2$s to %3$s', [$actor, $title, (string)($params['status'] ?? '')]),
				'rich' => $l->t('{actor} changed the status of {task} to %1$s', [(string)($params['status'] ?? '')]),
			],
			'task_assigned_activity' => [
				'parsed' => $l->t('%1$s assigned %2$s to %3$s', [$actor, $title, (string)($params['assignee'] ?? '')]),
				'rich' => $l->t('{actor} assigned {task} to %1$s', [(string)($params['assignee'] ?? '')]),
			],
			default => $this->dueDateTexts(event: $event, l: $l),
		};
	}//end changeTexts()

	/**
	 * The parsed and rich texts of a due-date change.
	 *
	 * @param IEvent $event The event.
	 * @param object $l The l10n translator (IL10N).
	 *
	 * @return array{parsed:string,rich:string}|null Null for an unhandled subject.
	 */
	private function dueDateTexts(IEvent $event, object $l): ?array {
		if ($event->getSubject() !== 'task_due_date_changed') {
			return null;
		}

		[$actor, $title] = $this->actorAndTitle(event: $event);
		$dueDate         = (string)($event->getSubjectParameters()['dueDate'] ?? '');

		return [
			'parsed' => $l->t('%1$s changed the due date of %2$s to %3$s', [$actor, $title, $dueDate]),
			'rich' => $l->t('{actor} changed the due date of {task} to %1$s', [$dueDate]),
		];
	}//end dueDateTexts()

	/**
	 * The acting user's display id and the task title from the subject parameters.
	 *
	 * @param IEvent $event The event.
	 *
	 * @return array{0:string,1:string}
	 */
	private function actorAndTitle(IEvent $event): array {
		$params = $event->getSubjectParameters();

		return [(string)($params['actor'] ?? ''), (string)($params['title'] ?? '')];
	}//end actorAndTitle()

	/**
	 * Build the rich-subject parameter map shared by every subject.
	 *
	 * The task UUID comes from the subject parameters, else from the event's object id.
	 *
	 * @param IEvent $event The event.
	 *
	 * @return array<string, array<string, string>> The rich parameter map.
	 */
	private function buildRichParams(IEvent $event): array {
		[$actor, $title] = $this->actorAndTitle(event: $event);

		return [
			'actor' => [
				'type' => 'user',
				'id' => $actor,
				'name' => $actor,
			],
			'task' => [
				'type' => 'highlight',
				'id' => (string)($event->getSubjectParameters()['objectId'] ?? $event->getObjectId()),
				'name' => $title,
			],
		];
	}//end buildRichParams()
	/**
	 * The text of a project event: the rich subject with its placeholders, and
	 * the same sentence with the names filled in as the plain subject.
	 *
	 * @param IEvent $event The event
	 * @param object $l     The app's IL10N for the reader's language
	 *
	 * @return void
	 *
	 * @spec openspec/changes/project-events-in-activity/specs/task-collaboration/spec.md#requirement-project-events-are-published-to-nextcloud-activity
	 */
	private function applyProjectSubject(IEvent $event, object $l): void {
		$rich = match ($event->getSubject()) {
			'project_created' => $l->t('{actor} created project {project}'),
			'project_archived' => $l->t('{actor} archived project {project}'),
			'project_restored' => $l->t('{actor} restored project {project}'),
			'project_deleted' => $l->t('{actor} deleted project {project}'),
			'project_member_added' => $l->t('{actor} added {member} to project {project}'),
			'project_member_removed' => $l->t('{actor} removed {member} from project {project}'),
			default => null,
		};
		if ($rich === null) {
			return;
		}

		$params = $event->getSubjectParameters();
		$actor  = (string)($params['actor'] ?? '');
		$member = (string)($params['member'] ?? '');
		$title  = (string)($params['title'] ?? '');

		$richParams = [
			'actor' => ['type' => 'user', 'id' => $actor, 'name' => $actor],
			'project' => ['type' => 'highlight', 'id' => (string)($params['objectId'] ?? $event->getObjectId()), 'name' => $title],
		];
		if (str_contains($rich, '{member}') === true) {
			$richParams['member'] = ['type' => 'user', 'id' => $member, 'name' => $member];
		}

		$event->setParsedSubject(str_replace(['{actor}', '{member}', '{project}'], [$actor, $member, $title], $rich));
		$event->setRichSubject(subject: $rich, parameters: $richParams);
	}//end applyProjectSubject()
}//end class
