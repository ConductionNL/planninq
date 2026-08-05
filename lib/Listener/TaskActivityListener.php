<?php

/**
 * Planix TaskActivityListener.
 *
 * Listens for OpenRegister object events and publishes planix task lifecycle
 * events (created / status changed / assigned / due date changed / deleted)
 * into the Nextcloud Activity stream for the task's project members.
 *
 * This is an activity-stream record — NOT a notification (ADR-031 / gate-18).
 * It never calls the notification engine; it only renders history into the
 * Activity app via {@see \OCP\Activity\IManager}. The listener is scoped to the
 * planix register's `task` schema and is defensively wrapped so a malformed
 * event or unavailable dependency can never break OpenRegister's event
 * dispatch.
 *
 * @category Listener
 * @package  OCA\Planix\Listener
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

namespace OCA\Planix\Listener;

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Publishes planix task activity from OpenRegister object events.
 *
 * @implements IEventListener<Event>
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class TaskActivityListener implements IEventListener
{
    /**
     * The activity app type planix publishes under.
     *
     * @var string
     */
    private const ACTIVITY_TYPE = 'planix_task';

    /**
     * Constructor.
     *
     * @param IActivityManager  $activityManager The NC activity manager.
     * @param IUserSession      $userSession     The current user session.
     * @param TaskScopeResolver $scopeResolver   Resolves task scope + project members from OR.
     * @param LoggerInterface   $logger          The logger.
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
    public function handle(Event $event): void
    {
        try {
            if ($event instanceof ObjectCreatedEvent === true) {
                $this->onChange(object: $event->getObject(), old: null, deleted: false);
                return;
            }

            if ($event instanceof ObjectUpdatedEvent === true) {
                $old = null;
                if ($event->getOldObject() !== null) {
                    $old = $event->getOldObject()->getObject();
                }

                $this->onChange(object: $event->getNewObject(), old: $old, deleted: false);
                return;
            }

            if ($event instanceof ObjectDeletedEvent === true) {
                $this->onChange(object: $event->getObject(), old: null, deleted: true);
            }
        } catch (\Throwable $e) {
            // OR's dispatch must never break because planix activity failed.
            $this->logger->warning(
                'Planix: task activity listener skipped an event',
                ['exception' => $e->getMessage()]
            );
        }//end try
    }//end handle()

    /**
     * Process one object change: scope to the planix task schema, pick the
     * subject, resolve the audience and publish.
     *
     * @param object     $object  The (new) object entity.
     * @param array|null $old     The previous object data, or null on create/delete.
     * @param bool       $deleted Whether the change is a deletion.
     *
     * @return void
     */
    private function onChange(object $object, ?array $old, bool $deleted): void
    {
        $registerId = (string) ($object->getRegister() ?? '');
        $schemaId   = (string) ($object->getSchema() ?? '');
        if ($this->scopeResolver->isPlanixTask(registerId: $registerId, schemaId: $schemaId) === false) {
            return;
        }

        $data = $object->getObject();
        if (is_array($data) === false) {
            return;
        }

        $subjectParams = $this->selectSubject(data: $data, old: $old, deleted: $deleted);
        if ($subjectParams === null) {
            // No activity-worthy change (e.g. an update that touched none of the
            // tracked fields).
            return;
        }

        [$subject, $params] = $subjectParams;

        $objectId = (string) ($object->getUuid() ?? '');
        $actor    = $this->currentActor();
        $audience = $this->resolveAudience(taskData: $data, actor: $actor);

        $params['actor']    = $actor;
        $params['title']    = $this->stringify(value: ($data['title'] ?? ''));
        $params['objectId'] = $objectId;

        foreach ($audience as $affectedUser) {
            $this->publish(
                subject: $subject,
                params: $params,
                actor: $actor,
                affectedUser: $affectedUser
            );
        }
    }//end onChange()

    /**
     * Pick the activity subject + params for a change by diffing tracked fields.
     *
     * Precedence on update: status > assignee > due date (one entry per event,
     * matching the most significant change).
     *
     * @param array      $data    The new task data.
     * @param array|null $old     The previous task data, or null on create/delete.
     * @param bool       $deleted Whether the change is a deletion.
     *
     * @return array{0:string,1:array<string,string>}|null The subject + params, or null when nothing is activity-worthy.
     */
    private function selectSubject(array $data, ?array $old, bool $deleted): ?array
    {
        if ($deleted === true) {
            return ['task_deleted', []];
        }

        if ($old === null) {
            return ['task_created', []];
        }

        $newStatus = $this->stringify(value: ($data['status'] ?? ''));
        $oldStatus = $this->stringify(value: ($old['status'] ?? ''));
        if ($newStatus !== $oldStatus) {
            return ['task_status_changed', ['status' => $newStatus]];
        }

        $newAssignee = $this->stringify(value: ($data['assignedTo'] ?? ''));
        $oldAssignee = $this->stringify(value: ($old['assignedTo'] ?? ''));
        if ($newAssignee !== $oldAssignee) {
            return ['task_assigned_activity', ['assignee' => $newAssignee]];
        }

        $newDue = $this->stringify(value: ($data['dueDate'] ?? ''));
        $oldDue = $this->stringify(value: ($old['dueDate'] ?? ''));
        if ($newDue !== $oldDue) {
            return ['task_due_date_changed', ['dueDate' => $newDue]];
        }

        return null;
    }//end selectSubject()

    /**
     * Resolve the audience for a task event: the members of the task's project,
     * minus the acting user (NC convention — no activity for your own change).
     *
     * Falls back to the task's assignee when the project cannot be resolved, so
     * a member still gets the entry; returns an empty list (silently) when there
     * is no resolvable audience.
     *
     * @param array  $taskData The task data (carries `project` UUID + `assignedTo`).
     * @param string $actor    The acting user id (excluded from the result).
     *
     * @return string[] Distinct affected user ids.
     */
    private function resolveAudience(array $taskData, string $actor): array
    {
        $members = $this->scopeResolver->projectMembers(
            projectId: $this->stringify(value: ($taskData['project'] ?? ''))
        );

        $assignee = $this->stringify(value: ($taskData['assignedTo'] ?? ''));
        if ($assignee !== '') {
            $members[] = $assignee;
        }

        $distinct = [];
        foreach ($members as $member) {
            $member = (string) $member;
            if ($member === '' || $member === $actor || isset($distinct[$member]) === true) {
                continue;
            }

            $distinct[$member] = true;
        }

        return array_keys($distinct);
    }//end resolveAudience()

    /**
     * Publish a single activity event to one affected user.
     *
     * @param string $subject      The subject key.
     * @param array  $params       The subject parameters (carries the task UUID under `objectId`).
     * @param string $actor        The acting user id (author).
     * @param string $affectedUser The recipient user id.
     *
     * @return void
     */
    private function publish(
        string $subject,
        array $params,
        string $actor,
        string $affectedUser
    ): void {
        try {
            $event = $this->activityManager->generateEvent();
            $event->setApp('planix')
                ->setType(self::ACTIVITY_TYPE)
                ->setAuthor($actor)
                ->setTimestamp(time())
                ->setSubject(subject: $subject, parameters: $params)
                ->setObject(objectType: self::ACTIVITY_TYPE, objectId: 0, objectName: ($params['title'] ?? ''))
                ->setAffectedUser($affectedUser);

            $this->activityManager->publish($event);
        } catch (\Throwable $e) {
            $this->logger->warning('Planix: failed to publish task activity', ['exception' => $e->getMessage()]);
        }
    }//end publish()

    /**
     * Resolve the acting user's id, or '' when there is no session user.
     *
     * @return string The current user id.
     */
    private function currentActor(): string
    {
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
    private function stringify(mixed $value): string
    {
        if (is_scalar($value) === true) {
            return (string) $value;
        }

        if (is_array($value) === true) {
            foreach (['en', 'en_GB', 'en_US', 'nl', 'nl_NL'] as $lang) {
                if (isset($value[$lang]) === true && is_scalar($value[$lang]) === true) {
                    return (string) $value[$lang];
                }
            }
        }

        return '';
    }//end stringify()
}//end class
