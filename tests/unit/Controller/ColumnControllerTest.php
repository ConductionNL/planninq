<?php

/**
 * Unit tests for ColumnController.
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

use OCA\Planix\Controller\ColumnController;
use OCA\Planix\Service\ColumnService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ColumnController.
 */
class ColumnControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var ColumnController
     */
    private ColumnController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock ColumnService.
     *
     * @var ColumnService&MockObject
     */
    private ColumnService&MockObject $columnService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request       = $this->createMock(originalClassName: IRequest::class);
        $this->columnService = $this->createMock(originalClassName: ColumnService::class);

        $this->controller = new ColumnController(
            request: $this->request,
            columnService: $this->columnService,
        );

    }//end setUp()

    /**
     * Test that index() returns columns for a valid project member.
     *
     * @return void
     */
    public function testIndexReturnsColumnsForProjectMember(): void
    {
        $projectId = 'proj-uuid-1';
        $columns   = [
            ['id' => 'col-1', 'title' => 'To Do', 'order' => 0],
            ['id' => 'col-2', 'title' => 'In Progress', 'order' => 1],
        ];

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('projectId')
            ->willReturn($projectId);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with($projectId)
            ->willReturn(true);

        $this->columnService->expects($this->once())
            ->method('listColumns')
            ->with($projectId)
            ->willReturn($columns);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertSame(expected: $columns, actual: $result->getData());

    }//end testIndexReturnsColumnsForProjectMember()

    /**
     * Test that index() returns 403 for non-project-members.
     *
     * @return void
     */
    public function testIndexReturnsForbiddenForNonMember(): void
    {
        $projectId = 'proj-uuid-1';

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('projectId')
            ->willReturn($projectId);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with($projectId)
            ->willReturn(false);

        $this->columnService->expects($this->never())
            ->method('listColumns');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testIndexReturnsForbiddenForNonMember()

    /**
     * Test that index() returns 400 when projectId is missing.
     *
     * @return void
     */
    public function testIndexReturnsBadRequestWithoutProjectId(): void
    {
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('projectId')
            ->willReturn(null);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());

    }//end testIndexReturnsBadRequestWithoutProjectId()

    /**
     * Test that create() creates a column successfully.
     *
     * @return void
     */
    public function testCreateReturnsCreatedColumn(): void
    {
        $params  = ['title' => 'Testing', 'project' => 'proj-uuid-1', 'order' => 4, 'type' => 'active'];
        $created = array_merge($params, ['id' => 'col-new']);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1')
            ->willReturn(true);

        $this->columnService->expects($this->once())
            ->method('createColumn')
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
        self::assertSame(expected: 'col-new', actual: $result->getData()['id']);

    }//end testCreateReturnsCreatedColumn()

    /**
     * Test that update() returns 404 for non-existent column.
     *
     * @return void
     */
    public function testUpdateReturnsNotFoundForMissingColumn(): void
    {
        $this->columnService->expects($this->once())
            ->method('findColumn')
            ->with('nonexistent')
            ->willReturn(null);

        $result = $this->controller->update('nonexistent');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testUpdateReturnsNotFoundForMissingColumn()

    /**
     * Test that update() returns 403 for non-project-member.
     *
     * @return void
     */
    public function testUpdateReturnsForbiddenForNonMember(): void
    {
        $column = ['id' => 'col-1', 'project' => 'proj-uuid-1', 'title' => 'To Do'];

        $this->columnService->expects($this->once())
            ->method('findColumn')
            ->with('col-1')
            ->willReturn($column);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1')
            ->willReturn(false);

        $result = $this->controller->update('col-1');

        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testUpdateReturnsForbiddenForNonMember()

    /**
     * Test that destroy() returns 404 for non-existent column.
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundForMissingColumn(): void
    {
        $this->columnService->expects($this->once())
            ->method('findColumn')
            ->with('nonexistent')
            ->willReturn(null);

        $result = $this->controller->destroy('nonexistent');

        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testDestroyReturnsNotFoundForMissingColumn()

    /**
     * Test that destroy() deletes column and returns success for project member.
     *
     * @return void
     */
    public function testDestroyDeletesColumnForMember(): void
    {
        $column = ['id' => 'col-1', 'project' => 'proj-uuid-1', 'title' => 'To Do'];

        $this->columnService->expects($this->once())
            ->method('findColumn')
            ->with('col-1')
            ->willReturn($column);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1')
            ->willReturn(true);

        $this->columnService->expects($this->once())
            ->method('deleteColumn')
            ->with('col-1')
            ->willReturn(true);

        $result = $this->controller->destroy('col-1');

        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroyDeletesColumnForMember()
}//end class
