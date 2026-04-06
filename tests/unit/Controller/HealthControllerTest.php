<?php

/**
 * Unit tests for HealthController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * Copyright (C) 2026 Conduction B.V.
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Controller
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

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\HealthController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HealthController.
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */
class HealthControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var HealthController
     */
    private HealthController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock IAppManager.
     *
     * @var IAppManager&MockObject
     */
    private IAppManager&MockObject $appManager;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request    = $this->createMock(originalClassName: IRequest::class);
        $this->appManager = $this->createMock(originalClassName: IAppManager::class);

        $this->controller = new HealthController(
            request: $this->request,
            appManager: $this->appManager,
        );

    }//end setUp()

    /**
     * Test that index() returns 200 with status ok when OpenRegister is available.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexReturnsOkWhenOpenRegisterAvailable(): void
    {
        $this->appManager->expects($this->once())
            ->method('isInstalled')
            ->with('openregister')
            ->willReturn(true);

        $this->appManager->expects($this->once())
            ->method('getAppVersion')
            ->with('planix')
            ->willReturn('0.2.1');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());

        $data = $result->getData();
        self::assertSame(expected: 'ok', actual: $data['status']);
        self::assertSame(expected: '0.2.1', actual: $data['version']);
        self::assertTrue(condition: $data['openRegisterAvailable']);

    }//end testIndexReturnsOkWhenOpenRegisterAvailable()

    /**
     * Test that index() returns 503 with degraded status when OpenRegister is unavailable.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexReturnsDegradedWhenOpenRegisterUnavailable(): void
    {
        $this->appManager->expects($this->once())
            ->method('isInstalled')
            ->with('openregister')
            ->willReturn(false);

        $this->appManager->expects($this->once())
            ->method('getAppVersion')
            ->with('planix')
            ->willReturn('0.2.1');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $result->getStatus());

        $data = $result->getData();
        self::assertSame(expected: 'degraded', actual: $data['status']);
        self::assertFalse(condition: $data['openRegisterAvailable']);

    }//end testIndexReturnsDegradedWhenOpenRegisterUnavailable()

    /**
     * Test that the response always contains required fields: status, version, openRegisterAvailable.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexResponseContainsRequiredFields(): void
    {
        $this->appManager->method('isInstalled')->willReturn(true);
        $this->appManager->method('getAppVersion')->willReturn('1.0.0');

        $result = $this->controller->index();
        $data   = $result->getData();

        self::assertArrayHasKey(key: 'status', array: $data);
        self::assertArrayHasKey(key: 'version', array: $data);
        self::assertArrayHasKey(key: 'openRegisterAvailable', array: $data);

    }//end testIndexResponseContainsRequiredFields()
}//end class
