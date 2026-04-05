<?php

/**
 * Planix Time Entry Service
 *
 * Service for managing time entry CRUD operations via OpenRegister.
 *
 * @category Service
 * @package  OCA\Planix\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
declare(strict_types=1);

namespace OCA\Planix\Service;

use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing time entry CRUD operations via OpenRegister.
 */
class TimeEntryService
{

    /**
     * The OpenRegister register slug.
     *
     * @var string
     */
    private const REGISTER = 'planix';

    /**
     * The OpenRegister schema slug for time entries.
     *
     * @var string
     */
    private const SCHEMA = 'timeEntry';

    /**
     * The OpenRegister schema slug for tasks.
     *
     * @var string
     */
    private const TASK_SCHEMA = 'task';

    /**
     * The OpenRegister schema slug for projects.
     *
     * @var string
     */
    private const PROJECT_SCHEMA = 'project';

    /**
     * Regex pattern for UUID v4 format validation.
     *
     * @var string
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Constructor for the TimeEntryService.
     *
     * @param ContainerInterface $container   The container
     * @param IUserSession       $userSession The user session
     * @param LoggerInterface    $logger      The logger
     *
     * @return void
     */
    public function __construct(
        private ContainerInterface $container,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Get the current user's UID.
     *
     * @return string|null
     */
    public function getCurrentUserId(): ?string
    {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }//end getCurrentUserId()

    /**
     * Get the OpenRegister ObjectService from the container.
     *
     * @return object The ObjectService instance
     *
     * @throws \RuntimeException When OpenRegister is not available.
     */
    private function getObjectService(): object
    {
        try {
            return $this->container->get('OCA\OpenRegister\Service\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService not available', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('OpenRegister is not available.');
        }

    }//end getObjectService()

    /**
     * Find objects in OpenRegister by register, schema, and filters.
     *
     * @param string              $schema  The schema slug
     * @param array<string,mixed> $filters Query filters
     *
     * @return array<int,array<string,mixed>>
     */
    private function findObjects(string $schema, array $filters=[]): array
    {
        $objectService = $this->getObjectService();
        return $objectService->findObjects(
            register: self::REGISTER,
            schema: $schema,
            filters: $filters,
        );

    }//end findObjects()

    /**
     * Find a single object by ID.
     *
     * @param string $schema The schema slug
     * @param string $id     The object UUID
     *
     * @return array<string,mixed>|null
     */
    private function findObject(string $schema, string $id): ?array
    {
        $objectService = $this->getObjectService();
        $result        = $objectService->findObject(
            register: self::REGISTER,
            schema: $schema,
            id: $id,
        );

        if (is_array($result) === true && empty($result) === false) {
            return $result;
        }

        return null;

    }//end findObject()

    /**
     * Save (create or update) an object in OpenRegister.
     *
     * @param string              $schema The schema slug
     * @param array<string,mixed> $data   The object data
     *
     * @return array<string,mixed>
     */
    private function saveObject(string $schema, array $data): array
    {
        $objectService = $this->getObjectService();
        return $objectService->saveObject(
            register: self::REGISTER,
            schema: $schema,
            object: $data,
        );

    }//end saveObject()

    /**
     * Delete an object from OpenRegister.
     *
     * @param string $schema The schema slug
     * @param string $id     The object UUID
     *
     * @return bool
     */
    private function deleteObject(string $schema, string $id): bool
    {
        $objectService = $this->getObjectService();
        return $objectService->deleteObject(
            register: self::REGISTER,
            schema: $schema,
            id: $id,
        );

    }//end deleteObject()

    /**
     * Assert that the current user is a member of the project that owns the task.
     *
     * Denies access if the task has no associated project, if the project record
     * cannot be found, or if the user is not listed in the project members array.
     * Fails closed by design: when in doubt, deny.
     *
     * @param array<string,mixed> $task   The resolved task object
     * @param string              $userId The UID of the requesting user
     *
     * @return void
     *
     * @throws \RuntimeException When access is denied.
     */
    private function assertProjectMembership(array $task, string $userId): void
    {
        $projectId = ($task['project'] ?? null);
        if ($projectId === null) {
            throw new \RuntimeException('Access denied: task is not associated with any project.');
        }

        $project = $this->findObject(schema: self::PROJECT_SCHEMA, id: $projectId);
        if ($project === null) {
            throw new \RuntimeException('Access denied: project not found.');
        }

        $members = ($project['members'] ?? []);
        if (in_array($userId, $members, true) === false) {
            throw new \RuntimeException('Access denied: you are not a member of this project.');
        }

    }//end assertProjectMembership()

    /**
     * Create a new time entry.
     *
     * @param array<string,mixed> $data Time entry data (taskId, duration, date, description)
     *
     * @return array<string,mixed> The created time entry
     *
     * @throws \InvalidArgumentException When validation fails or the task does not exist.
     * @throws \RuntimeException         When the user is not a member of the task's project.
     */
    public function createTimeEntry(array $data): array
    {
        $this->validateTimeEntryData(data: $data);

        $task = $this->findObject(schema: self::TASK_SCHEMA, id: $data['taskId']);
        if ($task === null) {
            throw new \InvalidArgumentException('Task not found.');
        }

        $userId = $this->getCurrentUserId();
        $this->assertProjectMembership(task: $task, userId: $userId);

        $entry = $this->saveObject(
            schema: self::SCHEMA,
            data: [
                'task'        => $data['taskId'],
                'user'        => $userId,
                'duration'    => (int) $data['duration'],
                'date'        => $data['date'],
                'description' => ($data['description'] ?? ''),
            ]
        );

        return $entry;

    }//end createTimeEntry()

    /**
     * List time entries for a given task.
     *
     * Enforces read access: the requesting user must be a member of the project
     * that owns the task (IDOR guard — SEC-001/SEC-002/F-002).
     *
     * @param string $taskId The task UUID
     *
     * @return array<int,array<string,mixed>>
     *
     * @throws \InvalidArgumentException When the task does not exist.
     * @throws \RuntimeException         When the current user is not a project member.
     */
    public function listTimeEntries(string $taskId): array
    {
        $task = $this->findObject(schema: self::TASK_SCHEMA, id: $taskId);
        if ($task === null) {
            throw new \InvalidArgumentException('Task not found.');
        }

        $userId = $this->getCurrentUserId();
        $this->assertProjectMembership(task: $task, userId: $userId);

        return $this->findObjects(schema: self::SCHEMA, filters: ['task' => $taskId]);

    }//end listTimeEntries()

    /**
     * Delete a time entry. Only the owner may delete.
     *
     * @param string $id The time entry UUID
     *
     * @return bool
     *
     * @throws \InvalidArgumentException When the entry is not found.
     * @throws \RuntimeException         When the current user is not the owner.
     */
    public function deleteTimeEntry(string $id): bool
    {
        $entry = $this->findObject(schema: self::SCHEMA, id: $id);
        if ($entry === null) {
            throw new \InvalidArgumentException('Time entry not found.');
        }

        $userId = $this->getCurrentUserId();
        if (($entry['user'] ?? '') !== $userId) {
            throw new \RuntimeException('Only the owner may delete a time entry.');
        }

        return $this->deleteObject(schema: self::SCHEMA, id: $id);

    }//end deleteTimeEntry()

    /**
     * Validate time entry input data.
     *
     * @param array<string,mixed> $data The input data
     *
     * @return void
     *
     * @throws \InvalidArgumentException When validation fails.
     */
    private function validateTimeEntryData(array $data): void
    {
        if (empty($data['taskId']) === true) {
            throw new \InvalidArgumentException('taskId is required.');
        }

        if (preg_match(self::UUID_PATTERN, $data['taskId']) !== 1) {
            throw new \InvalidArgumentException('taskId must be a valid UUID.');
        }

        if (isset($data['duration']) === false || (int) $data['duration'] <= 0) {
            throw new \InvalidArgumentException('duration must be greater than 0.');
        }

        if (empty($data['date']) === true) {
            throw new \InvalidArgumentException('date is required.');
        }

        if (\DateTime::createFromFormat('Y-m-d', $data['date']) === false
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date']) !== 1
        ) {
            throw new \InvalidArgumentException('date must be a valid ISO 8601 date (YYYY-MM-DD).');
        }

    }//end validateTimeEntryData()
}//end class
