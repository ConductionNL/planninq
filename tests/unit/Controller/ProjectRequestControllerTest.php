<?php

/**
 * Tests for project requests on ProjectController::create (projects-lifecycle-policy, section 3).
 *
 * The request is saved through the in-memory ObjectService and validated
 * against the real project schema fragment.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/InMemoryLockingProvider.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Controller\ProjectController;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryLockingProvider;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectRequestControllerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private BoardColumnService&MockObject $columns;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->columns = $this->createMock(originalClassName: BoardColumnService::class);
	}//end setUp()

	private function controller(bool $mayCreate, bool $mayRequest, array $params): ProjectController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn($params);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('rik');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('canCurrentUserCreateProject')->willReturn($mayCreate);
		$settings->method('canCurrentUserRequestProject')->willReturn($mayRequest);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectController(
			request: $request,
			settingsService: $settings,
			userSession: $session,
			container: $this->container(),
			logger: $logger,
			boardColumns: $this->columns,
			keys: new WorkItemKeyService(membership: $this->membershipService(), container: $this->container(), locking: new InMemoryLockingProvider(), logger: $logger),
		);
	}//end controller()

	/**
	 * A user who may create gets an active project with its columns, whatever status the client sent.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	public function testAnAllowedCallerCreatesAnActiveProject(): void {
		$this->columns->expects($this->once())->method('createDefaultColumns');
		$response = $this->controller(mayCreate: true, mayRequest: false, params: ['title' => 'Portaal', 'status' => 'active'])->create();

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		self::assertSame('active', $this->objects->saves[0]['object']['status']);
	}//end testAnAllowedCallerCreatesAnActiveProject()

	/**
	 * Scenario "A requester fills in the guided form": the project is stored as a request of its own, without columns.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	public function testARequesterStoresARequest(): void {
		$this->columns->expects($this->never())->method('createDefaultColumns');
		$params   = ['title' => 'Portaal', 'description' => 'A portal for residents', 'requestReason' => 'Residents ask for it', 'startDate' => '2027-01-04', 'status' => 'active', 'reviewedBy' => 'rik', 'members' => ['zoe']];
		$response = $this->controller(mayCreate: false, mayRequest: true, params: $params)->create();

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		$saved = $this->objects->saves[0]['object'];
		self::assertSame('requested', $saved['status']);
		self::assertSame('rik', $saved['owner']);
		self::assertSame(['rik'], $saved['members'], 'only the requester sees the request');
		self::assertArrayNotHasKey('reviewedBy', $saved, 'a requester cannot fill in the review');
		self::assertSame('Residents ask for it', $saved['requestReason']);
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: $saved));
	}//end testARequesterStoresARequest()

	/**
	 * Without the right to create or to request, the answer is 403 and nothing is saved.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.1
	 */
	public function testARefusedCallerGets403(): void {
		$response = $this->controller(mayCreate: false, mayRequest: false, params: ['title' => 'Portaal'])->create();

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertSame([], $this->objects->saves);
	}//end testARefusedCallerGets403()
}//end class
