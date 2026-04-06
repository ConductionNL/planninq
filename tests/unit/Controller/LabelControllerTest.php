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
     * Test that create() returns 400 when name is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutName(): void
    {
        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['name', null, null],
                    ['color', null, null],
                ]
            );

        $this->labelService->expects($this->never())
            ->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutName()

    /**
     * Test that create() creates a label with name and color.
     *
     * @return void
     */
    public function testCreateReturnsCreatedLabel(): void
    {
        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['name', null, 'Bug'],
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
     * Test that create() creates a label with name only (no color).
     *
     * @return void
     */
    public function testCreateWithNameOnlyOmitsColor(): void
    {
        $this->request->method('getParam')
            ->willReturnMap(
                [
                    ['name', null, 'Enhancement'],
                    ['color', null, null],
                ]
            );

        $created = [
            'id'    => 'uuid-new',
            'title' => 'Enhancement',
            'color' => '#4376FC',
        ];

        $this->labelService->expects($this->once())
            ->method('create')
            ->with(['title' => 'Enhancement'])
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());

    }//end testCreateWithNameOnlyOmitsColor()

    /**
     * Test that destroy() returns success on valid deletion.
     *
     * @return void
     */
    public function testDestroyReturnsSuccess(): void
    {
        $this->labelService->expects($this->once())
            ->method('delete')
            ->with('uuid-to-delete')
            ->willReturn(true);

        $result = $this->controller->destroy(id: 'uuid-to-delete');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroyReturnsSuccess()

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
