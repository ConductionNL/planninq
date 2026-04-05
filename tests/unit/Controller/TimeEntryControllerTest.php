<?php

/**
 * Unit tests for TimeEntryController.
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

use OCA\Planix\Controller\TimeEntryController;
use OCA\Planix\Service\TimeEntryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TimeEntryController.
 */
class TimeEntryControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var TimeEntryController
     */
    private TimeEntryController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock TimeEntryService.
     *
     * @var TimeEntryService&MockObject
     */
    private TimeEntryService&MockObject $timeEntryService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request          = $this->createMock(originalClassName: IRequest::class);
        $this->timeEntryService = $this->createMock(originalClassName: TimeEntryService::class);

        $this->controller = new TimeEntryController(
            request: $this->request,
            timeEntryService: $this->timeEntryService,
        );

    }//end setUp()

    /**
     * Test that create() returns 403 when no user is authenticated.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForUnauthenticatedUser(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        $this->timeEntryService->expects($this->never())
            ->method('createTimeEntry');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsForbiddenForUnauthenticatedUser()

    /**
     * Test that create() returns 201 with the created entry on success.
     *
     * @return void
     */
    public function testCreateReturnsCreatedEntryOnSuccess(): void
    {
        $params = [
            'taskId'      => 'task-uuid-1',
            'duration'    => 60,
            'date'        => '2026-04-01',
            'description' => 'Worked on feature',
        ];

        $created = array_merge($params, ['id' => 'entry-uuid-1', 'user' => 'testuser']);

        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('testuser');

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->timeEntryService->expects($this->once())
            ->method('createTimeEntry')
            ->with($params)
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
        self::assertSame(expected: 'entry-uuid-1', actual: $result->getData()['id']);

    }//end testCreateReturnsCreatedEntryOnSuccess()

    /**
     * Test that create() returns 400 when validation fails.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestOnValidationError(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('testuser');

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['taskId' => '', 'duration' => 0]);

        $this->timeEntryService->expects($this->once())
            ->method('createTimeEntry')
            ->willThrowException(new \InvalidArgumentException('taskId is required.'));

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertSame(expected: 'taskId is required.', actual: $result->getData()['error']);

    }//end testCreateReturnsBadRequestOnValidationError()

    /**
     * Test that index() returns 403 when no user is authenticated.
     *
     * @return void
     */
    public function testIndexReturnsForbiddenForUnauthenticatedUser(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testIndexReturnsForbiddenForUnauthenticatedUser()

    /**
     * Test that index() returns 400 when taskId is missing.
     *
     * @return void
     */
    public function testIndexReturnsBadRequestWithoutTaskId(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('testuser');

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('taskId', '')
            ->willReturn('');

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());

    }//end testIndexReturnsBadRequestWithoutTaskId()

    /**
     * Test that index() returns time entries for a task.
     *
     * @return void
     */
    public function testIndexReturnsEntriesForTask(): void
    {
        $taskId  = 'task-uuid-1';
        $entries = [
            ['id' => 'e1', 'task' => $taskId, 'duration' => 30, 'date' => '2026-04-01'],
            ['id' => 'e2', 'task' => $taskId, 'duration' => 45, 'date' => '2026-04-02'],
        ];

        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('testuser');

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('taskId', '')
            ->willReturn($taskId);

        $this->timeEntryService->expects($this->once())
            ->method('listTimeEntries')
            ->with($taskId)
            ->willReturn($entries);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertCount(expectedCount: 2, haystack: $result->getData());

    }//end testIndexReturnsEntriesForTask()

    /**
     * Test that destroy() returns 403 when no user is authenticated.
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenForUnauthenticatedUser(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        $result = $this->controller->destroy('entry-uuid-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testDestroyReturnsForbiddenForUnauthenticatedUser()

    /**
     * Test that destroy() returns success when owner deletes their entry.
     *
     * @return void
     */
    public function testDestroyReturnsSuccessForOwner(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('testuser');

        $this->timeEntryService->expects($this->once())
            ->method('deleteTimeEntry')
            ->with('entry-uuid-1')
            ->willReturn(true);

        $result = $this->controller->destroy('entry-uuid-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroyReturnsSuccessForOwner()

    /**
     * Test that destroy() returns 403 when non-owner tries to delete.
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenForNonOwner(): void
    {
        $this->timeEntryService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('otheruser');

        $this->timeEntryService->expects($this->once())
            ->method('deleteTimeEntry')
            ->with('entry-uuid-1')
            ->willThrowException(new \RuntimeException('Only the owner may delete a time entry.'));

        $result = $this->controller->destroy('entry-uuid-1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testDestroyReturnsForbiddenForNonOwner()
}//end class
