<?php

/**
 * Unit tests for TimeEntryService.
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

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Service;

use OCA\Planix\Service\TimeEntryService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for TimeEntryService.
 */
class TimeEntryServiceTest extends TestCase
{

    /**
     * The service under test.
     *
     * @var TimeEntryService
     */
    private TimeEntryService $service;

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

        $this->service = new TimeEntryService(
            container: $this->container,
            userSession: $this->userSession,
            logger: $this->logger,
        );

    }//end setUp()

    /**
     * Test getCurrentUserId() returns UID when user is logged in.
     *
     * @return void
     */
    public function testGetCurrentUserIdReturnsUidWhenLoggedIn(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('testuser');

        $this->userSession->method('getUser')->willReturn($user);

        self::assertSame(expected: 'testuser', actual: $this->service->getCurrentUserId());

    }//end testGetCurrentUserIdReturnsUidWhenLoggedIn()

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
     * Test createTimeEntry() throws when taskId is missing.
     *
     * @return void
     */
    public function testCreateTimeEntryThrowsWhenTaskIdMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('taskId is required.');

        $this->service->createTimeEntry([
            'duration' => 60,
            'date'     => '2026-04-01',
        ]);

    }//end testCreateTimeEntryThrowsWhenTaskIdMissing()

    /**
     * Test createTimeEntry() throws when duration is zero.
     *
     * @return void
     */
    public function testCreateTimeEntryThrowsWhenDurationIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duration must be greater than 0.');

        $this->service->createTimeEntry([
            'taskId'   => 'task-uuid-1',
            'duration' => 0,
            'date'     => '2026-04-01',
        ]);

    }//end testCreateTimeEntryThrowsWhenDurationIsZero()

    /**
     * Test createTimeEntry() throws when date is missing.
     *
     * @return void
     */
    public function testCreateTimeEntryThrowsWhenDateMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('date is required.');

        $this->service->createTimeEntry([
            'taskId'   => 'task-uuid-1',
            'duration' => 60,
        ]);

    }//end testCreateTimeEntryThrowsWhenDateMissing()

    /**
     * Test createTimeEntry() throws when duration is negative.
     *
     * @return void
     */
    public function testCreateTimeEntryThrowsWhenDurationIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duration must be greater than 0.');

        $this->service->createTimeEntry([
            'taskId'   => 'task-uuid-1',
            'duration' => -5,
            'date'     => '2026-04-01',
        ]);

    }//end testCreateTimeEntryThrowsWhenDurationIsNegative()
}//end class
