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
     * Mock ObjectService (from OpenRegister).
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
        $this->objectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getResultArrayForRequest', 'saveObject', 'deleteObject'])
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
     * Test that findAll() returns the results from OpenRegister.
     *
     * @return void
     */
    public function testFindAllReturnsLabels(): void
    {
        $labels = [
            ['id' => 'uuid-1', 'title' => 'Bug', 'color' => '#E74C3C'],
            ['id' => 'uuid-2', 'title' => 'Feature', 'color' => '#4376FC'],
        ];

        $this->objectService->expects($this->once())
            ->method('getResultArrayForRequest')
            ->willReturn(['results' => $labels]);

        $result = $this->service->findAll();

        self::assertSame(expected: $labels, actual: $result);

    }//end testFindAllReturnsLabels()

    /**
     * Test that create() passes correct data to OpenRegister and returns the result.
     *
     * @return void
     */
    public function testCreatePassesCorrectData(): void
    {
        $created = ['id' => 'uuid-new', 'title' => 'Urgent', 'color' => '#FF0000'];

        $this->objectService->expects($this->once())
            ->method('saveObject')
            ->willReturn($created);

        $result = $this->service->create(['name' => 'Urgent', 'color' => '#FF0000']);

        self::assertSame(expected: $created, actual: $result);

    }//end testCreatePassesCorrectData()

    /**
     * Test that create() uses the default color when none is provided.
     *
     * @return void
     */
    public function testCreateUsesDefaultColor(): void
    {
        $created = ['id' => 'uuid-new', 'title' => 'My Label', 'color' => '#4376FC'];

        $this->objectService->expects($this->once())
            ->method('saveObject')
            ->willReturn($created);

        $result = $this->service->create(['name' => 'My Label']);

        self::assertSame(expected: '#4376FC', actual: $result['color']);

    }//end testCreateUsesDefaultColor()

    /**
     * Test that delete() calls deleteObject and returns the result.
     *
     * @return void
     */
    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $this->objectService->expects($this->once())
            ->method('deleteObject')
            ->willReturn(true);

        $result = $this->service->delete('uuid-1');

        self::assertTrue(condition: $result);

    }//end testDeleteReturnsTrueOnSuccess()

    /**
     * Test that a RuntimeException is thrown when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testThrowsWhenOpenRegisterUnavailable(): void
    {
        $container = $this->createMock(originalClassName: ContainerInterface::class);
        $container->method('get')
            ->willThrowException(new \Exception('Service not found'));

        $service = new LabelService(
            container: $container,
            logger: $this->logger,
        );

        $this->expectException(exception: \RuntimeException::class);
        $service->findAll();

    }//end testThrowsWhenOpenRegisterUnavailable()
}//end class
