<?php

/**
 * Unit tests for TimetableController.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Controller\TimetableController;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;

/**
 * Tests the HTTP doors onto the timetable.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-signed-in-users-read-sessions-over-http-only-admins-upsert-req-006
 */
class TimetableControllerTest extends TestCase {

	/**
	 * Mock request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mock user session.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession&MockObject $userSession;

	/**
	 * Mock settings service.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settingsService;

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objectService;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);
		$this->objectService = new InMemoryObjectService();

	}//end setUp()

	/**
	 * Build the controller over the real service and an in-memory OpenRegister.
	 *
	 * @param ContainerInterface|null $container Overrides the container.
	 *
	 * @return TimetableController
	 */
	private function controller(?ContainerInterface $container = null): TimetableController {
		if ($container === null) {
			$container = $this->createMock(originalClassName: ContainerInterface::class);
			$container->method('get')->willReturn($this->objectService);
		}

		return new TimetableController(
			request: $this->request,
			userSession: $this->userSession,
			sessions: new TimetableSessionService(container: $container, logger: new NullLogger()),
			settingsService: $this->settingsService,
		);

	}//end controller()

	/**
	 * Sign a user in.
	 *
	 * @return void
	 */
	private function signIn(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('jan');
		$this->userSession->method('getUser')->willReturn($user);

	}//end signIn()

	/**
	 * A signed-in teacher lists their own week.
	 *
	 * @return void
	 */
	public function testSignedInTeacherListsTheirWeek(): void {
		$this->signIn();
		$this->objectService->seed(
			schema: 'timetableSession',
			id: 's-1',
			data: [
				'externalRef' => 'zm-1',
				'sourceSystem' => 'roster-zermelo',
				'subject' => 'Wiskunde',
				'startsAt' => '2026-09-28T09:00:00+02:00',
				'endsAt' => '2026-09-28T09:50:00+02:00',
				'teacherUserId' => 'jan',
			]
		);

		$response = $this->controller()->sessions(
			teacherUserId: 'jan',
			from: '2026-09-28T00:00:00+02:00',
			to: '2026-10-04T23:59:59+02:00'
		);

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(expected: 1, actual: $response->getData()['total']);
		self::assertSame(expected: 's-1', actual: $response->getData()['results'][0]['id']);
		self::assertSame(expected: '2026-09-28T00:00:00+02:00', actual: $response->getData()['window']['from']);

	}//end testSignedInTeacherListsTheirWeek()

	/**
	 * A read without any filter is a 400.
	 *
	 * @return void
	 */
	public function testReadWithoutFilterIsBadRequest(): void {
		$this->signIn();

		$response = $this->controller()->sessions();

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());

	}//end testReadWithoutFilterIsBadRequest()

	/**
	 * A read without a user is a 401.
	 *
	 * @return void
	 */
	public function testReadWithoutUserIsUnauthorized(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller()->sessions(cohortId: 'c-1');

		self::assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $response->getStatus());

	}//end testReadWithoutUserIsUnauthorized()

	/**
	 * A read without OpenRegister is a 503.
	 *
	 * @return void
	 */
	public function testReadWithoutOpenRegisterIsUnavailable(): void {
		$this->signIn();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('absent'));

		$response = $this->controller(container: $container)->sessions(cohortId: 'c-1');

		self::assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $response->getStatus());

	}//end testReadWithoutOpenRegisterIsUnavailable()

	/**
	 * An admin upsert returns the service result.
	 *
	 * @return void
	 */
	public function testAdminUpsertReturnsTheResult(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturn(true);
		$this->request->method('getParam')->willReturnMap(
			[
				['sourceSystem', null, 'roster-zermelo'],
				[
					'sessions',
					null,
					[
						[
							'externalRef' => 'zm-1',
							'subject' => 'Wiskunde',
							'startsAt' => '2026-09-28T09:00:00+02:00',
							'endsAt' => '2026-09-28T09:50:00+02:00',
						],
					],
				],
			]
		);

		$response = $this->controller()->upsert();

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(expected: 1, actual: $response->getData()['created']);

	}//end testAdminUpsertReturnsTheResult()

	/**
	 * A malformed upsert body is a 400; a non-admin is a 403.
	 *
	 * @return void
	 */
	public function testUpsertRefusesBadBodiesAndNonAdmins(): void {
		$this->settingsService->method('isCurrentUserAdmin')->willReturnOnConsecutiveCalls(true, true, false);
		$this->request->method('getParam')->willReturnOnConsecutiveCalls(
			'',
			[],
			'roster-zermelo',
			['not' => 'a list']
		);

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $this->controller()->upsert()->getStatus());
		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $this->controller()->upsert()->getStatus());
		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $this->controller()->upsert()->getStatus());

	}//end testUpsertRefusesBadBodiesAndNonAdmins()

	/**
	 * The read is open to signed-in users; the upsert is not.
	 *
	 * @return void
	 */
	public function testAuthPostureMatchesTheContract(): void {
		$read = new ReflectionMethod(TimetableController::class, 'sessions');
		self::assertNotEmpty(actual: $read->getAttributes(NoAdminRequired::class));
		self::assertNotEmpty(actual: $read->getAttributes(NoCSRFRequired::class));

		$write = new ReflectionMethod(TimetableController::class, 'upsert');
		self::assertSame(expected: [], actual: $write->getAttributes(NoAdminRequired::class));
		self::assertSame(expected: [], actual: $write->getAttributes(NoCSRFRequired::class));
		self::assertNotEmpty(actual: $write->getAttributes(AuthorizedAdminSetting::class));

	}//end testAuthPostureMatchesTheContract()

	/**
	 * Both routes are registered and point at existing methods.
	 *
	 * @return void
	 */
	public function testRoutesAreRegistered(): void {
		$routes = (string)file_get_contents(__DIR__ . '/../../../appinfo/routes.php');

		self::assertStringContainsString(needle: "'timetable#sessions', 'url' => '/api/timetable/sessions', 'verb' => 'GET'", haystack: $routes);
		self::assertStringContainsString(needle: "'timetable#upsert', 'url' => '/api/timetable/sessions/upsert', 'verb' => 'POST'", haystack: $routes);

	}//end testRoutesAreRegistered()
}//end class
