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

        $project = ['id' => $projectId, 'members' => ['user1']];

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with($projectId)
            ->willReturn($project);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with($projectId, $project)
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
     * Test that index() returns 404 for non-existent projects.
     *
     * @return void
     */
    public function testIndexReturnsNotFoundForNonExistentProject(): void
    {
        $projectId = 'nonexistent';

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('projectId')
            ->willReturn($projectId);

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with($projectId)
            ->willReturn(null);

        $this->columnService->expects($this->never())
            ->method('listColumns');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testIndexReturnsNotFoundForNonExistentProject()

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

        $project = ['id' => $projectId, 'members' => ['other-user']];

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with($projectId)
            ->willReturn($project);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with($projectId, $project)
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

        $project = ['id' => 'proj-uuid-1', 'members' => ['user1']];

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with('proj-uuid-1')
            ->willReturn($project);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1', $project)
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
     * Test that create() returns 400 when project field is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutProject(): void
    {
        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['title' => 'My Column']);

        $this->columnService->expects($this->never())
            ->method('isProjectMember');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutProject()

    /**
     * Test that create() returns 400 when title is empty.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithEmptyTitle(): void
    {
        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['project' => 'proj-uuid-1', 'title' => '']);

        $project = ['id' => 'proj-uuid-1', 'members' => ['user1']];

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with('proj-uuid-1')
            ->willReturn($project);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1', $project)
            ->willReturn(true);

        $this->columnService->expects($this->never())
            ->method('createColumn');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithEmptyTitle()

    /**
     * Test that create() returns 403 for non-project-member.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNonMember(): void
    {
        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['title' => 'My Column', 'project' => 'proj-uuid-1']);

        $project = ['id' => 'proj-uuid-1', 'members' => ['other-user']];

        $this->columnService->expects($this->once())
            ->method('findProject')
            ->with('proj-uuid-1')
            ->willReturn($project);

        $this->columnService->expects($this->once())
            ->method('isProjectMember')
            ->with('proj-uuid-1', $project)
            ->willReturn(false);

        $this->columnService->expects($this->never())
            ->method('createColumn');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsForbiddenForNonMember()

    /**
     * Test that update() returns 500 when service throws RuntimeException.
     *
     * @return void
     */
    public function testUpdateReturns500OnServiceException(): void
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

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['title' => 'Updated']);

        $this->columnService->expects($this->once())
            ->method('updateColumn')
            ->willThrowException(new \RuntimeException('OpenRegister unavailable'));

        $result = $this->controller->update('col-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testUpdateReturns500OnServiceException()

    /**
     * Test that destroy() returns 500 when service throws RuntimeException.
     *
     * @return void
     */
    public function testDestroyReturns500OnServiceException(): void
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
            ->willThrowException(new \RuntimeException('OpenRegister unavailable'));

        $result = $this->controller->destroy('col-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturns500OnServiceException()

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
     * Test that destroy() deletes column and returns 204 for project member.
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

        self::assertSame(expected: Http::STATUS_NO_CONTENT, actual: $result->getStatus());

    }//end testDestroyDeletesColumnForMember()
}//end class
