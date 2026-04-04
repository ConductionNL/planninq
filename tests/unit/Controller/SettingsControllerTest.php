<?php

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

        $this->request         = $this->createMock(originalClassName: IRequest::class);
        $this->settingsService = $this->createMock(originalClassName: SettingsService::class);

        $this->controller = new SettingsController(
            request: $this->request,
            settingsService: $this->settingsService,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse containing the admin settings from the service.
     *
     * @return void
     */
    public function testIndexReturnsJsonResponseWithSettings(): void
    {
        $settings = [
            'register'               => 'some-uuid',
            'default_columns'        => ['To Do', 'In Progress', 'Review', 'Done'],
            'allow_project_creation' => 'all',
            'openregisters'          => true,
            'isAdmin'                => false,
        ];

        $this->settingsService->expects($this->once())
            ->method('getAdminSettings')
            ->willReturn($settings);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: $settings, actual: $result->getData());

    }//end testIndexReturnsJsonResponseWithSettings()

    /**
     * Test that create() returns 403 for non-admin users.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 403, actual: $result->getStatus());

    }//end testCreateReturnsForbiddenForNonAdmin()

    /**
     * Test that create() calls setAdminSettings with request params and returns success.
     *
     * @return void
     */
    public function testCreateCallsSetAdminSettingsAndReturnsSuccess(): void
    {
        $params  = ['default_columns' => ['To Do', 'Done'], 'allow_project_creation' => 'all'];
        $updated = array_merge($params, ['register' => '', 'openregisters' => true, 'isAdmin' => true]);

        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->settingsService->expects($this->once())
            ->method('setAdminSettings')
            ->with($params)
            ->willReturn($updated);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertTrue(condition: $result->getData()['success']);
        self::assertArrayHasKey(key: 'config', array: $result->getData());

    }//end testCreateCallsSetAdminSettingsAndReturnsSuccess()

    /**
     * Test that load() returns 403 for non-admin users.
     *
     * @return void
     */
    public function testLoadReturnsForbiddenForNonAdmin(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isCurrentUserAdmin')
            ->willReturn(false);

        $result = $this->controller->load();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 403, actual: $result->getStatus());

    }//end testLoadReturnsForbiddenForNonAdmin()

    /**
     * Test that load() returns the result of loadConfiguration for admin users.
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
            ->method('isCurrentUserAdmin')
            ->willReturn(true);

        $this->settingsService->expects($this->once())
            ->method('loadConfiguration')
            ->with(force: true)
            ->willReturn($loadResult);

        $result = $this->controller->load();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertTrue(condition: $result->getData()['success']);

    }//end testLoadReturnsConfigurationResult()
}//end class
