<?php

/**
 * Tests for importing a Microsoft Project plan into a project (integration-msproject-import, tasks 2.1 and 2.2).
 *
 * The controller, the import service, the dependency service and the
 * membership service are the real classes; only OpenRegister's ObjectService
 * is the in-memory double with OpenRegister's method names. Every payload the
 * import writes is validated against the real register schema.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Controller\ProjectImportController;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\DependencyService;
use OCA\Planninq\Service\MsProjectImportService;
use OCA\Planninq\Service\MsProjectPlanMapper;
use OCA\Planninq\Service\MsProjectPlanParser;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\ProjectImportController
 * @covers \OCA\Planninq\Service\MsProjectImportService
 * @uses \OCA\Planninq\Exception\MsProjectImportException
 * @uses \OCA\Planninq\Service\DependencyGraph
 * @uses \OCA\Planninq\Service\DependencyRepository
 * @uses \OCA\Planninq\Service\DependencyService
 * @uses \OCA\Planninq\Service\MsProjectPlanMapper
 * @uses \OCA\Planninq\Service\MsProjectPlanParser
 * @uses \OCA\Planninq\Service\ProjectMembershipService
 */
class ProjectImportControllerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	/**
	 * The project the plan is imported into.
	 *
	 * @var string
	 */
	private const PROJECT = '6f1d6c0e-1b2a-4c3d-8e9f-0a1b2c3d4e5f';

	/**
	 * The uploaded file's path.
	 *
	 * @var string
	 */
	private string $upload = '';

	/**
	 * Seed the project: owner olga, member mo.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', self::PROJECT, ['title' => 'Renovatie stadhuis', 'owner' => 'olga', 'members' => ['olga', 'mo']]);
		$this->useFile(xml: $this->fixture());
	}//end setUp()

	/**
	 * Remove the uploaded copy.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->upload !== '' && file_exists($this->upload) === true) {
			unlink($this->upload);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * The fixture plan.
	 *
	 * @return string
	 */
	private function fixture(): string {
		return (string)file_get_contents(__DIR__ . '/../../fixtures/msproject/contractor-plan.xml');
	}//end fixture()

	/**
	 * Upload this XML as the request's file.
	 *
	 * @param string $xml The plan.
	 *
	 * @return void
	 */
	private function useFile(string $xml): void {
		if ($this->upload === '') {
			$this->upload = (string)tempnam(sys_get_temp_dir(), 'pln-mspdi');
		}

		file_put_contents($this->upload, $xml);
	}//end useFile()

	/**
	 * The controller, acting as this user.
	 *
	 * @param string|null $uid   The caller, null for none.
	 * @param bool        $admin Whether the caller is an admin.
	 *
	 * @return ProjectImportController
	 */
	private function controller(?string $uid, bool $admin = false): ProjectImportController {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);

		$request = $this->createMock(IRequest::class);
		$upload  = $this->upload;
		$request->method('getUploadedFile')->willReturnCallback(
			static fn (string $key): ?array => ($key === 'file')
				? ['name' => 'Renovatie stadhuis.xml', 'tmp_name' => $upload, 'size' => filesize($upload), 'error' => UPLOAD_ERR_OK]
				: null
		);

		$logger     = $this->createMock(LoggerInterface::class);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$repository = new DependencyRepository(container: $this->container(), logger: $logger, appManager: $appManager);

		$service = new MsProjectImportService(
			parser: new MsProjectPlanParser(),
			mapper: new MsProjectPlanMapper(),
			repository: $repository,
			dependencies: new DependencyService(repository: $repository, graph: new DependencyGraph(), userSession: $session, logger: $logger),
			logger: $logger
		);

		return new ProjectImportController(
			request: $request,
			importService: $service,
			membership: $this->membershipService(),
			userSession: $session,
			groupManager: $groups,
			logger: $logger
		);
	}//end controller()

	/**
	 * Stored rows of one schema whose title is this.
	 *
	 * @param string $schema The schema slug.
	 * @param string $title  The title.
	 *
	 * @return array<string,array<string,mixed>> Rows by uuid.
	 */
	private function titled(string $schema, string $title): array {
		return array_filter(($this->objects->rows[$schema] ?? []), static fn (array $row): bool => ($row['title'] ?? null) === $title);
	}//end titled()

	/**
	 * The one stored row with this title; its uuid and data.
	 *
	 * @param string $schema The schema slug.
	 * @param string $title  The title.
	 *
	 * @return array{0:string,1:array<string,mixed>}
	 */
	private function one(string $schema, string $title): array {
		$rows = $this->titled(schema: $schema, title: $title);
		self::assertCount(1, $rows, 'exactly one ' . $schema . ' called ' . $title);
		return [(string)array_key_first($rows), reset($rows)];
	}//end one()

	/**
	 * A member who is not the owner, an outsider and an anonymous caller are refused and nothing is written.
	 *
	 * @return void
	 */
	public function testMemberWhoIsNotOwnerIsRefused(): void {
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'mo')->preview(projectId: self::PROJECT)->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'mo')->import(projectId: self::PROJECT)->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'eve')->import(projectId: self::PROJECT)->getStatus());
		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(uid: null)->import(projectId: self::PROJECT)->getStatus());
		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller(uid: 'olga')->import(projectId: 'no-such-project')->getStatus());
		self::assertSame([], $this->objects->saves);

		// An admin who is not on the project may import it.
		self::assertSame(Http::STATUS_OK, $this->controller(uid: 'root', admin: true)->import(projectId: self::PROJECT)->getStatus());
		self::assertCount(1, $this->titled(schema: 'task', title: 'Fundering'));
		self::assertCount(3, $this->objects->rows['dependency']);
	}//end testMemberWhoIsNotOwnerIsRefused()

	/**
	 * The preview reports counts, samples and losses, and writes nothing.
	 *
	 * @return void
	 */
	public function testPreviewWritesNothing(): void {
		$response = $this->controller(uid: 'olga')->preview(projectId: self::PROJECT);
		$body     = $response->getData();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['phases' => 2, 'tasks' => 5, 'subtasks' => 2, 'milestones' => 1, 'links' => 3], $body['counts']);
		self::assertSame(2, $body['losses']['resources']);
		self::assertSame(['title' => 'Fundering', 'kind' => 'task', 'startDate' => '2027-03-01', 'dueDate' => '2027-03-12'], $body['sample'][0]);
		self::assertSame(0, $body['updates']);
		self::assertSame([], $body['missing']);
		self::assertSame([], $this->objects->saves);

		$this->useFile(xml: "\xD0\xCF\x11\xE0" . str_repeat("\0", 32));
		$refused = $this->controller(uid: 'olga')->preview(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $refused->getStatus());
		self::assertSame('mpp', $refused->getData()['reason']);
	}//end testPreviewWritesNothing()

	/**
	 * The import writes phases, tasks, sub-tasks and links, each valid for the register.
	 *
	 * @return void
	 */
	public function testCommitCreatesPhasesTasksAndLinks(): void {
		$response = $this->controller(uid: 'olga')->import(projectId: self::PROJECT);
		$body     = $response->getData();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['phases' => 2, 'tasks' => 6, 'subtasks' => 2, 'links' => 3], $body['created']);
		self::assertSame(0, $body['refusedLinks']);

		[$ruwbouw, $phase] = $this->one(schema: 'projectPhase', title: 'Ruwbouw');
		self::assertSame(self::PROJECT, $phase['project']);
		self::assertSame('1', $phase['metadata']['msProjectUid']);

		[$fundering, $task] = $this->one(schema: 'task', title: 'Fundering');
		[$metselwerk]       = $this->one(schema: 'task', title: 'Metselwerk');
		[$kozijnen]         = $this->one(schema: 'task', title: 'Kozijnen');
		self::assertSame($ruwbouw, $task['phase']);
		self::assertSame(self::PROJECT, $task['project']);
		self::assertSame('2027-03-01', $task['startDate']);
		self::assertSame('2027-03-12', $task['dueDate']);
		self::assertSame($kozijnen, $this->one(schema: 'task', title: 'Maatvoering')[1]['parent']);
		self::assertArrayNotHasKey('phase', $this->one(schema: 'task', title: 'Asbestsanering')[1]);

		$edges = array_values($this->objects->rows['dependency']);
		self::assertContains(['blocker' => $fundering, 'blocked' => $metselwerk, 'type' => 'blocks'], $edges);
		self::assertContains(['blocker' => $metselwerk, 'blocked' => $this->one(schema: 'task', title: 'Oplevering ruwbouw')[0], 'type' => 'relates'], $edges);

		// Phases first, then tasks, then sub-tasks, then links; every payload valid.
		$order = array_map(static fn (array $save): string => (string)$save['schema'], $this->objects->saves);
		self::assertSame(['projectPhase', 'projectPhase'], array_slice($order, 0, 2));
		self::assertSame(['dependency', 'dependency', 'dependency'], array_slice($order, -3));
		foreach ($this->objects->saves as $save) {
			self::assertSame([], $this->registerSchemaErrors(slug: (string)$save['schema'], payload: (array)$save['object']), (string)$save['schema']);
			if ($save['schema'] !== 'dependency') {
				self::assertTrue($save['_rbac'], 'phases and tasks are written with the caller\'s rights');
			}
		}
	}//end testCommitCreatesPhasesTasksAndLinks()

	/**
	 * A newer plan moves a task instead of duplicating it.
	 *
	 * @return void
	 */
	public function testReimportUpdatesMatchedTasks(): void {
		$this->controller(uid: 'olga')->import(projectId: self::PROJECT);
		[$before] = $this->one(schema: 'task', title: 'Fundering');

		$this->useFile(xml: str_replace(
			['<Start>2027-03-01T08:00:00</Start>
			<Finish>2027-03-12T17:00:00</Finish>'],
			['<Start>2027-03-08T08:00:00</Start>
			<Finish>2027-03-19T17:00:00</Finish>'],
			$this->fixture()
		));
		self::assertSame(10, $this->controller(uid: 'olga')->preview(projectId: self::PROJECT)->getData()['updates']);
		$body = $this->controller(uid: 'olga')->import(projectId: self::PROJECT)->getData();

		[$after, $task] = $this->one(schema: 'task', title: 'Fundering');
		self::assertSame($before, $after);
		self::assertSame('2027-03-08', $task['startDate']);
		self::assertSame('2027-03-19', $task['dueDate']);
		self::assertSame(['phases' => 0, 'tasks' => 0, 'subtasks' => 0, 'links' => 0], $body['created']);
		self::assertSame(['phases' => 2, 'tasks' => 6, 'subtasks' => 2], $body['updated']);
		self::assertCount(3, $this->objects->rows['dependency']);
		self::assertCount(8, $this->objects->rows['task']);
	}//end testReimportUpdatesMatchedTasks()

	/**
	 * A task dropped from the plan is listed, not deleted.
	 *
	 * @return void
	 */
	public function testReimportListsMissingTasks(): void {
		$this->controller(uid: 'olga')->import(projectId: self::PROJECT);

		$withoutAsbest = preg_replace('#<Task>\s*<UID>10</UID>.*?</Task>#s', '', $this->fixture());
		$this->useFile(xml: (string)$withoutAsbest);
		self::assertSame(['Asbestsanering'], $this->controller(uid: 'olga')->preview(projectId: self::PROJECT)->getData()['missing']);
		$body = $this->controller(uid: 'olga')->import(projectId: self::PROJECT)->getData();

		self::assertSame(['Asbestsanering'], $body['missing']);
		$this->one(schema: 'task', title: 'Asbestsanering');
	}//end testReimportListsMissingTasks()

	/**
	 * A failed import says how far it got; running it again completes it without duplicates.
	 *
	 * @return void
	 */
	public function testRerunAfterFailureCreatesNoDuplicates(): void {
		$failing = new class extends InMemoryObjectService {
			public bool $fail = true;
			// phpcs:ignore
			public function saveObject(array|object $object, ?array $extend = [], mixed $register = null, mixed $schema = null, ?string $uuid = null, bool $_rbac = true, bool $_multitenancy = true, bool $silent = false, bool $_validation = true): ?object {
				if ($this->fail === true && (((array)$object)['title'] ?? null) === 'Schilderwerk') {
					throw new \RuntimeException('database went away');
				}

				return parent::saveObject(object: $object, extend: $extend, register: $register, schema: $schema, uuid: $uuid, _rbac: $_rbac, _multitenancy: $_multitenancy, silent: $silent, _validation: $_validation);
			}
		};
		$failing->rows    = $this->objects->rows;
		$this->objects    = $failing;

		$response = $this->controller(uid: 'olga')->import(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		self::assertSame(2, $response->getData()['created']['phases']);
		self::assertArrayHasKey('error', $response->getData());

		$failing->fail = false;
		$rerun = $this->controller(uid: 'olga')->import(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_OK, $rerun->getStatus());
		self::assertCount(2, $this->objects->rows['projectPhase']);
		self::assertCount(8, $this->objects->rows['task']);
		self::assertCount(3, $this->objects->rows['dependency']);
		foreach (['Fundering', 'Schilderwerk', 'Maatvoering'] as $title) {
			$this->one(schema: 'task', title: $title);
		}
	}//end testRerunAfterFailureCreatesNoDuplicates()
}//end class
