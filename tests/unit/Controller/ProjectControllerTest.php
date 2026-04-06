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
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request        = $this->createMock(originalClassName: IRequest::class);
        $this->projectService = $this->createMock(originalClassName: ProjectService::class);

        $this->controller = new ProjectController(
            request: $this->request,
            projectService: $this->projectService,
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
     * Test that show() returns 404 when the project does not exist.
     *
     * @return void
     */
    public function testShowReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'nonexistent')
            ->willReturn(null);

        $result = $this->controller->show(id: 'nonexistent');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testShowReturnsNotFoundForMissingProject()

    /**
     * Test that show() returns 403 when the user is not a member.
     *
     * @return void
     */
    public function testShowReturnsForbiddenForNonMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('other-user');

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'other-user')
            ->willReturn(false);

        $result = $this->controller->show(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testShowReturnsForbiddenForNonMember()

    /**
     * Test that show() returns the project when the user is a member.
     *
     * @return void
     */
    public function testShowReturnsProjectForMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['user1']];

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user1');

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
     * Test that create() returns 400 when title is missing.
     *
     * @return void
     */
    public function testCreateReturnsBadRequestWithoutTitle(): void
    {
        $this->request->expects($this->once())
            ->method('getParams')
            ->willReturn(['description' => 'no title here']);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsBadRequestWithoutTitle()

    /**
     * Test that create() returns 201 with a valid project.
     *
     * @return void
     */
    public function testCreateReturnsCreatedProject(): void
    {
        $params  = ['title' => 'New Project', 'description' => 'A test', 'color' => '#ff0000'];
        $created = ['id' => 'p-new', 'title' => 'New Project', 'members' => ['user1']];

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
     * Test that update() returns 404 for a non-existent project.
     *
     * @return void
     */
    public function testUpdateReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'missing')
            ->willReturn(null);

        $result = $this->controller->update(id: 'missing');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testUpdateReturnsNotFoundForMissingProject()

    /**
     * Test that update() returns 403 when the user is not a member.
     *
     * @return void
     */
    public function testUpdateReturnsForbiddenForNonMember(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('other-user');

        $this->projectService->expects($this->once())
            ->method('isMember')
            ->with(project: $project, uid: 'other-user')
            ->willReturn(false);

        $result = $this->controller->update(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testUpdateReturnsForbiddenForNonMember()

    /**
     * Test that destroy() returns 404 for a non-existent project.
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundForMissingProject(): void
    {
        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'gone')
            ->willReturn(null);

        $result = $this->controller->destroy(id: 'gone');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testDestroyReturnsNotFoundForMissingProject()

    /**
     * Test that destroy() returns 403 when the user is not the owner.
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenForNonOwner(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1', 'user2']];

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('user2');

        $this->projectService->expects($this->once())
            ->method('isOwner')
            ->with(project: $project, uid: 'user2')
            ->willReturn(false);

        $result = $this->controller->destroy(id: 'p1');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());

    }//end testDestroyReturnsForbiddenForNonOwner()

    /**
     * Test that destroy() succeeds when the user is the owner.
     *
     * @return void
     */
    public function testDestroySucceedsForOwner(): void
    {
        $project = ['id' => 'p1', 'title' => 'Alpha', 'members' => ['owner1']];

        $this->projectService->expects($this->once())
            ->method('find')
            ->with(id: 'p1')
            ->willReturn($project);

        $this->projectService->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn('owner1');

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
