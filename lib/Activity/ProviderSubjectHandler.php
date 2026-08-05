<?php

/**
 * Planix Activity ProviderSubjectHandler.
 *
 * Applies the human-readable subject text and rich parameters to a planix task
 * activity event, keyed by the event subject. Pulled out of {@see Provider} so
 * the subject rendering can be unit-tested in isolation.
 *
 * @category Activity
 * @package  OCA\Planix\Activity
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

namespace OCA\Planix\Activity;

use OCP\Activity\IEvent;

/**
 * Applies subject text and rich parameters to planix task activity events.
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class ProviderSubjectHandler
{
    /**
     * Apply the parsed + rich subject for the event's subject key.
     *
     * The actor and task title are always rendered as rich parameters; the
     * status / assignee / due-date variants append the changed value. Unknown
     * subjects are left untouched (the {@see Provider} only forwards the five
     * handled keys).
     *
     * @param IEvent $event  The event to mutate.
     * @param object $l      The l10n translator (IL10N).
     * @param array  $params The subject parameters from the listener.
     *
     * @return void
     *
     * @spec openspec/specs/task-collaboration/spec.md
     */
    public function applySubjectText(IEvent $event, object $l, array $params): void
    {
        $actor    = (string) ($params['actor'] ?? '');
        $title    = (string) ($params['title'] ?? '');
        $objectId = (string) ($params['objectId'] ?? $event->getObjectId());
        $rich     = $this->buildRichParams(actor: $actor, title: $title, objectId: $objectId);

        switch ($event->getSubject()) {
            case 'task_created':
                $event->setParsedSubject($l->t('%1$s created task %2$s', [$actor, $title]));
                $event->setRichSubject(subject: $l->t('{actor} created task {task}'), parameters: $rich);
                break;

            case 'task_status_changed':
                $status = (string) ($params['status'] ?? '');
                $event->setParsedSubject($l->t('%1$s changed the status of %2$s to %3$s', [$actor, $title, $status]));
                $event->setRichSubject(
                    subject: $l->t('{actor} changed the status of {task} to %1$s', [$status]),
                    parameters: $rich
                );
                break;

            case 'task_assigned_activity':
                $assignee = (string) ($params['assignee'] ?? '');
                $event->setParsedSubject($l->t('%1$s assigned %2$s to %3$s', [$actor, $title, $assignee]));
                $event->setRichSubject(
                    subject: $l->t('{actor} assigned {task} to %1$s', [$assignee]),
                    parameters: $rich
                );
                break;

            case 'task_due_date_changed':
                $dueDate = (string) ($params['dueDate'] ?? '');
                $event->setParsedSubject($l->t('%1$s changed the due date of %2$s to %3$s', [$actor, $title, $dueDate]));
                $event->setRichSubject(
                    subject: $l->t('{actor} changed the due date of {task} to %1$s', [$dueDate]),
                    parameters: $rich
                );
                break;

            case 'task_deleted':
                $event->setParsedSubject($l->t('%1$s deleted task %2$s', [$actor, $title]));
                $event->setRichSubject(subject: $l->t('{actor} deleted task {task}'), parameters: $rich);
                break;

            default:
                // Unhandled subject — leave the event as-is. The Provider only
                // forwards the five handled keys, so this is unreachable in
                // practice and exists only to keep the switch total.
                break;
        }//end switch
    }//end applySubjectText()

    /**
     * Build the rich-subject parameter map shared by every subject.
     *
     * @param string $actor    The acting user's display id.
     * @param string $title    The task title.
     * @param string $objectId The task UUID (used as the rich object id).
     *
     * @return array<string, array<string, string>> The rich parameter map.
     */
    private function buildRichParams(string $actor, string $title, string $objectId): array
    {
        return [
            'actor' => [
                'type' => 'user',
                'id'   => $actor,
                'name' => $actor,
            ],
            'task'  => [
                'type' => 'highlight',
                'id'   => $objectId,
                'name' => $title,
            ],
        ];
    }//end buildRichParams()
}//end class
