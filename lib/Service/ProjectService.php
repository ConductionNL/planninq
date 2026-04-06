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
     * Constructor for the ProjectService.
     *
     * @param ContainerInterface $container   The service container
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
     * Fetch all projects the current user is a member of.
     *
     * @return array<int,array<string,mixed>> List of projects
     */
    public function findAll(): array
    {
        $objectService = $this->getObjectService();
        $uid           = $this->getCurrentUserId();

        $objects = $objectService->findAll(
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA
        );

        // Client-side member filter (MariaDB lacks jsonb operators).
        if ($uid !== null) {
            $objects = array_values(
                array_filter(
                    $objects,
                    static function (array $project) use ($uid): bool {
                        $members = ($project['members'] ?? []);
                        return is_array($members) && in_array(needle: $uid, haystack: $members, strict: true);
                    }
                )
            );
        }

        return $objects;

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
     * Create a new project.
     *
     * @param array<string,mixed> $data The project data (title required)
     *
     * @return array<string,mixed> The created project
     */
    public function create(array $data): array
    {
        $objectService = $this->getObjectService();
        $uid           = $this->getCurrentUserId();

        $members = [];
        if ($uid !== null) {
            $members = [$uid];
        }

        $projectData = [
            'title'       => $data['title'],
            'description' => ($data['description'] ?? ''),
            'color'       => ($data['color'] ?? ''),
            'status'      => ($data['status'] ?? 'active'),
            'members'     => $members,
        ];

        return $objectService->save(
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA,
            data: $projectData
        );

    }//end create()

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
     * Delete a project by ID.
     *
     * @param string $id The project UUID
     *
     * @return bool Whether the deletion was successful
     */
    public function delete(string $id): bool
    {
        $objectService = $this->getObjectService();

        return $objectService->delete(
            id: $id,
            register: self::REGISTER_SLUG,
            schema: self::PROJECT_SCHEMA
        );

    }//end delete()
}//end class
