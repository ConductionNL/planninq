<?php

/**
 * Unit tests for TimeEntryController.
 *
 * Covers the security-critical ownership check on destroy() and the
 * server-side user attribution on create() (SEC-W-001 / SEC-001).
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

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
     * Anonymous mock for OCA\OpenRegister\Service\ObjectService.
     *
     * @var MockObject
     */
    private MockObject $objectService;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request     = $this->createMock(originalClassName: IRequest::class);
        $this->userSession = $this->createMock(originalClassName: IUserSession::class);
        $this->container   = $this->createMock(originalClassName: ContainerInterface::class);
        $this->logger      = $this->createMock(originalClassName: LoggerInterface::class);

        // OCA\OpenRegister\Service\ObjectService is not available in unit-test
        // scope, so we create an anonymous stub with only the methods we need.
        $this->objectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getObject', 'deleteObject', 'saveObject'])
            ->getMock();

        $this->container
            ->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($this->objectService);

        $this->controller = new TimeEntryController(
            request: $this->request,
            userSession: $this->userSession,
            container: $this->container,
            logger: $this->logger,
        );

    }//end setUp()

    // -----------------------------------------------------------------------
    // destroy() — ownership-validated DELETE (SEC-001)
    // -----------------------------------------------------------------------

    /**
     * Unauthenticated call to destroy() must return 401.
     *
     * @return void
     */
    public function testDestroyReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->controller->destroy('some-uuid');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturnsUnauthorizedWhenNotLoggedIn()

    /**
     * Destroy() must return 404 when the entry does not exist.
     *
     * @return void
     */
    public function testDestroyReturnsNotFoundWhenEntryDoesNotExist(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->objectService->method('getObject')->willReturn(null);

        $result = $this->controller->destroy('missing-uuid');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_NOT_FOUND, actual: $result->getStatus());

    }//end testDestroyReturnsNotFoundWhenEntryDoesNotExist()

    /**
     * Destroy() must return 403 when the entry belongs to a different user.
     *
     * @return void
     */
    public function testDestroyReturnsForbiddenWhenEntryOwnedByAnotherUser(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->objectService
            ->method('getObject')
            ->willReturn(['user' => 'bob', 'id' => 'some-uuid']);

        $result = $this->controller->destroy('some-uuid');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
        self::assertArrayHasKey(key: 'error', array: $result->getData());

    }//end testDestroyReturnsForbiddenWhenEntryOwnedByAnotherUser()

    /**
     * Destroy() must call deleteObject and return 200 when the owner matches.
     *
     * @return void
     */
    public function testDestroyDeletesEntryAndReturnsSuccessWhenOwnerMatches(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        $this->objectService
            ->method('getObject')
            ->willReturn(['user' => 'alice', 'id' => 'some-uuid']);

        $this->objectService
            ->expects($this->once())
            ->method('deleteObject');

        $result = $this->controller->destroy('some-uuid');

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
        self::assertTrue(condition: $result->getData()['success']);

    }//end testDestroyDeletesEntryAndReturnsSuccessWhenOwnerMatches()

    // -----------------------------------------------------------------------
    // create() — server-side user attribution (SEC-W-001)
    // -----------------------------------------------------------------------

    /**
     * Unauthenticated call to create() must return 401.
     *
     * @return void
     */
    public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $result->getStatus());

    }//end testCreateReturnsUnauthorizedWhenNotLoggedIn()

    /**
     * Create() must override any client-supplied user field with the
     * authenticated session UID before forwarding to OpenRegister.
     *
     * @return void
     */
    public function testCreateSubstitutesServerSideUserAndReturnsEntry(): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);

        // Simulate a client trying to spoof another user's UID (must be ignored by controller).
        $this->request
            ->method('getParams')
            ->willReturn(
                    [
                        'task'        => 'task-uuid',
                        'user'        => 'mallory',
                        'duration'    => 90,
                        'date'        => '2026-04-07',
                        'description' => 'Some work',
                    ]
                    );

        $savedEntry = [
            'id'          => 'new-entry-uuid',
            'task'        => 'task-uuid',
            'user'        => 'alice',
            'duration'    => 90,
            'date'        => '2026-04-07',
            'description' => 'Some work',
        ];

        $ownershipCallback = static function (array $entry): bool {
            // Ensure the server substituted 'alice', not the client-supplied 'mallory'.
            return $entry['user'] === 'alice';
        };

        $this->objectService
            ->expects($this->once())
            ->method('saveObject')
            ->with(
                register: 'planix',
                schema: 'timeEntry',
                object: $this->callback(callback: $ownershipCallback)
            )
            ->willReturn($savedEntry);

        $result = $this->controller->create();

        self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
        self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
        self::assertSame(expected: 'alice', actual: $result->getData()['user']);

    }//end testCreateSubstitutesServerSideUserAndReturnsEntry()
}//end class
