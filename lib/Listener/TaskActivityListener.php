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
 * @spec openspec/specs/task-collaboration.md
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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Publishes planix task activity from OpenRegister object events.
 *
 * @implements IEventListener<Event>
 */
class TaskActivityListener implements IEventListener
{
    /**
     * OpenRegister register slug owning the planix schemas.
     *
     * @var string
     */
    private const REGISTER_SLUG = 'planix';

    /**
     * OpenRegister schema slug for tasks.
     *
     * @var string
     */
    private const TASK_SCHEMA_SLUG = 'task';

    /**
     * OpenRegister schema slug for projects (members resolution).
     *
     * @var string
     */
    private const PROJECT_SCHEMA_SLUG = 'project';

    /**
     * The activity app type planix publishes under.
     *
     * @var string
     */
    private const ACTIVITY_TYPE = 'planix_task';

    /**
     * Constructor.
     *
     * @param IActivityManager   $activityManager The NC activity manager.
     * @param IUserSession       $userSession     The current user session.
     * @param ContainerInterface $container        DI container (resolves OR services at runtime).
     * @param LoggerInterface    $logger          The logger.
     */
    public function __construct(
        private IActivityManager $activityManager,
        private IUserSession $userSession,
        private ContainerInterface $container,
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
     * @spec openspec/specs/task-collaboration.md
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
        if ($this->isPlanixTask(object: $object) === false) {
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
                objectId: $objectId,
                actor: $actor,
                affectedUser: $affectedUser
            );
        }
    }//end onChange()

    /**
     * Decide whether an object belongs to the planix register's `task` schema.
     *
     * Resolves the event object's register and schema IDs to their slugs via the
     * OpenRegister mappers. Returns false (silently) when OR is unavailable.
     *
     * @param object $object The object entity.
     *
     * @return bool Whether this is a planix task.
     */
    private function isPlanixTask(object $object): bool
    {
        $registerId = (string) ($object->getRegister() ?? '');
        $schemaId   = (string) ($object->getSchema() ?? '');
        if ($registerId === '' || $schemaId === '') {
            return false;
        }

        $registerSlug = $this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\RegisterMapper', id: $registerId);
        if ($registerSlug !== self::REGISTER_SLUG) {
            return false;
        }

        $schemaSlug = $this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\SchemaMapper', id: $schemaId);
        return $schemaSlug === self::TASK_SCHEMA_SLUG;
    }//end isPlanixTask()

    /**
     * Resolve an OpenRegister entity's slug via a mapper FQCN, by id.
     *
     * @param string $service The mapper FQCN (RegisterMapper or SchemaMapper).
     * @param string $id      The numeric/string id to look up.
     *
     * @return string The slug, or '' when unresolvable / OR unavailable.
     */
    private function resolveSlug(string $service, string $id): string
    {
        try {
            $mapper = $this->container->get($service);
            $entity = $mapper->find($id);
            if (is_object($entity) === true && method_exists($entity, 'getSlug') === true) {
                return (string) $entity->getSlug();
            }
        } catch (\Throwable $e) {
            $this->logger->debug(
                'Planix: could not resolve OpenRegister slug',
                ['service' => $service, 'id' => $id, 'exception' => $e->getMessage()]
            );
        }

        return '';
    }//end resolveSlug()

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
        $members = $this->projectMembers(projectId: $this->stringify(value: ($taskData['project'] ?? '')));

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
     * Fetch the member ids of a project from OpenRegister.
     *
     * @param string $projectId The project UUID.
     *
     * @return string[] The project's member ids (plus owner), or [] when unresolvable.
     */
    private function projectMembers(string $projectId): array
    {
        if ($projectId === '') {
            return [];
        }

        try {
            $objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
            $objectService->setRegister(self::REGISTER_SLUG);
            $objectService->setSchema(self::PROJECT_SCHEMA_SLUG);
            $project = $objectService->find($projectId);

            $data = $this->entityToArray(entity: $project);
            $members = ($data['members'] ?? []);
            if (is_array($members) === false) {
                $members = [];
            }

            $owner = $this->stringify(value: ($data['owner'] ?? ''));
            if ($owner !== '') {
                $members[] = $owner;
            }

            return array_map('strval', $members);
        } catch (\Throwable $e) {
            $this->logger->debug(
                'Planix: could not resolve project members for task activity',
                ['project' => $projectId, 'exception' => $e->getMessage()]
            );
            return [];
        }//end try
    }//end projectMembers()

    /**
     * Normalise an OpenRegister entity or array to a plain data array.
     *
     * @param mixed $entity An OR entity object or a plain array.
     *
     * @return array<string,mixed> The object data.
     */
    private function entityToArray(mixed $entity): array
    {
        if (is_object($entity) === true && method_exists($entity, 'getObject') === true) {
            $data = $entity->getObject();
            if (is_array($data) === true) {
                return $data;
            }
        }

        if (is_array($entity) === true) {
            return $entity;
        }

        return [];
    }//end entityToArray()

    /**
     * Publish a single activity event to one affected user.
     *
     * @param string $subject      The subject key.
     * @param array  $params       The subject parameters.
     * @param string $objectId     The task UUID.
     * @param string $actor        The acting user id (author).
     * @param string $affectedUser The recipient user id.
     *
     * @return void
     */
    private function publish(
        string $subject,
        array $params,
        string $objectId,
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
