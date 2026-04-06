<?php

/**
 * Unit tests for ProjectService.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Service
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

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\Service\ProjectService;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for ProjectService.
 */
class ProjectServiceTest extends TestCase
{

    /**
     * The service under test.
     *
     * @var ProjectService
     */
    private ProjectService $service;

    /**
     * Mock ContainerInterface.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Mock LoggerInterface.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Mock IAppConfig.
     *
     * @var IAppConfig&MockObject
     */
    private IAppConfig&MockObject $appConfig;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container   = $this->createMock(originalClassName: ContainerInterface::class);
        $this->userSession = $this->createMock(originalClassName: IUserSession::class);
        $this->logger      = $this->createMock(originalClassName: LoggerInterface::class);
        $this->appConfig   = $this->createMock(originalClassName: IAppConfig::class);

        $this->service = new ProjectService(
            container: $this->container,
            userSession: $this->userSession,
            logger: $this->logger,
            appConfig: $this->appConfig,
        );

    }//end setUp()

    /**
     * Test isMember() returns true when the user is in the members array.
     *
     * @return void
     */
    public function testIsMemberReturnsTrueForMember(): void
    {
        $project = ['id' => 'p1', 'members' => ['alice', 'bob']];

        self::assertTrue(condition: $this->service->isMember(project: $project, uid: 'alice'));
        self::assertTrue(condition: $this->service->isMember(project: $project, uid: 'bob'));

    }//end testIsMemberReturnsTrueForMember()

    /**
     * Test isMember() returns false when the user is not in the members array.
     *
     * @return void
     */
    public function testIsMemberReturnsFalseForNonMember(): void
    {
        $project = ['id' => 'p1', 'members' => ['alice']];

        self::assertFalse(condition: $this->service->isMember(project: $project, uid: 'charlie'));

    }//end testIsMemberReturnsFalseForNonMember()

    /**
     * Test isMember() returns false when members key is missing.
     *
     * @return void
     */
    public function testIsMemberReturnsFalseWhenMembersKeyMissing(): void
    {
        $project = ['id' => 'p1'];

        self::assertFalse(condition: $this->service->isMember(project: $project, uid: 'alice'));

    }//end testIsMemberReturnsFalseWhenMembersKeyMissing()

    /**
     * Test isOwner() returns true when the user is the first member.
     *
     * @return void
     */
    public function testIsOwnerReturnsTrueForFirstMember(): void
    {
        $project = ['id' => 'p1', 'members' => ['alice', 'bob']];

        self::assertTrue(condition: $this->service->isOwner(project: $project, uid: 'alice'));

    }//end testIsOwnerReturnsTrueForFirstMember()

    /**
     * Test isOwner() returns false when the user is not the first member.
     *
     * @return void
     */
    public function testIsOwnerReturnsFalseForNonFirstMember(): void
    {
        $project = ['id' => 'p1', 'members' => ['alice', 'bob']];

        self::assertFalse(condition: $this->service->isOwner(project: $project, uid: 'bob'));

    }//end testIsOwnerReturnsFalseForNonFirstMember()

    /**
     * Test getCurrentUserId() returns the UID of the logged-in user.
     *
     * @return void
     */
    public function testGetCurrentUserIdReturnsUid(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('testuser');

        $this->userSession->method('getUser')->willReturn($user);

        self::assertSame(expected: 'testuser', actual: $this->service->getCurrentUserId());

    }//end testGetCurrentUserIdReturnsUid()

    /**
     * Test getCurrentUserId() returns null when no user is logged in.
     *
     * @return void
     */
    public function testGetCurrentUserIdReturnsNullWithoutUser(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        self::assertNull(actual: $this->service->getCurrentUserId());

    }//end testGetCurrentUserIdReturnsNullWithoutUser()

    /**
     * Test isOwner() returns false when the members key is absent.
     *
     * @return void
     */
    public function testIsOwnerReturnsFalseWhenMembersKeyMissing(): void
    {
        $project = ['id' => 'p1'];

        self::assertFalse(condition: $this->service->isOwner(project: $project, uid: 'alice'));

    }//end testIsOwnerReturnsFalseWhenMembersKeyMissing()

    /**
     * Test findAll() returns an empty array when no user is authenticated.
     *
     * @return void
     */
    public function testFindAllReturnsEmptyArrayWhenUnauthenticated(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        // GetObjectService must NOT be called — no database query for unauthenticated callers.
        $this->container->expects($this->never())->method('get');

        $result = $this->service->findAll();

        self::assertSame(expected: [], actual: $result);

    }//end testFindAllReturnsEmptyArrayWhenUnauthenticated()

    /**
     * Test findAll() returns only projects the user is a member of and filters out non-active projects.
     *
     * @return void
     */
    public function testFindAllFiltersToMemberActiveProjects(): void
    {
        $user = $this->createMock(originalClassName: \OCP\IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $allProjects = [
            ['id' => 'p1', 'title' => 'My Active',   'members' => ['alice'],       'status' => 'active'],
            ['id' => 'p2', 'title' => 'Not Member',   'members' => ['bob'],         'status' => 'active'],
            ['id' => 'p3', 'title' => 'My Archived',  'members' => ['alice'],       'status' => 'archived'],
            ['id' => 'p4', 'title' => 'My Completed', 'members' => ['alice'],       'status' => 'completed'],
            ['id' => 'p5', 'title' => 'No Status',    'members' => ['alice']],
        ];

        $objectService = $this->createMock(originalClassName: \stdClass::class);
        $objectService->method('findAll')->willReturn($allProjects);

        $this->container->method('get')->willReturn($objectService);

        $result = $this->service->findAll();

        // Only p1 (alice + active) and p5 (alice + no status defaults to active) should be returned.
        self::assertCount(expectedCount: 2, haystack: $result);
        $ids = array_column($result, 'id');
        self::assertContains(needle: 'p1', haystack: $ids);
        self::assertContains(needle: 'p5', haystack: $ids);

    }//end testFindAllFiltersToMemberActiveProjects()

    /**
     * Test create() throws RuntimeException when no user is authenticated.
     *
     * @return void
     */
    public function testCreateThrowsWhenUnauthenticated(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: 'Cannot create project: no authenticated user.');

        $this->service->create(data: ['title' => 'Test']);

    }//end testCreateThrowsWhenUnauthenticated()
}//end class
