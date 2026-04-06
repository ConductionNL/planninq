<?php

/**
 * Unit tests for LabelService.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Service
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

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\Service\LabelService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for LabelService.
 *
 * @spec openspec/changes/label-crud/tasks.md#task-1
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
     * Mock ObjectService (dynamic mock).
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
     * Test that findAll() returns labels from OpenRegister.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return void
     */
    public function testFindAllReturnsLabels(): void
    {
        $expected = [
            'results' => [
                ['id' => 'uuid-1', 'title' => 'Bug', 'color' => '#E74C3C'],
            ],
        ];

        $this->objectService->expects($this->once())
            ->method('getResultArrayForRequest')
            ->with('planix', 'label', [])
            ->willReturn($expected);

        $result = $this->service->findAll();

        self::assertSame(expected: $expected, actual: $result);

    }//end testFindAllReturnsLabels()

    /**
     * Test that create() saves a label and normalises the name field to title.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return void
     */
    public function testCreateSavesLabelWithNameNormalisedToTitle(): void
    {
        $input    = ['name' => 'Urgent', 'color' => '#FF0000'];
        $expected = ['id' => 'uuid-new', 'title' => 'Urgent', 'color' => '#FF0000'];

        $this->objectService->expects($this->once())
            ->method('saveObject')
            ->with('planix', 'label', ['title' => 'Urgent', 'color' => '#FF0000'])
            ->willReturn($expected);

        $result = $this->service->create($input);

        self::assertSame(expected: $expected, actual: $result);

    }//end testCreateSavesLabelWithNameNormalisedToTitle()

    /**
     * Test that create() throws InvalidArgumentException when name is missing.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return void
     */
    public function testCreateThrowsWhenNameMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Label name is required.');

        $this->service->create(['color' => '#FF0000']);

    }//end testCreateThrowsWhenNameMissing()

    /**
     * Test that delete() calls deleteObject on OpenRegister.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return void
     */
    public function testDeleteCallsObjectService(): void
    {
        $this->objectService->expects($this->once())
            ->method('deleteObject')
            ->with('planix', 'label', 'uuid-1')
            ->willReturn(true);

        $result = $this->service->delete('uuid-1');

        self::assertTrue(condition: $result);

    }//end testDeleteCallsObjectService()

    /**
     * Test that findAll() throws RuntimeException when OpenRegister is unavailable.
     *
     * @spec openspec/changes/label-crud/tasks.md#task-1
     *
     * @return void
     */
    public function testFindAllThrowsWhenOpenRegisterUnavailable(): void
    {
        $container = $this->createMock(originalClassName: ContainerInterface::class);
        $container->method('get')
            ->willThrowException(new \RuntimeException('Service not found'));

        $service = new LabelService(
            container: $container,
            logger: $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $service->findAll();

    }//end testFindAllThrowsWhenOpenRegisterUnavailable()
}//end class
