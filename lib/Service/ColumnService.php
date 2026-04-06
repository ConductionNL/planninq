<?php

/**
 * Planix Column Service
 *
 * Service for managing kanban board columns via OpenRegister.
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

declare(strict_types=1);

namespace OCA\Planix\Service;

use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing kanban board columns.
 *
 * Wraps OpenRegister CRUD for the 'column' schema and adds
 * project-membership authorization checks.
 */
class ColumnService
{
    /**
     * Constructor for the ColumnService.
     *
     * @param ContainerInterface $container   The DI container
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
     * Get the OpenRegister object service.
     *
     * @return object The ObjectService instance
     *
     * @throws \RuntimeException When OpenRegister is not available
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
     * Get the current user UID.
     *
     * @return string
     */
    private function getCurrentUid(): string
    {
        $user = $this->userSession->getUser();
        if ($user !== null) {
            return $user->getUID();
        }

        return '';

    }//end getCurrentUid()

    /**
     * Check whether the current user is a member of the given project.
     *
     * @param string $projectId The project UUID
     *
     * @return bool
     */
    public function isProjectMember(string $projectId): bool
    {
        try {
            $objectService = $this->getObjectService();
            $project       = $objectService->findObject(register: 'planix', schema: 'project', id: $projectId);

            if ($project === null) {
                return false;
            }

            $members = $project['members'] ?? [];
            $uid     = $this->getCurrentUid();

            return in_array($uid, $members, true);
        } catch (\Throwable $e) {
            $this->logger->warning('Planix: membership check failed', ['exception' => $e->getMessage()]);
            return false;
        }

    }//end isProjectMember()

    /**
     * List columns for a project, ordered by position.
     *
     * @param string $projectId The project UUID
     *
     * @return array List of column objects
     */
    public function listColumns(string $projectId): array
    {
        $objectService = $this->getObjectService();
        $columns       = $objectService->findObjects(
            register: 'planix',
            schema: 'column',
            filters: ['project' => $projectId],
        );

        usort(
                $columns,
                static function (array $a, array $b): int {
                    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                }
                );

        return $columns;

    }//end listColumns()

    /**
     * Find a single column by ID.
     *
     * @param string $id The column UUID
     *
     * @return array|null Column data or null if not found
     */
    public function findColumn(string $id): ?array
    {
        $objectService = $this->getObjectService();
        $column        = $objectService->findObject(register: 'planix', schema: 'column', id: $id);

        if ($column === false || $column === null) {
            return null;
        }

        return $column;

    }//end findColumn()

    /**
     * Create a new column.
     *
     * @param array $data Column fields (title, projectId, position)
     *
     * @return array The created column
     */
    public function createColumn(array $data): array
    {
        $objectService = $this->getObjectService();

        return $objectService->saveObject(
            register: 'planix',
            schema: 'column',
            object: $data,
        );

    }//end createColumn()

    /**
     * Update an existing column.
     *
     * @param string $id   The column UUID
     * @param array  $data Updated fields
     *
     * @return array The updated column
     */
    public function updateColumn(string $id, array $data): array
    {
        $objectService = $this->getObjectService();

        return $objectService->saveObject(
            register: 'planix',
            schema: 'column',
            object: array_merge($data, ['id' => $id]),
        );

    }//end updateColumn()

    /**
     * Delete a column and move its tasks to the backlog (column = null).
     *
     * @param string $id The column UUID
     *
     * @return bool True on success
     */
    public function deleteColumn(string $id): bool
    {
        $objectService = $this->getObjectService();

        // Move tasks assigned to this column to backlog (column = null).
        $tasks = $objectService->findObjects(
            register: 'planix',
            schema: 'task',
            filters: ['column' => $id],
        );

        foreach ($tasks as $task) {
            $objectService->saveObject(
                register: 'planix',
                schema: 'task',
                object: [
                    'id'          => $task['id'],
                    'column'      => null,
                    'columnOrder' => 0,
                ],
            );
        }

        return $objectService->deleteObject(register: 'planix', schema: 'column', id: $id);

    }//end deleteColumn()
}//end class
