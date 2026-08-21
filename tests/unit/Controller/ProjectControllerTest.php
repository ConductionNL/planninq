<?php

/**
 * Unit tests for ProjectController.
 *
 * Covers C1 (create policy enforcement), C3 (leaveProject RBAC bypass),
 * the legacy checkCreatePolicy endpoint, SB1 (_rbac:false regression),
 * WF1 (error envelope), and WF2 (owner-leave ownership handoff).
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

// The test double is not a *Test.php file, so PHPUnit's directory testsuite
// never loads it and composer's psr-4 map only covers `lib/`. Require it
// explicitly rather than adding an `autoload-dev` entry, which would change
// composer.json's content hash and make CI's `composer install` complain that
// the lock file is out of date.
require_once __DIR__ . '/../Support/ObjectServiceDouble.php';

use OCA\Planix\Controller\ProjectController;
use OCA\Planix\Service\SettingsService;
use OCA\Planix\Tests\Unit\Support\ObjectServiceDouble;
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
 * Tests for ProjectController.
 */
class ProjectControllerTest extends TestCase {

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
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

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
	public function testCheckCreatePolicyReturnsAllowedWhenPermitted(): void {
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
	public function testCheckCreatePolicyReturnsForbiddenWhenDenied(): void {
		$this->settingsService->expects($this->once())
			->method('canCurrentUserCreateProject')
			->willReturn(false);

		$result = $this->controller->checkCreatePolicy();

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());

	}//end testCheckCreatePolicyReturnsForbiddenWhenDenied()

	// ── create (C1 + SB1) ────────────────────────────────────────────────────

	/**
	 * Create returns 401 when no user is authenticated.
	 *
	 * @return void
	 */
	public function testCreateReturnsUnauthorizedWhenNotAuthenticated(): void {
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
	public function testCreateReturnsForbiddenWhenPolicyDenies(): void {
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
	public function testCreateReturnsServiceUnavailableWhenORUnavailable(): void {
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

	/**
	 * SB1 regression: non-admin user with allow_project_creation='all' can
	 * create a project successfully (201). Verifies that saveObject is called
	 * with _rbac:false so the schema-level "create":["admin"] defence-in-depth
	 * rule does not block the proxy path.
	 *
	 * @return void
	 */
	public function testCreateSucceedsForNonAdminWhenPolicyAllowsAll(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('regularuser');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		// Policy gate passes (allow_project_creation = 'all').
		$this->settingsService->expects($this->once())
			->method('canCurrentUserCreateProject')
			->willReturn(true);

		// Fake saved entity.
		$savedEntity = new class {
			/**
			 * Returns a serialised project stub.
			 *
			 * @return array<string,mixed>
			 */
			public function jsonSerialize(): array {
				return ['id' => 'new-uuid', 'title' => 'My Project', 'owner' => 'regularuser'];
			}//end jsonSerialize()
		};

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->expects($this->once())
			->method('saveObject')
			->willReturn($savedEntity);

		$this->container->expects($this->once())
			->method('get')
			->with('OCA\\OpenRegister\\Service\\ObjectService')
			->willReturn($objectService);

		$this->request->method('getParams')->willReturn(['title' => 'My Project']);

		$result = $this->controller->create();

		self::assertSame(expected: Http::STATUS_CREATED, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'id', array: $result->getData());

	}//end testCreateSucceedsForNonAdminWhenPolicyAllowsAll()

	// ── WF1 error envelope ───────────────────────────────────────────────────

	/**
	 * Create returns 500 for unexpected errors from ObjectService.
	 *
	 * @return void
	 */
	public function testCreateReturnsInternalServerErrorForUnexpectedThrowable(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');

		$this->userSession->method('getUser')->willReturn($user);
		$this->settingsService->method('canCurrentUserCreateProject')->willReturn(true);

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->method('saveObject')
			->willThrowException(new \RuntimeException('Unexpected DB error'));

		$this->container->method('get')->willReturn($objectService);
		$this->request->method('getParams')->willReturn(['title' => 'X']);

		$result = $this->controller->create();

		self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());

	}//end testCreateReturnsInternalServerErrorForUnexpectedThrowable()

	/**
	 * LeaveProject returns 500 for unexpected errors from ObjectService.
	 *
	 * @return void
	 */
	public function testLeaveProjectReturnsInternalServerErrorForUnexpectedThrowable(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('bob');

		$this->userSession->method('getUser')->willReturn($user);

		$entity = new class {
			/**
			 * Returns a project stub with bob as member.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return ['owner' => 'alice', 'members' => ['alice', 'bob']];
			}//end getObject()
		};

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->method('find')->willReturn($entity);
		$objectService->method('saveObject')
			->willThrowException(new \RuntimeException('Unexpected DB error'));

		$this->container->method('get')->willReturn($objectService);

		$result = $this->controller->leaveProject('project-uuid-1');

		self::assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());

	}//end testLeaveProjectReturnsInternalServerErrorForUnexpectedThrowable()

	// ── leaveProject (C3 + WF2) ──────────────────────────────────────────────

	/**
	 * LeaveProject returns 401 when the user is not authenticated.
	 *
	 * @return void
	 */
	public function testLeaveProjectReturnsUnauthorizedWhenNotAuthenticated(): void {
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
	public function testLeaveProjectReturnsServiceUnavailableWhenORUnavailable(): void {
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

	/**
	 * WF2: When the project owner leaves, ownership transfers to the
	 * alphabetically-first remaining member.
	 *
	 * @return void
	 */
	public function testLeaveProjectTransfersOwnershipWhenOwnerLeaves(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');

		$this->userSession->method('getUser')->willReturn($user);

		// Fake entity: alice is owner and member; bob and carol are other members.
		$entity = new class {
			/**
			 * Returns the project stub.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return [
					'id' => 'project-uuid-1',
					'title' => 'Test Project',
					'owner' => 'alice',
					'members' => ['alice', 'bob', 'carol'],
				];
			}//end getObject()
		};

		// Capture saved payload to assert ownership transferred to 'bob' (alphabetically first after 'alice').
		$savedPayload = null;
		$savedEntity = new class {
			/**
			 * Returns a serialised saved project.
			 *
			 * @return array<string,mixed>
			 */
			public function jsonSerialize(): array {
				return ['id' => 'project-uuid-1', 'owner' => 'bob', 'members' => ['bob', 'carol']];
			}//end jsonSerialize()
		};

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->method('find')->willReturn($entity);
		$objectService->expects($this->once())
			->method('saveObject')
			->willReturnCallback(
				function () use ($savedEntity, &$savedPayload) {
					$args = func_get_args();
					$savedPayload = $args[0] ?? null;
					return $savedEntity;
				}
			);

		$this->container->method('get')->willReturn($objectService);

		$result = $this->controller->leaveProject('project-uuid-1');

		self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
		// Owner in the saved payload must be 'bob' (alphabetically first of remaining members).
		self::assertNotNull(actual: $savedPayload, message: 'saveObject must have been called');
		self::assertSame(expected: 'bob', actual: ($savedPayload['owner'] ?? null));
		self::assertNotContains(needle: 'alice', haystack: ($savedPayload['members'] ?? []));

	}//end testLeaveProjectTransfersOwnershipWhenOwnerLeaves()

	/**
	 * WF2: When a non-owner member leaves, the owner field is unchanged.
	 *
	 * @return void
	 */
	public function testLeaveProjectDoesNotChangeOwnerWhenNonOwnerLeaves(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('bob');

		$this->userSession->method('getUser')->willReturn($user);

		$entity = new class {
			/**
			 * Returns the project stub.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return [
					'id' => 'project-uuid-1',
					'title' => 'Test Project',
					'owner' => 'alice',
					'members' => ['alice', 'bob'],
				];
			}//end getObject()
		};

		$savedPayload = null;
		$savedEntity = new class {
			/**
			 * Returns a serialised saved project.
			 *
			 * @return array<string,mixed>
			 */
			public function jsonSerialize(): array {
				return ['id' => 'project-uuid-1', 'owner' => 'alice', 'members' => ['alice']];
			}//end jsonSerialize()
		};

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->method('find')->willReturn($entity);
		$objectService->expects($this->once())
			->method('saveObject')
			->willReturnCallback(
				function () use ($savedEntity, &$savedPayload) {
					$args = func_get_args();
					$savedPayload = $args[0] ?? null;
					return $savedEntity;
				}
			);

		$this->container->method('get')->willReturn($objectService);

		$result = $this->controller->leaveProject('project-uuid-1');

		self::assertSame(expected: Http::STATUS_OK, actual: $result->getStatus());
		self::assertNotNull(actual: $savedPayload);
		// Owner must remain 'alice' — non-owner bob leaving does not change ownership.
		self::assertSame(expected: 'alice', actual: ($savedPayload['owner'] ?? null));
		self::assertNotContains(needle: 'bob', haystack: ($savedPayload['members'] ?? []));

	}//end testLeaveProjectDoesNotChangeOwnerWhenNonOwnerLeaves()

	/**
	 * LeaveProject returns 422 when trying to leave as the last member.
	 *
	 * @return void
	 */
	public function testLeaveProjectRefusesWhenLastMember(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');

		$this->userSession->method('getUser')->willReturn($user);

		$entity = new class {
			/**
			 * Returns the project stub with alice as sole member.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return [
					'id' => 'project-uuid-1',
					'owner' => 'alice',
					'members' => ['alice'],
				];
			}//end getObject()
		};

		$objectService = $this->createMock(originalClassName: ObjectServiceDouble::class);

		$objectService->method('find')->willReturn($entity);
		$objectService->expects($this->never())->method('saveObject');

		$this->container->method('get')->willReturn($objectService);

		$result = $this->controller->leaveProject('project-uuid-1');

		self::assertSame(expected: Http::STATUS_UNPROCESSABLE_ENTITY, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());

	}//end testLeaveProjectRefusesWhenLastMember()
}//end class
