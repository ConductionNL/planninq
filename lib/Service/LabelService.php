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
 */

declare(strict_types=1);

namespace OCA\Planix\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing label objects via OpenRegister.
 */
class LabelService
{

    /**
     * The OpenRegister register slug for Planix.
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
     * Retrieve the OpenRegister ObjectService from the container.
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
            throw new \RuntimeException('OpenRegister is not installed or enabled.');
        }

    }//end getObjectService()

    /**
     * List all labels.
     *
     * @return array<int,array<string,mixed>> The list of label objects.
     */
    public function findAll(): array
    {
        $objectService = $this->getObjectService();
        $result = $objectService->getResultArrayForRequest(
            register: self::REGISTER,
            schema: self::SCHEMA,
            requestParams: []
        );

        return ($result['results'] ?? []);

    }//end findAll()

    /**
     * Create a new label.
     *
     * @param array<string,mixed> $data The label data (must contain 'name').
     *
     * @return array<string,mixed> The created label object.
     */
    public function create(array $data): array
    {
        $objectService = $this->getObjectService();

        $labelData = [
            'title' => $data['name'],
            'color' => ($data['color'] ?? '#4376FC'),
        ];

        if (isset($data['description']) === true) {
            $labelData['description'] = $data['description'];
        }

        return $objectService->saveObject(
            register: self::REGISTER,
            schema: self::SCHEMA,
            object: $labelData
        );

    }//end create()

    /**
     * Delete a label by its ID.
     *
     * @param string $id The label UUID.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(string $id): bool
    {
        $objectService = $this->getObjectService();

        return $objectService->deleteObject(
            register: self::REGISTER,
            schema: self::SCHEMA,
            id: $id
        );

    }//end delete()
}//end class
