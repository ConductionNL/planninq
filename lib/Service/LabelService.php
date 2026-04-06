<?php

/**
 * Planix Label Service
 *
 * Service for managing label objects via OpenRegister.
 *
 * @category Service
 * @package  OCA\Planix\Service
 * @spec     openspec/changes/label-crud/tasks.md#task-1
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

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for managing label objects via OpenRegister.
 *
 * @spec openspec/changes/label-crud/tasks.md#task-1
 */
class LabelService
{

    /**
     * The OpenRegister register name for Planix.
     *
     * @var string
     */
    private const REGISTER = 'planix';

    /**
     * The OpenRegister schema slug for labels.
     *
     * @var string
     */
    private const SCHEMA = 'label';

    /**
     * Constructor for the LabelService.
     *
     * @param ContainerInterface $container The DI container
     * @param LoggerInterface    $logger    The logger
     *
     * @return void
     */
    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Retrieve the OpenRegister ObjectService from the DI container.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return object The ObjectService instance
     *
     * @throws RuntimeException When OpenRegister is not available.
     */
    private function getObjectService(): object
    {
        try {
            return $this->container->get('OCA\OpenRegister\Service\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService not available', ['exception' => $e->getMessage()]);
            throw new RuntimeException('OpenRegister is not installed or enabled.');
        }

    }//end getObjectService()

    /**
     * List all labels.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return array<string,mixed> Paginated result from OpenRegister
     */
    public function findAll(): array
    {
        $objectService = $this->getObjectService();

        return $objectService->getResultArrayForRequest(
            self::REGISTER,
            self::SCHEMA,
            [],
        );

    }//end findAll()

    /**
     * Create a new label.
     *
     * @param array<string,mixed> $data Label data (name, color, description)
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return array<string,mixed> The created label object
     *
     * @throws InvalidArgumentException When name is missing.
     */
    public function create(array $data): array
    {
        if (empty($data['name']) === true && empty($data['title']) === true) {
            throw new InvalidArgumentException('Label name is required.');
        }

        // Normalise: accept both "name" and "title" — OpenRegister schema uses "title".
        if (isset($data['name']) === true && isset($data['title']) === false) {
            $data['title'] = $data['name'];
            unset($data['name']);
        }

        $objectService = $this->getObjectService();

        return $objectService->saveObject(
            self::REGISTER,
            self::SCHEMA,
            $data,
        );

    }//end create()

    /**
     * Delete a label by its UUID.
     *
     * @param string $id The UUID of the label to delete
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return bool True on success
     *
     * @throws RuntimeException When deletion fails.
     */
    public function delete(string $id): bool
    {
        $objectService = $this->getObjectService();

        return $objectService->deleteObject(
            self::REGISTER,
            self::SCHEMA,
            $id,
        );

    }//end delete()
}//end class
