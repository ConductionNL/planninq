<?php

/**
 * Unit tests for HealthController.
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
 *
 * @spec openspec/changes/status-api/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\HealthController;
use OCA\Planix\Service\SettingsService;
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
     * Mock SettingsService.
     *
     * @var SettingsService&MockObject
     */
    private SettingsService&MockObject $settingsService;

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

        $this->request         = $this->createMock(originalClassName: IRequest::class);
        $this->settingsService = $this->createMock(originalClassName: SettingsService::class);
        $this->appManager      = $this->createMock(originalClassName: IAppManager::class);

        $this->controller = new HealthController(
            request: $this->request,
            settingsService: $this->settingsService,
            appManager: $this->appManager,
        );

    }//end setUp()

    /**
     * Test that index() returns HTTP 200 with status "ok" when OpenRegister is available.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexReturnsOkWhenOpenRegisterAvailable(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isOpenRegisterAvailable')
            ->willReturn(true);

        $this->appManager->expects($this->once())
            ->method('getAppVersion')
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
     * Test that index() returns HTTP 503 with status "degraded" when OpenRegister is unavailable.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexReturnsDegradedWhenOpenRegisterUnavailable(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isOpenRegisterAvailable')
            ->willReturn(false);

        $this->appManager->expects($this->once())
            ->method('getAppVersion')
            ->willReturn('0.2.1');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $result->getStatus());

        $data = $result->getData();
        self::assertSame(expected: 'degraded', actual: $data['status']);
        self::assertFalse(condition: $data['openRegisterAvailable']);

    }//end testIndexReturnsDegradedWhenOpenRegisterUnavailable()

    /**
     * Test that index() response contains all required JSON fields.
     *
     * @spec openspec/changes/status-api/tasks.md#task-1
     *
     * @return void
     */
    public function testIndexResponseContainsRequiredFields(): void
    {
        $this->settingsService->expects($this->once())
            ->method('isOpenRegisterAvailable')
            ->willReturn(true);

        $this->appManager->expects($this->once())
            ->method('getAppVersion')
            ->willReturn('1.0.0');

        $result = $this->controller->index();
        $data   = $result->getData();

        self::assertArrayHasKey(key: 'status', array: $data);
        self::assertArrayHasKey(key: 'version', array: $data);
        self::assertArrayHasKey(key: 'openRegisterAvailable', array: $data);
        self::assertSame(expected: '1.0.0', actual: $data['version']);

    }//end testIndexResponseContainsRequiredFields()
}//end class
