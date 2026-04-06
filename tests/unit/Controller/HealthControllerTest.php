<?php

/**
 * Unit tests for HealthController.
 *
 * @spec     openspec/changes/status-api/tasks.md#task-1
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
     * Test that index() returns HTTP 200 with status "ok" when OpenRegister is available.
     *
     * @return void
     */
    public function testIndexReturnsOkWhenOpenRegisterAvailable(): void
    {
        $this->appManager->expects($this->once())
            ->method('isEnabledForUser')
            ->with('openregister')
            ->willReturn(true);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());

        $data = $result->getData();
        self::assertSame(expected: 'ok', actual: $data['status']);
        self::assertTrue(condition: $data['openRegisterAvailable']);

    }//end testIndexReturnsOkWhenOpenRegisterAvailable()

    /**
     * Test that index() returns HTTP 503 with status "degraded" when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testIndexReturnsDegradedWhenOpenRegisterUnavailable(): void
    {
        $this->appManager->expects($this->once())
            ->method('isEnabledForUser')
            ->with('openregister')
            ->willReturn(false);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $result->getStatus());

        $data = $result->getData();
        self::assertSame(expected: 'degraded', actual: $data['status']);
        self::assertFalse(condition: $data['openRegisterAvailable']);

    }//end testIndexReturnsDegradedWhenOpenRegisterUnavailable()

    /**
     * Test that the healthy response always contains the two required fields.
     *
     * @return void
     */
    public function testResponseContainsRequiredFields(): void
    {
        $this->appManager->expects($this->once())
            ->method('isEnabledForUser')
            ->with('openregister')
            ->willReturn(true);

        $result = $this->controller->index();
        $data   = $result->getData();

        self::assertArrayHasKey(key: 'status', array: $data);
        self::assertArrayHasKey(key: 'openRegisterAvailable', array: $data);

    }//end testResponseContainsRequiredFields()

    /**
     * Test that the degraded response always contains the two required fields.
     *
     * @return void
     */
    public function testDegradedResponseContainsRequiredFields(): void
    {
        $this->appManager->expects($this->once())
            ->method('isEnabledForUser')
            ->with('openregister')
            ->willReturn(false);

        $result = $this->controller->index();
        $data   = $result->getData();

        self::assertArrayHasKey(key: 'status', array: $data);
        self::assertArrayHasKey(key: 'openRegisterAvailable', array: $data);

    }//end testDegradedResponseContainsRequiredFields()
}//end class
