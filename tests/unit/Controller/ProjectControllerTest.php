<?php

/**
 * Unit tests for ProjectController.
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

// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\ProjectController;
use OCA\Planix\Service\ProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for ProjectController.
 */
class ProjectControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var ProjectController
     */
    private ProjectController $controller;

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock ProjectService.
     *
     * @var ProjectService&MockObject
     */
    private ProjectService&MockObject $projectService;

    /**
     * Mock LoggerInterface.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request        = $this->createMock(originalClassName: IRequest::class);
        $this->projectService = $this->createMock(originalClassName: ProjectService::class);
        $this->logger         = $this->createMock(originalClassName: LoggerInterface::class);

        $this->controller = new ProjectController(
            request: $this->request,
            projectService: $this->projectService,
            logger: $this->logger,
        );

    }//end setUp()

    /**
     * Test that index() returns a JSONResponse with a list of projects.
     *
     * @return void
     */
    public function testIndexReturnsProjectList(): void
    {
        $projects = [
            ['id' => 'p1', 'title' => 'Alpha', 'members' => ['user1']],
            ['id' => 'p2', 'title' => 'Beta', 'members' => ['user1']],
        ];

        $this->projectService->expects($this->once())
            ->method('findAll')
            ->willReturn($projects);

        $result = $this->controller->index();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertCount(expectedCount: 2, haystack: $result->getData());

    }//end testIndexReturnsProjectList()

    /**
     * Test that show() returns 403 immediately when user session is absent (IDOR prevention).
     * Auth check must happen before the datastore lookup.
     *
     * @return void
     */
    public function testShowReturnsForbiddenForNullUidWithoutLookup(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        // No find() call should be made — auth check is first.
        $this->projectService->expects($this->never())
            ->method('find');

        $result = $this->controller->show(id: 'any-id');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testShowReturnsForbiddenForNullUidWithoutLookup()

    /**
     * Test that show() returns 404 when the project does not exist (authenticated user).
     *
     * @return void
     */
    public function testShowReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'nonexistent')
            ->willReturn(null);

        // find() returns null, so the || short-circuits — isMember is never called.
        $this->projectService->expects($this->never())
            ->method('isMember');

        $result = $this->controller->show(id: 'nonexistent');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testShowReturnsNotFoundForMissingProject()

    /**
     * Test that show() returns 404 when the user is not a member (IDOR prevention — no 403 oracle).
     *
     * @return void
     */
    public function testShowReturnsNotFoundForNonMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('other-user');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'other-user')
            ->willReturn(false);

        $result = $this->controller->show(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        // Returns 404 (not 403) to prevent existence oracle.
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testShowReturnsNotFoundForNonMember()

    /**
     * Test that show() returns the project when the user is authenticated and a member.
     *
     * @return void
     */
    public function testShowReturnsProjectForMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['user1']];

        // Auth check happens FIRST, then lookup.
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'user1')
            ->willReturn(true);

        $result = $this->controller->show(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertSame(expected: 'Alpha', actual: $result->getData()['title']);

    }//end testShowReturnsProjectForMember()

    /**
     * Test that create() returns 403 when unauthenticated (not 500).
     *
     * @return void
     */
    public function testCreateReturnsForbiddenForNullUid(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        // No request params or service create() call should happen.
        $this->request->expects($this->never())->method('getParams');
        $this->projectService->expects($this->never())->method('create');

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testCreateReturnsForbiddenForNullUid()

    /**
     * Test that create() returns 400 when title is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutTitle(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['description' => 'no title here']);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutTitle()

    /**
     * Test that create() returns 400 when color format is invalid.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestForInvalidColor(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['title' => 'My Project', 'color' => 'notacolor']);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertStringContainsString(needle: 'color', haystack: strtolower((string) $result->getData()['error']));

    }//end testCreateReturnsBadRequestForInvalidColor()

    /**
     * Test that create() returns 201 with a valid project.
     *
     * @return void
     */
    public function testCreateReturnsCreatedProject(): void
    {
        $params  = ['title' => 'New Project', 'description' => 'A test', 'color' => '#ff0000'];
        $created = ['id' => 'p-new', 'title' => 'New Project', 'members' => ['user1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->projectService->expects($this->once())
            ->method('create')
            ->willReturn($created);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
        self::assertSame(expected: 'New Project', actual: $result->getData()['title']);

    }//end testCreateReturnsCreatedProject()

    /**
     * Test that update() returns 403 immediately when user session is absent (IDOR prevention).
     *
     * @return void
     */
    public function testUpdateReturnsForbiddenForNullUidWithoutLookup(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        // No find() call should be made — auth check is first.
        $this->projectService->expects($this->never())->method('find');

        $result = $this->controller->update(id: 'any-id');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testUpdateReturnsForbiddenForNullUidWithoutLookup()

    /**
     * Test that update() returns 404 for a non-existent project (authenticated user).
     *
     * @return void
     */
    public function testUpdateReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'missing')
            ->willReturn(null);

        // find() returns null, so the || short-circuits — isMember is never called.
        $this->projectService->expects($this->never())
            ->method('isMember');

        $result = $this->controller->update(id: 'missing');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testUpdateReturnsNotFoundForMissingProject()

    /**
     * Test that update() returns 404 when the user is not a member (IDOR prevention — no 403 oracle).
     *
     * @return void
     */
    public function testUpdateReturnsNotFoundForNonMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('other-user');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'other-user')
            ->willReturn(false);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        // Returns 404 (not 403) to prevent existence oracle.
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testUpdateReturnsNotFoundForNonMember()

    /**
     * Test that update() returns 400 when title is an empty string.
     *
     * @return void
     */
    public function testUpdateReturnsBadRequestForEmptyTitle(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'owner1')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['title' => '   ']);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testUpdateReturnsBadRequestForEmptyTitle()

    /**
     * Test that update() returns 403 when a non-owner attempts to modify the members list.
     *
     * @return void
     */
    public function testUpdateReturnsForbiddenWhenNonOwnerModifiesMembers(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1', 'user2']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user2');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'user2')
            ->willReturn(true);

        $this->projectService->expects($this->once())
            ->method('isOwner')
            ->with(project: $project, uid: 'user2')
            ->willReturn(false);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['members' => ['attacker']]);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testUpdateReturnsForbiddenWhenNonOwnerModifiesMembers()

    /**
     * Test that update() returns 400 when color format is invalid.
     *
     * @return void
     */
    public function testUpdateReturnsBadRequestForInvalidColor(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['color' => 'badcolor']);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());

    }//end testUpdateReturnsBadRequestForInvalidColor()

    /**
     * Test that update() returns 400 when status value is invalid.
     *
     * @return void
     */
    public function testUpdateReturnsBadRequestForInvalidStatus(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['status' => 'invalid-status']);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());

    }//end testUpdateReturnsBadRequestForInvalidStatus()

    /**
     * Test that update() allows the owner to modify the members list.
     *
     * @return void
     */
    public function testUpdateAllowsOwnerToModifyMembers(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];
        $updated = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1', 'user2']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'owner1')
            ->willReturn(true);

        $this->projectService->expects($this->once())
            ->method('isOwner')
            ->with(project: $project, uid: 'owner1')
            ->willReturn(true);

        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['members' => ['owner1', 'user2']]);

        $this->projectService->expects($this->once())
            ->method('update')
            ->willReturn($updated);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());

    }//end testUpdateAllowsOwnerToModifyMembers()

    /**
     * Test that destroy() returns 403 immediately when user session is absent (IDOR prevention).
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenForNullUidWithoutLookup(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(null);

        // No find() call should be made — auth check is first.
        $this->projectService->expects($this->never())->method('find');

        $result = $this->controller->destroy(id: 'any-id');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testDestroyReturnsForbiddenForNullUidWithoutLookup()

    /**
     * Test that destroy() returns 404 for a non-existent project (authenticated user).
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'gone')
            ->willReturn(null);

        // find() returns null, so the || short-circuits — isOwner is never called.
        $this->projectService->expects($this->never())
            ->method('isOwner');

        $result = $this->controller->destroy(id: 'gone');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testDestroyReturnsNotFoundForMissingProject()

    /**
     * Test that destroy() returns 404 when the user is not the owner (IDOR prevention — no 403 oracle).
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundForNonOwner(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1', 'user2']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user2');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isOwner')
            ->with(project: $project, uid: 'user2')
            ->willReturn(false);

        $result = $this->controller->destroy(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        // Returns 404 (not 403) to prevent existence oracle.
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testDestroyReturnsNotFoundForNonOwner()

    /**
     * Test that destroy() succeeds when the user is the owner.
     *
     * @return void
     */
    public function testDestroySucceedsForOwner(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('isOwner')
            ->with(project: $project, uid: 'owner1')
            ->willReturn(true);

        $this->projectService->expects($this->once())
            ->method('delete')
            ->with(id: 'p1')
            ->willReturn(true);

        $result = $this->controller->destroy(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: 200, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroySucceedsForOwner()
}//end class
