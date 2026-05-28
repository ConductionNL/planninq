<?php

/**
 * Unit tests for ProjectController.
 *
 * Covers C1 (create policy enforcement), C3 (leaveProject RBAC bypass),
 * and the legacy checkCreatePolicy endpoint.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Controller;

use OCA\Planix\Controller\ProjectController;
use OCA\Planix\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for ProjectController.
 */
class ProjectControllerTest extends TestCase
{

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock SettingsService.
     *
     * @var SettingsService&MockObject
     */
    private SettingsService&MockObject $settingsService;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;

    /**
     * Mock ContainerInterface.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * Mock LoggerInterface.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * The controller under test.
     *
     * @var ProjectController
     */
    private ProjectController $controller;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(originalClassName: IRequest::class);
        $this->settingsService = $this->createMock(originalClassName: SettingsService::class);
        $this->userSession     = $this->createMock(originalClassName: IUserSession::class);
        $this->container       = $this->createMock(originalClassName: ContainerInterface::class);
        $this->logger          = $this->createMock(originalClassName: LoggerInterface::class);

        $this->controller = new ProjectController(
            request: $this->request,
            settingsService: $this->settingsService,
            userSession: $this->userSession,
            container: $this->container,
            logger: $this->logger,
        );

    }//end setUp()

    // ── checkCreatePolicy ────────────────────────────────────────────────────

    /**
     * CheckCreatePolicy returns 200 when the user is allowed to create.
     *
     * @return void
     */
    public function testCheckCreatePolicyReturnsAllowedWhenPermitted(): void
    {
        $this->settingsService->expects($this->once())
            ->method('canCurrentUserCreateProject')
            ->willReturn(true);

        $result = $this->controller->checkCreatePolicy();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'allowed', array: $result->getData());
        self::assertTrue(condition: $result->getData()['allowed']);

    }//end testCheckCreatePolicyReturnsAllowedWhenPermitted()

    /**
     * CheckCreatePolicy returns 403 when the policy denies creation.
     *
     * @return void
     */
    public function testCheckCreatePolicyReturnsForbiddenWhenDenied(): void
    {
        $this->settingsService->expects($this->once())
            ->method('canCurrentUserCreateProject')
            ->willReturn(false);

        $result = $this->controller->checkCreatePolicy();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCheckCreatePolicyReturnsForbiddenWhenDenied()

    // ── create (C1) ──────────────────────────────────────────────────────────

    /**
     * Create returns 401 when no user is authenticated.
     *
     * @return void
     */
    public function testCreateReturnsUnauthorizedWhenNotAuthenticated(): void
    {
        $this->userSession->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $result = $this->controller->create();

        self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $result->getStatus());

    }//end testCreateReturnsUnauthorizedWhenNotAuthenticated()

    /**
     * Create returns 403 when the policy forbids the current user from creating.
     *
     * @return void
     */
    public function testCreateReturnsForbiddenWhenPolicyDenies(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');

        $this->userSession->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->settingsService->expects($this->once())
            ->method('canCurrentUserCreateProject')
            ->willReturn(false);

        // ObjectService must NOT be called.
        $this->container->expects($this->never())->method('get');

        $result = $this->controller->create();

        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testCreateReturnsForbiddenWhenPolicyDenies()

    /**
     * Create returns 503 when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testCreateReturnsServiceUnavailableWhenORUnavailable(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');

        $this->userSession->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->settingsService->expects($this->once())
            ->method('canCurrentUserCreateProject')
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Service not found'));

        $result = $this->controller->create();

        self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $result->getStatus());

    }//end testCreateReturnsServiceUnavailableWhenORUnavailable()

    // ── leaveProject (C3) ────────────────────────────────────────────────────

    /**
     * LeaveProject returns 401 when the user is not authenticated.
     *
     * @return void
     */
    public function testLeaveProjectReturnsUnauthorizedWhenNotAuthenticated(): void
    {
        $this->userSession->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $result = $this->controller->leaveProject('project-uuid-1');

        self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $result->getStatus());

    }//end testLeaveProjectReturnsUnauthorizedWhenNotAuthenticated()

    /**
     * LeaveProject returns 503 when OpenRegister is unavailable.
     *
     * @return void
     */
    public function testLeaveProjectReturnsServiceUnavailableWhenORUnavailable(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('bob');

        $this->userSession->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->container->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Service not found'));

        $result = $this->controller->leaveProject('project-uuid-1');

        self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $result->getStatus());
    }//end testLeaveProjectReturnsServiceUnavailableWhenORUnavailable()
}//end class
