<?php

/**
 * Planix Label Service
 *
 * Service for managing label objects via OpenRegister.
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
 *
 * @spec openspec/changes/label-crud/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Planix\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing label objects via OpenRegister.
 *
 * @spec openspec/changes/label-crud/tasks.md#task-1
 */
class LabelService
{

    /**
     * The OpenRegister register slug for Planix.
     *
     * @var string
     */
    private const REGISTER_SLUG = 'planix';

    /**
     * The OpenRegister schema slug for labels.
     *
     * @var string
     */
    private const SCHEMA_SLUG = 'label';

    /**
     * Constructor for the LabelService.
     *
     * @param ContainerInterface $container The service container
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
     * Get the OpenRegister ObjectService from the container.
     *
     * @return object The ObjectService instance
     *
     * @throws \RuntimeException When OpenRegister is not available.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    private function getObjectService(): object
    {
        try {
            return $this->container->get('OCA\OpenRegister\Service\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->error('Planix: OpenRegister ObjectService not available', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('OpenRegister is not installed or enabled.');
        }

    }//end getObjectService()

    /**
     * List all labels.
     *
     * @return array<int,array<string,mixed>> Array of label objects
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function findAll(): array
    {
        $objectService = $this->getObjectService();
        $objectService->setRegister(self::REGISTER_SLUG);
        $objectService->setSchema(self::SCHEMA_SLUG);

        return $objectService->findAll([]);

    }//end findAll()

    /**
     * Create a new label.
     *
     * @param array<string,mixed> $data The label data (title and color are required)
     *
     * @return array<string,mixed> The created label object
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function create(array $data): array
    {
        $objectService = $this->getObjectService();
        $objectService->setRegister(self::REGISTER_SLUG);
        $objectService->setSchema(self::SCHEMA_SLUG);

        $object = $objectService->createFromArray(
            $data,
            [],
        );

        return $object->jsonSerialize();

    }//end create()

    /**
     * Delete a label by UUID.
     *
     * @param string $id The label UUID
     *
     * @return bool True if deletion was successful
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     */
    public function delete(string $id): bool
    {
        $objectService = $this->getObjectService();
        $objectService->setRegister(self::REGISTER_SLUG);
        $objectService->setSchema(self::SCHEMA_SLUG);

        return $objectService->deleteObject($id);

    }//end delete()
}//end class
