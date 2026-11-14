<?php

/**
 * Tests for gathering and zipping one project's export (project-data-export, tasks 1.1 and 1.2).
 *
 * The service and the archive writer are the real classes; OpenRegister's
 * services are the in-memory doubles of ProjectExportFixture, and the export
 * gate double refuses the schemas a test names. The produced zip is opened
 * and read back.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ProjectExportFixture.php';

use OCA\Planninq\Exception\ProjectExportException;
use OCA\Planninq\Service\ProjectExportArchive;
use OCA\Planninq\Service\ProjectExportService;
use OCA\Planninq\Tests\Unit\Support\ProjectExportFixture;
use OCP\App\IAppManager;
use OCP\ITempManager;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * @covers \OCA\Planninq\Service\ProjectExportService
 * @covers \OCA\Planninq\Service\ProjectExportArchive
 * @uses \OCA\Planninq\Exception\ProjectExportException
 */
class ProjectExportServiceTest extends TestCase {
	use ProjectExportFixture;

	/**
	 * Temporary files to remove after each test.
	 *
	 * @var array<int,string>
	 */
	private array $temporary = [];

	/**
	 * Seed project A and project B.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->seedTwoProjects();
	}//end setUp()

	/**
	 * Remove the temporary files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ($this->temporary as $path) {
			@unlink($path);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Only project A's objects are gathered, each schema through the gate with planninq's profile.
	 *
	 * @return void
	 */
	public function testItGathersOnlyTheProjectsObjects(): void {
		$content = $this->exportService()->collect(projectId: self::PROJECT_A);

		$this->assertSame('Herinrichting Marktplein', $content['title']);
		$this->assertSame('HM', $content['key']);
		$this->assertSame(7, $content['registerId']);
		$this->assertSame(['HM-1', 'HM-2', 'HM-3'], array_column($content['schemas']['task'], 'key'));
		$this->assertSame(['Mine'], array_column($content['schemas']['boardView'], 'title'));
		$this->assertSame(['d0000000-0000-4000-8000-000000000001'], array_column(array_column($content['schemas']['dependency'], '@self'), 'id'));
		$this->assertSame(['a0000000-0000-4000-8000-000000000001'], array_keys($content['comments']));
		$this->assertSame('Piet Pieters', $content['comments']['a0000000-0000-4000-8000-000000000001'][1]['displayName']);
		$this->assertSame(['attachments/HM-1/plan.pdf', 'phase-documents/Ontwerp/ontwerp.odt'], array_column($content['files'], 'path'));
		$this->assertSame([], $content['skipped']);

		$this->assertSame(['HM-1', 'Task A1', 'open', 'Doing', 'Olga Owner', '2026-11-01', '2026-12-01', 'urgent'], $content['csv'][1]);
		$this->assertCount(4, $content['csv']);

		$profiles = array_unique(array_column($this->gateCalls, 1));
		$this->assertSame([ProjectExportService::PROFILE], array_values($profiles));
		$this->assertSame([7], array_values(array_unique(array_column($this->gateCalls, 2))));
		$slugs = array_column($this->gateCalls, 0);
		$this->assertSame(count($slugs), count(array_unique($slugs)), 'each schema is put to the gate once');
		$this->assertContains('dependency', $slugs);
	}//end testItGathersOnlyTheProjectsObjects()

	/**
	 * Searches run with the requester's rights and page through OpenRegister's results.
	 *
	 * @return void
	 */
	public function testItReadsAsTheRequesterPageByPage(): void {
		for ($i = 4; $i <= ProjectExportService::PAGE_SIZE + 3; $i++) {
			$this->rows['task'][sprintf('a0000000-0000-4000-8000-%012d', $i)] = ['title' => 'T' . $i, 'project' => self::PROJECT_A];
		}

		$content = $this->exportService()->collect(projectId: self::PROJECT_A);

		$this->assertCount(ProjectExportService::PAGE_SIZE + 3, $content['schemas']['task']);
		$taskSearches = array_values(array_filter($this->searches, static fn (array $s): bool => $s['schema'] === 'task'));
		$this->assertSame([0, ProjectExportService::PAGE_SIZE], array_map(static fn (array $s): int => $s['filters']['_offset'], $taskSearches));
		$this->assertSame(self::PROJECT_A, $taskSearches[0]['filters']['project']);
		$this->assertSame([true], array_values(array_unique(array_column($this->searches, 'rbac'))));
	}//end testItReadsAsTheRequesterPageByPage()

	/**
	 * A refused task schema stops the export.
	 *
	 * @return void
	 */
	public function testARefusedTaskSchemaStopsTheExport(): void {
		$this->refused = ['task'];

		try {
			$this->exportService()->collect(projectId: self::PROJECT_A);
			$this->fail('A refused task schema must stop the export.');
		} catch (ProjectExportException $e) {
			$this->assertSame(ProjectExportException::REFUSED, $e->getReason());
		}

		$this->assertSame([], $this->searches, 'nothing is read once the task schema is refused');
	}//end testARefusedTaskSchemaStopsTheExport()

	/**
	 * A refused project schema stops the export too.
	 *
	 * @return void
	 */
	public function testARefusedProjectSchemaStopsTheExport(): void {
		$this->refused = ['project'];

		$this->expectException(ProjectExportException::class);
		$this->exportService()->collect(projectId: self::PROJECT_A);
	}//end testARefusedProjectSchemaStopsTheExport()

	/**
	 * A refused risk schema is left out and named as skipped.
	 *
	 * @return void
	 */
	public function testARefusedOtherSchemaIsSkipped(): void {
		$this->refused = ['risk'];

		$content = $this->exportService()->collect(projectId: self::PROJECT_A);

		$this->assertArrayNotHasKey('risk', $content['schemas']);
		$this->assertArrayHasKey('task', $content['schemas']);
		$this->assertSame([['item' => 'risk.json', 'reason' => 'no export permission']], $content['skipped']);
		$this->assertNotContains('risk', array_column($this->searches, 'schema'));
	}//end testARefusedOtherSchemaIsSkipped()

	/**
	 * An unreadable attachment is skipped with "no read access", not fatal.
	 *
	 * @return void
	 */
	public function testAnUnreadableFileIsSkipped(): void {
		$this->objectFiles['a0000000-0000-4000-8000-000000000002'] = ['secret.pdf' => ['x', false]];

		$content = $this->exportService()->collect(projectId: self::PROJECT_A);

		$this->assertNotContains('attachments/HM-2/secret.pdf', array_column($content['files'], 'path'));
		$this->assertSame([['item' => 'attachments/HM-2/secret.pdf', 'reason' => 'no read access']], $content['skipped']);
	}//end testAnUnreadableFileIsSkipped()

	/**
	 * A project the requester cannot read is not found.
	 *
	 * @return void
	 */
	public function testAnUnknownProjectIsNotFound(): void {
		try {
			$this->exportService()->collect(projectId: 'cccccccc-0000-4000-8000-000000000001');
			$this->fail('An unknown project must not export.');
		} catch (ProjectExportException $e) {
			$this->assertSame(ProjectExportException::NOT_FOUND, $e->getReason());
		}
	}//end testAnUnknownProjectIsNotFound()

	/**
	 * The zip holds project A and nothing else, with a manifest that counts it.
	 *
	 * @return void
	 */
	public function testTheZipHoldsTheProjectAndNothingElse(): void {
		$this->refused = ['risk'];
		$content       = $this->exportService()->collect(projectId: self::PROJECT_A);
		$path          = $this->temporaryFile();

		$manifest = $this->archive()->write(content: $content, path: $path, started: new \DateTimeImmutable('2026-11-14T10:30:00+00:00'));

		$zip = new ZipArchive();
		$this->assertTrue($zip->open($path));
		$tasks = json_decode((string)$zip->getFromName('task.json'), true);
		$this->assertSame(['HM-1', 'HM-2', 'HM-3'], array_column($tasks, 'key'));
		$this->assertSame('%PDF-1.4 plan', $zip->getFromName('attachments/HM-1/plan.pdf'));
		$this->assertSame('odt bytes', $zip->getFromName('phase-documents/Ontwerp/ontwerp.odt'));
		$comments = json_decode((string)$zip->getFromName('comments.json'), true);
		$this->assertSame(['First', 'Second'], array_column($comments['a0000000-0000-4000-8000-000000000001'], 'message'));
		$this->assertSame('Herinrichting Marktplein', json_decode((string)$zip->getFromName('project.json'), true)['title']);
		$this->assertFalse($zip->getFromName('risk.json'));
		$csv = array_map('str_getcsv', explode("\n", trim((string)$zip->getFromName('tasks.csv'))));
		$this->assertSame(ProjectExportService::CSV_HEADER, $csv[0]);
		$this->assertCount(4, $csv);

		$written = json_decode((string)$zip->getFromName('manifest.json'), true);
		$zip->close();

		$this->assertSame($manifest, $written);
		$this->assertSame(['valueMode' => 'stored', 'rows' => 3], $written['files']['task.json']);
		$this->assertSame(['valueMode' => 'rendered', 'rows' => 3], $written['files']['tasks.csv']);
		$this->assertSame(['valueMode' => 'stored', 'rows' => 2], $written['files']['comments.json']);
		$this->assertSame(['valueMode' => 'stored', 'rows' => 1], $written['files']['dependency.json']);
		$this->assertSame('2026-11-14T10:30:00+00:00', $written['startedAt']);
		$this->assertSame(['planninq' => '1.2.3', 'openregister' => '1.2.3'], $written['versions']);
		$this->assertSame(['id' => self::PROJECT_A, 'key' => 'HM', 'title' => 'Herinrichting Marktplein'], $written['project']);
		$this->assertSame([['item' => 'risk.json', 'reason' => 'no export permission']], $written['skipped']);
		$this->assertSame(ProjectExportService::PROFILE, $written['profile']);
	}//end testTheZipHoldsTheProjectAndNothingElse()

	/**
	 * A file that fails to open while zipping is skipped and named, and two equal names both land.
	 *
	 * @return void
	 */
	public function testTheArchiveSkipsAFileItCannotOpenAndKeepsDuplicateNames(): void {
		$broken = $this->createMock(\OCP\Files\File::class);
		$broken->method('fopen')->willThrowException(new \OCP\Files\NotPermittedException('no'));
		$content = [
			'project'  => ['@self' => ['id' => 'p']],
			'schemas'  => ['task' => [['a' => 1], ['a' => 2]]],
			'csv'      => [ProjectExportService::CSV_HEADER],
			'comments' => [],
			'files'    => [
				['path' => 'attachments/K/a.pdf', 'file' => $this->fileNode(name: 'a.pdf', content: 'one')],
				['path' => 'attachments/K/a.pdf', 'file' => $this->fileNode(name: 'a.pdf', content: 'two')],
				['path' => 'attachments/K/x.pdf', 'file' => $broken],
			],
			'skipped'  => [],
		];
		$path = $this->temporaryFile();

		$manifest = $this->archive()->write(content: $content, path: $path, started: new \DateTimeImmutable('2026-11-14T10:30:00+00:00'));

		$zip = new ZipArchive();
		$zip->open($path);
		$this->assertSame('one', $zip->getFromName('attachments/K/a.pdf'));
		$this->assertSame('two', $zip->getFromName('attachments/K/a (1).pdf'));
		$this->assertFalse($zip->getFromName('attachments/K/x.pdf'));
		$zip->close();
		$this->assertSame([['item' => 'attachments/K/x.pdf', 'reason' => 'no read access']], $manifest['skipped']);
		$this->assertSame(['valueMode' => 'rendered', 'rows' => 0], $manifest['files']['tasks.csv']);
		$this->assertSame(3, $this->archive()->objectCount(content: $content));
	}//end testTheArchiveSkipsAFileItCannotOpenAndKeepsDuplicateNames()

	/**
	 * The archive writer over a real temp directory.
	 *
	 * @return ProjectExportArchive
	 */
	private function archive(): ProjectExportArchive {
		$temp = $this->createMock(ITempManager::class);
		$temp->method('getTemporaryFile')->willReturnCallback(fn (): string => $this->temporaryFile());
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppVersion')->willReturn('1.2.3');

		return new ProjectExportArchive(tempManager: $temp, appManager: $apps);
	}//end archive()

	/**
	 * A fresh temporary file path, removed after the test.
	 *
	 * @return string
	 */
	private function temporaryFile(): string {
		$path              = (string)tempnam(sys_get_temp_dir(), 'pqx');
		$this->temporary[] = $path;
		return $path;
	}//end temporaryFile()
}//end class
