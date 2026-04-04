<?php

// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2024 Conduction B.V.

/**
 * Unit tests for SettingsController.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\SettingsController;
use OCA\Planix\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SettingsController.
 */
class SettingsControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var SettingsController
     */
    private SettingsController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock SettingsService.
     *
     * @var SettingsService&MockObject
     */
    private SettingsService&MockObject $settingsService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(IRequest::class);
        $this->settingsService = $this->createMock(SettingsService::class);

        $this->controller = new SettingsController(
            request: $this->request,
            settingsService: $this->settingsService,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse containing the settings from the service.
     *
     * @return void
     */
    public function testIndexReturnsJsonResponseWithSettings(): void
    {
        $settings = [
            'register'               => 'some-uuid',
            'default_columns'        => '["To Do","In Progress","Review","Done"]',
            'allow_project_creation' => 'all',
            'openregisters'          => true,
            'isAdmin'                => false,
        ];

        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn($settings);

        $result = $this->controller->index();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame($settings, $result->getData());

    }//end testIndexReturnsJsonResponseWithSettings()

    /**
     * Test that create() returns 403 when the current user is not an admin.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $this->settingsService->expects($this->never())
            ->method('updateSettings');

        $result = $this->controller->create();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
        self::assertArrayHasKey('error', $result->getData());

    }//end testCreateReturnsForbiddenForNonAdmin()

    /**
     * Test that create() calls updateSettings with request params and returns success for admins.
     *
     * @return void
     */
    public function testCreateCallsUpdateSettingsAndReturnsSuccess(): void
    {
        $params  = ['register' => 'new-uuid', 'default_columns' => '["Backlog","Doing","Done"]'];
        $updated = array_merge($params, ['openregisters' => true, 'isAdmin' => true]);

        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->settingsService->expects($this->once())
            ->method('updateSettings')
            ->with($params)
            ->willReturn($updated);

        $result = $this->controller->create();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(200, $result->getStatus());
        self::assertTrue($result->getData()['success']);
        self::assertArrayHasKey('config', $result->getData());

    }//end testCreateCallsUpdateSettingsAndReturnsSuccess()

    /**
     * Test that load() returns the result of loadConfiguration.
     *
     * @return void
     */
    public function testLoadReturnsConfigurationResult(): void
    {
        $loadResult = [
            'success' => true,
            'message' => 'Configuration imported successfully.',
            'version' => '0.1.0',
        ];

        $this->settingsService->expects($this->once())
            ->method('loadConfiguration')
            ->with(force: true)
            ->willReturn($loadResult);

        $result = $this->controller->load();

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertTrue($result->getData()['success']);

    }//end testLoadReturnsConfigurationResult()
}//end class
