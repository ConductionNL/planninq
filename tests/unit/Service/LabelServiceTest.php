<?php

/**
 * Unit tests for LabelService.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Service
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

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\Service\LabelService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for LabelService.
 */
class LabelServiceTest extends TestCase
{

    /**
     * The service under test.
     *
     * @var LabelService
     */
    private LabelService $service;

    /**
     * Mock ContainerInterface.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * Mock LoggerInterface.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Mock ObjectService (simulates OpenRegister).
     *
     * @var MockObject
     */
    private MockObject $objectService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container     = $this->createMock(originalClassName: ContainerInterface::class);
        $this->logger        = $this->createMock(originalClassName: LoggerInterface::class);
        $this->objectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(
                [
                    'setRegister',
                    'setSchema',
                    'findAll',
                    'createFromArray',
                    'deleteObject',
                ]
            )
            ->getMock();

        $this->container->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($this->objectService);

        $this->service = new LabelService(
            container: $this->container,
            logger: $this->logger,
        );

    }//end setUp()

    /**
     * Test that findAll() returns labels from OpenRegister.
     *
     * @return void
     */
    public function testFindAllReturnsLabels(): void
    {
        $labels = [
            [
                'id'    => 'uuid-1',
                'title' => 'Bug',
                'color' => '#FF0000',
            ],
        ];

        $this->objectService->expects($this->once())
            ->method('setRegister')
            ->with('planix');

        $this->objectService->expects($this->once())
            ->method('setSchema')
            ->with('label');

        $this->objectService->expects($this->once())
            ->method('findAll')
            ->with(
                [
                    'filters' => [
                        'register' => 'planix',
                        'schema'   => 'label',
                    ],
                ]
            )
            ->willReturn($labels);

        $result = $this->service->findAll();

        self::assertSame(expected: $labels, actual: $result);

    }//end testFindAllReturnsLabels()

    /**
     * Test that create() calls createFromArray and returns the serialised object.
     *
     * @return void
     */
    public function testCreateCallsObjectService(): void
    {
        $data = [
            'title' => 'Bug',
            'color' => '#FF0000',
        ];

        $mockEntity = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['jsonSerialize'])
            ->getMock();

        $mockEntity->method('jsonSerialize')
            ->willReturn(
                [
                    'id'    => 'uuid-new',
                    'title' => 'Bug',
                    'color' => '#FF0000',
                ]
            );

        $this->objectService->expects($this->once())
            ->method('createFromArray')
            ->with(
                $data,
                [],
                'planix',
                'label',
            )
            ->willReturn($mockEntity);

        $result = $this->service->create(data: $data);

        self::assertSame(expected: 'uuid-new', actual: $result['id']);
        self::assertSame(expected: 'Bug', actual: $result['title']);

    }//end testCreateCallsObjectService()

    /**
     * Test that delete() calls deleteObject with the correct UUID.
     *
     * @return void
     */
    public function testDeleteCallsObjectService(): void
    {
        $this->objectService->expects($this->once())
            ->method('deleteObject')
            ->with('uuid-to-delete')
            ->willReturn(true);

        $result = $this->service->delete(id: 'uuid-to-delete');

        self::assertTrue(condition: $result);

    }//end testDeleteCallsObjectService()

    /**
     * Test that findAll() throws RuntimeException when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testFindAllThrowsWhenOpenRegisterUnavailable(): void
    {
        $container = $this->createMock(originalClassName: ContainerInterface::class);
        $container->method('get')
            ->willThrowException(new \Exception('Service not found'));

        $service = new LabelService(
            container: $container,
            logger: $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenRegister is not installed or enabled.');

        $service->findAll();

    }//end testFindAllThrowsWhenOpenRegisterUnavailable()
}//end class
