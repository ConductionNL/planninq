<?php

/**
 * Planix Project Service
 *
 * Service for managing Planix project CRUD operations via OpenRegister.
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

use OCA\Planix\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing Planix project CRUD operations via OpenRegister.
 */
class ProjectService
{

    /**
     * The OpenRegister register slug for Planix.
     *
     * @var string
     */
    private const REGISTER_SLUG = 'planix';

    /**
     * The OpenRegister schema slug for projects.
     *
     * @var string
     */
    private const PROJECT_SCHEMA = 'project';

    /**
     * The OpenRegister schema slug for kanban columns.
     *
     * @var string
     */
    private const COLUMN_SCHEMA = 'column';

    /**
     * Default columns used when the admin setting is absent.
     *
     * @var string[]
     */
    private const DEFAULT_COLUMNS = ['To Do', 'In Progress', 'Review', 'Done'];

    /**
     * Constructor for the ProjectService.
     *
     * @param ContainerInterface $container   The service container
     * @param IUserSession       $userSession The user session
     * @param LoggerInterface    $logger      The logger
     * @param IAppConfig         $appConfig   The app config
     *
     * @return void
     */
    public function __construct(
        private ContainerInterface $container,
        private IUserSession $userSession,
        private LoggerInterface $logger,
        private IAppConfig $appConfig,
    ) {
    }//end __construct()

    /**
     * Get the current user's UID.
     *
     * @return string|null The current user's UID or null if not logged in
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
     * @throws \RuntimeException When OpenRegister is not available
     */
    private function getObjectService(): object
    {
        try {
            return $this->container->get('OCA\OpenRegister\Service\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Planix: OpenRegister ObjectService not available',
                ['exception' => $e->getMessage()]
            );
            throw new \RuntimeException('OpenRegister is not available.');
        }

    }//end getObjectService()

    /**
     * Fetch all active projects the current user is a member of.
     *
     * Returns an empty list when there is no authenticated user.
     *
     * Note: OpenRegister's MariaDB backend lacks JSON-column operators, so we
     * fetch all projects and apply the membership + status filters in PHP.
     * This is an O(N) scan on total project count. A server-side filter should
     * be added once OpenRegister exposes a `filters` parameter (backlog item).
     *
     * @return array<int,array<string,mixed>> List of active projects the user is a member of
     */
    public function findAll(): array
    {
        $uid = $this->getCurrentUserId();
        if ($uid === null) {
            return [];
        }

        $objectService = $this->getObjectService();

        $objects = $objectService->findAll(
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA
        );

        // Client-side member + status filter (MariaDB lacks jsonb operators).
        return array_values(
            array_filter(
                $objects,
                static function (array $project) use ($uid): bool {
                    $members = ($project['members'] ?? []);
                    $status  = ($project['status'] ?? 'active');

                    // Only show active projects (archived/completed are excluded from default list).
                    if ($status !== 'active') {
                        return false;
                    }

                    return is_array($members) && in_array(needle: $uid, haystack: $members, strict: true);
                }
            )
        );

    }//end findAll()

    /**
     * Fetch a single project by ID.
     *
     * @param string $id The project UUID
     *
     * @return array<string,mixed>|null The project or null if not found
     */
    public function find(string $id): ?array
    {
        $objectService = $this->getObjectService();

        $object = $objectService->find(
            id: $id,
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA
        );

        if ($object === null || (is_array($object) === true && empty($object) === true)) {
            return null;
        }

        return $object;

    }//end find()

    /**
     * Check whether the given user is a member of the project.
     *
     * @param array<string,mixed> $project The project data
     * @param string              $uid     The user UID to check
     *
     * @return bool
     */
    public function isMember(array $project, string $uid): bool
    {
        $members = ($project['members'] ?? []);
        return is_array($members) && in_array(needle: $uid, haystack: $members, strict: true);

    }//end isMember()

    /**
     * Check whether the given user is the owner (first member) of the project.
     *
     * @param array<string,mixed> $project The project data
     * @param string              $uid     The user UID to check
     *
     * @return bool
     */
    public function isOwner(array $project, string $uid): bool
    {
        $members = ($project['members'] ?? []);
        return is_array($members) && isset($members[0]) && $members[0] === $uid;

    }//end isOwner()

    /**
     * Create a new project and provision default kanban columns.
     *
     * Throws a RuntimeException when there is no authenticated user to prevent
     * creation of orphaned projects with no owner.
     *
     * @param array<string,mixed> $data The project data (title required)
     *
     * @return array<string,mixed> The created project
     *
     * @throws \RuntimeException When no authenticated user is present
     */
    public function create(array $data): array
    {
        $uid = $this->getCurrentUserId();
        if ($uid === null) {
            throw new \RuntimeException('Cannot create project: no authenticated user.');
        }

        $objectService = $this->getObjectService();

        $projectData = [
            'title'       => $data['title'],
            'description' => ($data['description'] ?? ''),
            'color'       => ($data['color'] ?? ''),
            'status'      => ($data['status'] ?? 'active'),
            'members'     => [$uid],
        ];

        $project = $objectService->save(
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA,
            data: $projectData
        );

        $this->createDefaultColumns(project: $project, objectService: $objectService);

        return $project;

    }//end create()

    /**
     * Create the default kanban columns for a newly created project.
     *
     * Column titles are read from the admin setting `default_columns` (JSON array).
     * The last column is assigned type `done`; all others receive type `active`.
     *
     * @param array<string,mixed> $project       The saved project array (must contain 'id')
     * @param object              $objectService The OpenRegister ObjectService
     *
     * @return void
     */
    private function createDefaultColumns(array $project, object $objectService): void
    {
        $projectId = ($project['id'] ?? null);
        if ($projectId === null) {
            $this->logger->warning('Planix: could not create default columns — project has no id');
            return;
        }

        $rawSetting = $this->appConfig->getValueString(
            Application::APP_ID,
            'default_columns',
            json_encode(self::DEFAULT_COLUMNS)
        );

        $columnTitles = json_decode($rawSetting, true);
        if (is_array($columnTitles) === false || empty($columnTitles) === true) {
            $columnTitles = self::DEFAULT_COLUMNS;
        }

        $lastIndex = (count($columnTitles) - 1);

        foreach ($columnTitles as $index => $title) {
            $columnType = 'active';
            if ($index === $lastIndex) {
                $columnType = 'done';
            }

            try {
                $objectService->save(
                    register: self::REGISTER_SLUG,
                    schema: self::COLUMN_SCHEMA,
                    data: [
                        'title'   => $title,
                        'project' => $projectId,
                        'order'   => $index,
                        'type'    => $columnType,
                    ]
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Planix: failed to create default column "'.$title.'"',
                    ['exception' => $e->getMessage()]
                );
            }
        }//end foreach

    }//end createDefaultColumns()

    /**
     * Update an existing project.
     *
     * @param string              $id   The project UUID
     * @param array<string,mixed> $data The fields to update
     *
     * @return array<string,mixed> The updated project
     */
    public function update(string $id, array $data): array
    {
        $objectService = $this->getObjectService();

        return $objectService->save(
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA,
            data: array_merge($data, ['id' => $id])
        );

    }//end update()

    /**
     * Delete a project and all its associated kanban columns.
     *
     * Columns are fetched and deleted before the project record to prevent
     * orphaned column objects accumulating in the OpenRegister store.
     *
     * @param string $id The project UUID
     *
     * @return bool Whether the project deletion was successful
     */
    public function delete(string $id): bool
    {
        $objectService = $this->getObjectService();

        // Cascade-delete all columns that reference this project.
        try {
            $columns = $objectService->findAll(
                register: self::REGISTER_SLUG,
                schema: self::COLUMN_SCHEMA
            );

            foreach ($columns as $column) {
                if (isset($column['project']) === true && (string) $column['project'] === $id) {
                    try {
                        $objectService->delete(
                            id: (string) $column['id'],
                            register: self::REGISTER_SLUG,
                            schema: self::COLUMN_SCHEMA
                        );
                    } catch (\Throwable $e) {
                        // Best-effort deletion — log but continue so the project itself is still deleted.
                        $this->logger->error(
                            'Planix: failed to delete column "'.(string) ($column['id'] ?? '').'" during project cascade delete',
                            ['exception' => $e->getMessage()]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Planix: failed to fetch columns for cascade delete of project "'.$id.'"',
                ['exception' => $e->getMessage()]
            );
        }//end try

        return $objectService->delete(
            id: $id,
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA
        );

    }//end delete()
}//end class
