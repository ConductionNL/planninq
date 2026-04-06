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
    public function testIndexReturnsAllLabels(): void
    {
        $labels = [
            [
                'id'    => 'uuid-1',
                'title' => 'Bug',
                'color' => '#FF0000',
            ],
            [
                'id'    => 'uuid-2',
                'title' => 'Feature',
                'color' => '#00FF00',
            ],
        ];

        $this->labelService->expects($this->once())
            ->method('findAll')
            ->willReturn($labels);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: $labels, actual: $result->getData());
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());

    }//end testIndexReturnsAllLabels()

    /**
     * Test that create() returns 403 when the user is not an admin.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNonAdmin(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(false);

        $this->labelService->expects($this->never())->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsForbiddenForNonAdmin()

    /**
     * Test that create() returns 400 when title is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutTitle(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['title', null, null],
                    ['color', null, '#FF0000'],
                ]
            );

        $this->labelService->expects($this->never())
            ->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutTitle()

    /**
     * Test that create() returns 400 when color is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutColor(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['title', null, 'Bug'],
                    ['color', null, null],
                ]
            );

        $this->labelService->expects($this->never())
            ->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutColor()

    /**
     * Test that create() returns 400 when color is not a valid hex format.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestForInvalidColorFormat(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['title', null, 'Bug'],
                    ['color', null, 'not-a-color'],
                ]
            );

        $this->labelService->expects($this->never())->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestForInvalidColorFormat()

    /**
     * Test that create() creates a label with title and color.
     *
     * @return void
     */
    public function testCreateReturnsCreatedLabel(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['title', null, 'Bug'],
                    ['color', null, '#FF0000'],
                ]
            );

        $created = [
            'id'    => 'uuid-new',
            'title' => 'Bug',
            'color' => '#FF0000',
        ];

        $this->labelService->expects($this->once())
            ->method('create')
            ->with(['title' => 'Bug', 'color' => '#FF0000'])
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
        self::assertSame(expected: 'Bug', actual: $result->getData()['title']);

    }//end testCreateReturnsCreatedLabel()

    /**
     * Test that destroy() returns 403 when the user is not an admin.
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenForNonAdmin(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(false);

        $this->labelService->expects($this->never())->method('delete');

        $result = $this->controller->destroy(id: 'uuid-to-delete');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturnsForbiddenForNonAdmin()

    /**
     * Test that destroy() returns 204 No Content on successful deletion.
     *
     * @return void
     */
    public function testDestroyReturnsNoContent(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->labelService->expects($this->once())
            ->method('delete')
            ->with('uuid-to-delete')
            ->willReturn(true);

        $result = $this->controller->destroy(id: 'uuid-to-delete');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NO_CONTENT, actual: $result->getStatus());

    }//end testDestroyReturnsNoContent()

    /**
     * Test that destroy() returns 404 when the label does not exist.
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundWhenLabelMissing(): void
    {
        $this->labelService->method('isCurrentUserAdmin')->willReturn(true);

        $this->labelService->expects($this->once())
            ->method('delete')
            ->with('uuid-missing')
            ->willReturn(false);

        $result = $this->controller->destroy(id: 'uuid-missing');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturnsNotFoundWhenLabelMissing()

    /**
     * Test that index() returns 500 when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testIndexReturnsErrorWhenServiceFails(): void
    {
        $this->labelService->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \RuntimeException('OpenRegister is not installed or enabled.'));

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testIndexReturnsErrorWhenServiceFails()
}//end class
