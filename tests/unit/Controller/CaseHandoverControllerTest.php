<?php

/**
 * Tests for handing a project over to its case (integration-case-bridge, tasks 3.1 and 3.2).
 *
 * The controller, the handover service and the membership service are the
 * real classes. OpenRegister's ObjectService and FileService are in-memory
 * doubles with OpenRegister's method and parameter names; the files are real
 * OCP\Files\File mocks. The project payload the handover writes is validated
 * against the real register schema.
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
require_once __DIR__ . '/../Support/FileServiceDouble.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Controller\CaseHandoverController;
use OCA\Planninq\Service\CaseHandoverService;
use OCA\Planninq\Tests\Unit\Support\FileServiceDouble;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Controller\CaseHandoverController
 * @covers \OCA\Planninq\Service\CaseHandoverService
 */
class CaseHandoverControllerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const PROJECT = '6f1d6c0e-1b2a-4c3d-8e9f-0a1b2c3d4e5f';

	private const CASE_ID = '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d';

	private const TASK = '1b2c3d4e-5f60-4172-8394-a5b6c7d8e9f0';

	/**
	 * The file double.
	 *
	 * @var FileServiceDouble
	 */
	private FileServiceDouble $fileService;

	/**
	 * Seed a project linked to a case, one task, and a file on each.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', self::PROJECT, ['title' => 'Renovatie stadhuis', 'owner' => 'olga', 'members' => ['olga', 'mo'], 'status' => 'active', 'caseReference' => self::CASE_ID]);
		$this->objects->seed('task', self::TASK, ['title' => 'Fundering', 'project' => self::PROJECT, 'status' => 'done', 'dueDate' => '2027-03-12', 'assignedTo' => 'mo', 'key' => 'RS-1']);
		$this->objects->seed('case', self::CASE_ID, ['title' => 'Zaak 12', 'identifier' => 'Z-2026-12']);

		$test = $this;
		$this->fileService = new FileServiceDouble(
			node: static function (string $name, string $content) use ($test): File {
				$file = $test->createMock(File::class);
				$file->method('getName')->willReturn($name);
				$file->method('getContent')->willReturn($content);
				$file->method('getSize')->willReturn(strlen($content));
				return $file;
			}
		);
		$this->fileService->files[self::PROJECT] = ['plan.pdf' => "%PDF-1.4 plan\n%%EOF"];
		$this->fileService->files[self::TASK]    = ['tekening.pdf' => "%PDF-1.4 tekening\n%%EOF"];
	}//end setUp()

	/**
	 * The controller acting as this user.
	 *
	 * @param string $uid           The caller.
	 * @param bool   $caseInstalled Whether Dossiq is installed.
	 *
	 * @return CaseHandoverController
	 */
	private function controller(string $uid, bool $caseInstalled = true): CaseHandoverController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);

		$apps = $this->createMock(IAppManager::class);
		$apps->method('isInstalled')->willReturnCallback(
			static fn (string $app): bool => $app === 'openregister' || ($app === 'dossiq' && $caseInstalled === true)
		);

		$objects   = $this->container();
		$files     = $this->fileService;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\\OpenRegister\\Service\\FileService') ? $files : $objects->get($id)
		);

		$logger  = $this->createMock(LoggerInterface::class);
		$service = new CaseHandoverService(container: $container, appManager: $apps, userSession: $session, logger: $logger);

		return new CaseHandoverController(
			request: $this->createMock(IRequest::class),
			handoverService: $service,
			membership: $this->membershipService(),
			userSession: $session,
			groupManager: $groups,
			logger: $logger
		);
	}//end controller()

	/**
	 * The owner hands the project over: every file byte for byte, a metadata file, and a record with checksums.
	 *
	 * @return void
	 */
	public function testOwnerHandsOverFilesAndMetadataUnchanged(): void {
		$response = $this->controller(uid: 'olga')->handOver(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_OK, $response->getStatus());

		$onCase = $this->fileService->files[self::CASE_ID];
		self::assertSame("%PDF-1.4 plan\n%%EOF", $onCase['plan.pdf']);
		self::assertSame("%PDF-1.4 tekening\n%%EOF", $onCase['RS-1 Fundering - tekening.pdf']);

		$metadata = json_decode($onCase['project-metadata.json'], true);
		self::assertSame('Renovatie stadhuis', $metadata['project']['title']);
		self::assertSame(['olga', 'mo'], $metadata['project']['members']);
		self::assertSame([['id' => self::TASK, 'key' => 'RS-1', 'title' => 'Fundering', 'status' => 'done', 'startDate' => null, 'dueDate' => '2027-03-12', 'assignedTo' => 'mo']], $metadata['tasks']);

		$record = $this->objects->rows['project'][self::PROJECT]['caseHandovers'][0];
		self::assertSame('olga', $record['by']);
		self::assertSame(self::CASE_ID, $record['case']);
		self::assertSame([], $record['failures']);
		$checksums = array_column($record['files'], 'sha256', 'name');
		self::assertSame(hash('sha256', "%PDF-1.4 plan\n%%EOF"), $checksums['plan.pdf']);
		self::assertSame(hash('sha256', $onCase['project-metadata.json']), $checksums['project-metadata.json']);
		self::assertSame($record, $response->getData());

		$saved = end($this->objects->saves);
		self::assertTrue($saved['_rbac'], 'the record is written with the owner\'s rights');
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: (array)$saved['object']));
	}//end testOwnerHandsOverFilesAndMetadataUnchanged()

	/**
	 * A copy that reads back different bytes fails that file, and the record says so.
	 *
	 * @return void
	 */
	public function testChecksumMismatchFailsThatFile(): void {
		$this->fileService->corruptOnWrite = ['plan.pdf'];

		$record = $this->controller(uid: 'olga')->handOver(projectId: self::PROJECT)->getData();

		self::assertSame([['name' => 'plan.pdf', 'reason' => 'checksum']], $record['failures']);
		self::assertNotContains('plan.pdf', array_column($record['files'], 'name'));
		self::assertContains('RS-1 Fundering - tekening.pdf', array_column($record['files'], 'name'));
	}//end testChecksumMismatchFailsThatFile()

	/**
	 * A second handover keeps the first copies and adds numbered ones.
	 *
	 * @return void
	 */
	public function testSecondHandoverKeepsBothCopies(): void {
		$this->controller(uid: 'olga')->handOver(projectId: self::PROJECT);
		$record = $this->controller(uid: 'olga')->handOver(projectId: self::PROJECT)->getData();

		self::assertContains('plan (1).pdf', array_column($record['files'], 'name'));
		self::assertContains('project-metadata (1).json', array_column($record['files'], 'name'));
		self::assertCount(2, $this->objects->rows['project'][self::PROJECT]['caseHandovers']);
	}//end testSecondHandoverKeepsBothCopies()

	/**
	 * A member who is not the owner is refused and nothing is copied.
	 *
	 * @return void
	 */
	public function testNonOwnerIsRefused(): void {
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'mo')->handOver(projectId: self::PROJECT)->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'eve')->status(projectId: self::PROJECT)->getStatus());
		self::assertSame([], $this->fileService->added);
	}//end testNonOwnerIsRefused()

	/**
	 * Without the case app, without a case link, or with a case the user cannot read, nothing is copied.
	 *
	 * @return void
	 */
	public function testNothingIsCopiedWithoutAReachableCase(): void {
		$status = $this->controller(uid: 'olga', caseInstalled: false)->status(projectId: self::PROJECT)->getData();
		self::assertFalse($status['available']);
		$refused = $this->controller(uid: 'olga', caseInstalled: false)->handOver(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_CONFLICT, $refused->getStatus());
		self::assertSame('noCaseApp', $refused->getData()['reason']);

		unset($this->objects->rows['case'][self::CASE_ID]);
		$unreadable = $this->controller(uid: 'olga')->handOver(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_NOT_FOUND, $unreadable->getStatus());
		self::assertSame('caseNotFound', $unreadable->getData()['reason']);

		$this->objects->rows['project'][self::PROJECT]['caseReference'] = '';
		$unlinked = $this->controller(uid: 'olga')->handOver(projectId: self::PROJECT);
		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $unlinked->getStatus());
		self::assertSame('noCase', $unlinked->getData()['reason']);

		self::assertSame([], $this->fileService->added);

		$available = $this->controller(uid: 'olga')->status(projectId: self::PROJECT)->getData();
		self::assertSame(['available' => true, 'handovers' => []], $available);
	}//end testNothingIsCopiedWithoutAReachableCase()
}//end class
