<?php

/**
 * Tests for the project key on ProjectController: the create check and the availability check.
 *
 * Runs the real WorkItemKeyService over the real ProjectMembershipService and
 * an in-memory ObjectService holding a project the caller cannot read.
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

use OCA\Planninq\Controller\ProjectController;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryLockingProvider;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectKeyControllerTest extends TestCase {
	use MembershipFixture;

	/**
	 * Request parameters.
	 *
	 * @var array<string,mixed>
	 */
	private array $params = [];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'p-secret', ['title' => 'Secret project', 'status' => 'active', 'owner' => 'zoe', 'members' => ['zoe'], 'key' => 'VERG']);
	}//end setUp()

	private function controller(bool $loggedIn = true): ProjectController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturnCallback(fn (): array => $this->params);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('bob');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($loggedIn === true ? $user : null);

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('canCurrentUserCreateProject')->willReturn(true);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new ProjectController(
			request: $request,
			settingsService: $settings,
			userSession: $session,
			container: $this->container(),
			logger: $logger,
			boardColumns: $this->createMock(originalClassName: BoardColumnService::class),
			keys: new WorkItemKeyService(membership: $this->membershipService(), container: $this->container(), locking: new InMemoryLockingProvider(), logger: $logger),
		);
	}//end controller()

	/**
	 * Scenario "A used key is refused": 409, the message names no project, nothing saved.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testCreateRefusesAKeyAnotherProjectUses(): void {
		$this->params = ['title' => 'Vergunningen Noord', 'key' => 'verg'];
		$response = $this->controller()->create();

		self::assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		self::assertSame('This key is already used by another project.', $response->getData()['error']);
		self::assertSame('planninq-project-key-used', $response->getData()['code']);
		self::assertStringNotContainsString('Secret', (string)json_encode($response->getData()));
		self::assertSame([], $this->objects->saves);
	}//end testCreateRefusesAKeyAnotherProjectUses()

	/**
	 * A key in the wrong format is refused with 400 before anything is saved.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testCreateRefusesABadKey(): void {
		$this->params = ['title' => 'X', 'key' => '1X'];
		$response = $this->controller()->create();

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame('planninq-project-key-format', $response->getData()['code']);
		self::assertSame([], $this->objects->saves);
	}//end testCreateRefusesABadKey()

	/**
	 * Scenario "Create a project with a key": the key is stored uppercase.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testCreateStoresTheKeyUppercase(): void {
		$this->params = ['title' => 'Handhaving', 'key' => ' hand '];
		$response = $this->controller()->create();

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		self::assertSame('HAND', $this->objects->saves[0]['object']['key']);
	}//end testCreateStoresTheKeyUppercase()

	/**
	 * The availability check answers only valid and available, never whose key it is.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
	 */
	public function testKeyAvailableAnswersOnlyTrueOrFalse(): void {
		self::assertSame(['valid' => true, 'available' => false], $this->controller()->keyAvailable(key: 'verg')->getData());
		self::assertSame(['valid' => true, 'available' => true], $this->controller()->keyAvailable(key: 'HAND')->getData());
		self::assertSame(['valid' => false, 'available' => false], $this->controller()->keyAvailable(key: 'H')->getData());
		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(loggedIn: false)->keyAvailable(key: 'HAND')->getStatus());
	}//end testKeyAvailableAnswersOnlyTrueOrFalse()
}//end class
