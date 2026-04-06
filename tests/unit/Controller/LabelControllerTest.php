<?php

/**
 * Unit tests for LabelController.
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

use OCA\Planix\Controller\LabelController;
use OCA\Planix\Service\LabelService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LabelController.
 */
class LabelControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var LabelController
     */
    private LabelController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock LabelService.
     *
     * @var LabelService&MockObject
     */
    private LabelService&MockObject $labelService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request      = $this->createMock(originalClassName: IRequest::class);
        $this->labelService = $this->createMock(originalClassName: LabelService::class);

        $this->controller = new LabelController(
            request: $this->request,
            labelService: $this->labelService,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse with all labels.
     *
     * @return void
     */
    public function testIndexReturnsLabels(): void
    {
        $labels = [
            ['id' => 'uuid-1', 'title' => 'Bug', 'color' => '#E74C3C'],
            ['id' => 'uuid-2', 'title' => 'Feature', 'color' => '#4376FC'],
        ];

        $this->labelService->expects($this->once())
            ->method('findAll')
            ->willReturn($labels);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: $labels, actual: $result->getData());
        self::assertSame(expected: 200, actual: $result->getStatus());

    }//end testIndexReturnsLabels()

    /**
     * Test that create() returns 400 when name is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutName(): void
    {
        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['color' => '#FF0000']);

        $this->labelService->expects($this->never())
            ->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutName()

    /**
     * Test that create() calls the service and returns 201 on success.
     *
     * @return void
     */
    public function testCreateReturnsCreatedLabel(): void
    {
        $params  = ['name' => 'Urgent', 'color' => '#FF0000'];
        $created = ['id' => 'uuid-new', 'title' => 'Urgent', 'color' => '#FF0000'];

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->labelService->expects($this->once())
            ->method('create')
            ->with($params)
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
        self::assertSame(expected: $created, actual: $result->getData());

    }//end testCreateReturnsCreatedLabel()

    /**
     * Test that destroy() returns success when the label is deleted.
     *
     * @return void
     */
    public function testDestroyReturnsSuccess(): void
    {
        $this->labelService->expects($this->once())
            ->method('delete')
            ->with('uuid-1')
            ->willReturn(true);

        $result = $this->controller->destroy('uuid-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroyReturnsSuccess()

    /**
     * Test that destroy() returns 404 when the label cannot be found.
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundWhenDeleteFails(): void
    {
        $this->labelService->expects($this->once())
            ->method('delete')
            ->with('nonexistent-id')
            ->willReturn(false);

        $result = $this->controller->destroy('nonexistent-id');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturnsNotFoundWhenDeleteFails()

    /**
     * Test that index() handles RuntimeException from the service.
     *
     * @return void
     */
    public function testIndexReturnsErrorWhenServiceThrows(): void
    {
        $this->labelService->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \RuntimeException('OpenRegister is not installed or enabled.'));

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testIndexReturnsErrorWhenServiceThrows()
}//end class
