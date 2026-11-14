<?php

/**
 * Tests for the queued project export (project-data-export, tasks 1.3, 3.2 and 3.3).
 *
 * The job, the export service and the archive writer are the real classes.
 * OpenRegister is the in-memory doubles of ProjectExportFixture plus an
 * ExportAuditRecorder double; the user's Files are a Folder mock that
 * creates File mocks and can be told to run out of space.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
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

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

require_once __DIR__ . '/../Support/ProjectExportFixture.php';

use OCA\Planninq\BackgroundJob\ExportProjectJob;
use OCA\Planninq\Service\ProjectExportArchive;
use OCA\Planninq\Service\ProjectExportService;
use OCA\Planninq\Tests\Unit\Support\ProjectExportFixture;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ZipArchive;

/**
 * @covers \OCA\Planninq\BackgroundJob\ExportProjectJob
 * @uses \OCA\Planninq\Service\ProjectExportService
 * @uses \OCA\Planninq\Service\ProjectExportArchive
 * @uses \OCA\Planninq\Exception\ProjectExportException
 */
class ExportProjectJobTest extends TestCase {
	use ProjectExportFixture;

	/**
	 * The audit recorder's calls: [method, named arguments].
	 *
	 * @var list<array{0:string,1:array<string,mixed>}>
	 */
	public array $audit = [];

	/**
	 * The notifications sent: [user, subject, parameters, object id].
	 *
	 * @var list<array{0:string,1:string,2:array<string,mixed>,3:string}>
	 */
	private array $sent = [];

	/**
	 * Files in the user's export folder: name => content.
	 *
	 * @var array<string,string>
	 */
	private array $exportFolder = [];

	/**
	 * Whether the export folder exists before the job runs.
	 *
	 * @var boolean
	 */
	private bool $folderExists = false;

	/**
	 * Whether writing a file runs out of space after creating it.
	 *
	 * @var boolean
	 */
	private bool $quotaFull = false;

	/**
	 * The users set on the session, in order.
	 *
	 * @var list<string|null>
	 */
	private array $sessionUsers = [];

	/**
	 * Temporary files to remove.
	 *
	 * @var array<int,string>
	 */
	private array $temporary = [];

	/**
	 * Seed both projects.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->seedTwoProjects();
	}//end setUp()

	/**
	 * Remove temporary files.
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
	 * A completed export lands in Files, is audited with its object count and notified as ready.
	 *
	 * @return void
	 */
	public function testACompletedExportIsWrittenAuditedAndNotified(): void {
		$path = $this->job()->export(projectId: self::PROJECT_A, userId: 'mia');

		$this->assertSame('Planninq exports/HM-2026-11-14-1030.zip', $path);
		$this->assertSame(['HM-2026-11-14-1030.zip'], array_keys($this->exportFolder));
		$zip   = $this->zipOf(content: $this->exportFolder['HM-2026-11-14-1030.zip']);
		$tasks = json_decode((string)$zip->getFromName('task.json'), true);
		$zip->close();
		$this->assertCount(3, $tasks);

		// project + 3 tasks + column + phase + risk + 1 board view + 1 dependency.
		$this->assertSame(
			[['recordCompleted', ['profile' => ProjectExportService::PROFILE, 'rowCount' => 9, 'format' => 'zip', 'valueMode' => 'stored', 'register' => 7, 'actorId' => 'mia']]],
			$this->audit
		);
		$this->assertSame(
			[['mia', 'project_export_ready', ['project' => 'Herinrichting Marktplein', 'path' => 'Planninq exports/HM-2026-11-14-1030.zip', 'fileId' => 42], self::PROJECT_A]],
			$this->sent
		);
		$this->assertSame(['mia', 'admin'], $this->sessionUsers, 'the export runs as the requester and the session is put back');
	}//end testACompletedExportIsWrittenAuditedAndNotified()

	/**
	 * The queued argument runs the same export, into an existing folder, beside an earlier zip.
	 *
	 * @return void
	 */
	public function testTheQueuedArgumentRunsTheExportBesideAnEarlierZip(): void {
		$this->folderExists                           = true;
		$this->exportFolder['HM-2026-11-14-1030.zip'] = 'earlier';

		$this->runJob(['projectId' => self::PROJECT_A, 'userId' => 'mia']);

		$this->assertSame(['HM-2026-11-14-1030.zip', 'HM-2026-11-14-1030 (1).zip'], array_keys($this->exportFolder));
		$this->assertSame('earlier', $this->exportFolder['HM-2026-11-14-1030.zip']);
		$this->assertSame('project_export_ready', $this->sent[0][1]);
	}//end testTheQueuedArgumentRunsTheExportBesideAnEarlierZip()

	/**
	 * A manager without the export verb gets no zip, the permission failure, and no second audit row.
	 *
	 * @return void
	 */
	public function testARefusedExportWritesNoZipAndSaysThePermissionIsMissing(): void {
		$this->refused = ['task'];

		$this->assertNull($this->job()->export(projectId: self::PROJECT_A, userId: 'mia'));

		$this->assertSame([], $this->exportFolder);
		$this->assertSame([], $this->audit, 'the gate records the refusal; planninq writes no row of its own');
		$this->assertSame(
			[['mia', 'project_export_failed', ['project' => self::PROJECT_A, 'reason' => ExportProjectJob::REASON_PERMISSION], self::PROJECT_A]],
			$this->sent
		);
	}//end testARefusedExportWritesNoZipAndSaysThePermissionIsMissing()

	/**
	 * A full quota leaves no partial zip and says there is not enough space.
	 *
	 * @return void
	 */
	public function testAFullQuotaLeavesNoPartialZip(): void {
		$this->quotaFull = true;

		$this->assertNull($this->job()->export(projectId: self::PROJECT_A, userId: 'mia'));

		$this->assertSame([], $this->exportFolder, 'the half-written zip is removed');
		$this->assertSame('project_export_failed', $this->sent[0][1]);
		$this->assertSame(['project' => 'Herinrichting Marktplein', 'reason' => ExportProjectJob::REASON_SPACE], $this->sent[0][2]);
		$this->assertSame('recordRefused', $this->audit[0][0]);
		$this->assertSame('planninq-export-space', $this->audit[0][1]['rule']);
		$this->assertSame('mia', $this->audit[0][1]['actorId']);
		$this->assertSame(['mia', 'admin'], $this->sessionUsers);
	}//end testAFullQuotaLeavesNoPartialZip()

	/**
	 * An argument without a known user does nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownUserDoesNothing(): void {
		$this->runJob(['projectId' => self::PROJECT_A, 'userId' => 'ghost']);
		$this->runJob('not an array');

		$this->assertSame([], $this->exportFolder);
		$this->assertSame([], $this->sent);
		$this->assertSame([], $this->sessionUsers);
	}//end testAnUnknownUserDoesNothing()

	/**
	 * The zip is named by the key, else the title, else "project", with unsafe characters replaced.
	 *
	 * @return void
	 */
	public function testTheZipName(): void {
		$at  = new \DateTimeImmutable('2026-01-02T03:04:00+00:00');
		$job = $this->job();

		$this->assertSame('HM-2026-01-02-0304.zip', $job->zipName(content: ['key' => 'HM', 'title' => 'X'], started: $at));
		$this->assertSame('Plan-B-2026-01-02-0304.zip', $job->zipName(content: ['key' => '', 'title' => 'Plan/B'], started: $at));
		$this->assertSame('project-2026-01-02-0304.zip', $job->zipName(content: ['key' => ' ', 'title' => '..'], started: $at));
	}//end testTheZipName()

	/**
	 * Run the job's protected run() with a queued argument.
	 *
	 * @param mixed $argument The argument.
	 *
	 * @return void
	 */
	private function runJob(mixed $argument): void {
		$run = new \ReflectionMethod(ExportProjectJob::class, 'run');
		$run->invoke($this->job(), $argument);
	}//end runJob()

	/**
	 * The job over the doubles.
	 *
	 * @return ExportProjectJob
	 */
	private function job(): ExportProjectJob {
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-11-14T10:30:00+00:00'));
		$time->method('getTime')->willReturn(1789000000);

		$temp = $this->createMock(ITempManager::class);
		$temp->method('getTemporaryFile')->willReturnCallback(
			function (): string {
				$path              = (string)tempnam(sys_get_temp_dir(), 'pqj');
				$this->temporary[] = $path;
				return $path;
			}
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppVersion')->willReturn('1.0.0');

		$mia = $this->createMock(IUser::class);
		$mia->method('getUID')->willReturn('mia');
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $uid === 'mia' ? $mia : null);

		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($admin);
		$session->method('setUser')->willReturnCallback(
			function (?IUser $user): void {
				$this->sessionUsers[] = $user?->getUID();
			}
		);

		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('mia')->willReturn($this->home());

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with('OCA\\OpenRegister\\Service\\Export\\ExportAuditRecorder')->willReturn($this->recorder());

		return new ExportProjectJob(
			time: $time,
			exports: $this->exportService(),
			archive: new ProjectExportArchive(tempManager: $temp, appManager: $apps),
			userManager: $users,
			userSession: $session,
			rootFolder: $root,
			tempManager: $temp,
			notifications: $this->notifications(),
			container: $container,
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end job()

	/**
	 * The ExportAuditRecorder double.
	 *
	 * @return object
	 */
	private function recorder(): object {
		$test = $this;
		return new class($test) {
			// phpcs:disable
			public function __construct(private object $test) {
			}
			public function recordCompleted(string $profile, int $rowCount, string $format, ?string $valueMode = null, ?int $register = null, ?int $schema = null, ?string $actorId = null): void {
				$this->test->audit[] = ['recordCompleted', compact('profile', 'rowCount', 'format', 'valueMode', 'register', 'actorId')];
			}
			public function recordRefused(string $profile, string $rule, string $reason, ?int $register = null, ?int $schema = null, ?string $actorId = null): void {
				$this->test->audit[] = ['recordRefused', compact('profile', 'rule', 'reason', 'actorId')];
			}
			// phpcs:enable
		};
	}//end recorder()

	/**
	 * The notification manager mock that records each notification.
	 *
	 * @return INotificationManager
	 */
	private function notifications(): INotificationManager {
		$manager = $this->createMock(INotificationManager::class);
		$manager->method('createNotification')->willReturnCallback(
			function (): INotification {
				$state        = new \ArrayObject();
				$notification = $this->createMock(INotification::class);
				foreach (['setApp', 'setUser', 'setDateTime'] as $setter) {
					$notification->method($setter)->willReturnCallback(
						static function (mixed $value) use ($state, $setter, $notification): INotification {
							$state[$setter] = $value;
							return $notification;
						}
					);
				}

				$notification->method('setObject')->willReturnCallback(
					static function (string $type, string $id) use ($state, $notification): INotification {
						$state['object'] = $id;
						return $notification;
					}
				);
				$notification->method('setSubject')->willReturnCallback(
					static function (string $subject, array $parameters) use ($state, $notification): INotification {
						$state['subject']    = $subject;
						$state['parameters'] = $parameters;
						return $notification;
					}
				);
				$notification->method('getUser')->willReturnCallback(static fn (): string => (string)$state['setUser']);
				$notification->method('getSubject')->willReturnCallback(static fn (): string => (string)$state['subject']);
				$notification->method('getSubjectParameters')->willReturnCallback(static fn (): array => (array)$state['parameters']);
				$notification->method('getObjectId')->willReturnCallback(static fn (): string => (string)$state['object']);
				$notification->method('getApp')->willReturnCallback(static fn (): string => (string)$state['setApp']);
				return $notification;
			}
		);
		$manager->method('notify')->willReturnCallback(
			function (INotification $notification): void {
				$this->assertSame('planninq', $notification->getApp());
				$this->sent[] = [$notification->getUser(), $notification->getSubject(), $notification->getSubjectParameters(), $notification->getObjectId()];
			}
		);

		return $manager;
	}//end notifications()

	/**
	 * The user's home folder mock.
	 *
	 * @return Folder
	 */
	private function home(): Folder {
		$home = $this->createMock(Folder::class);
		$home->method('nodeExists')->willReturnCallback(fn (string $name): bool => $name === ExportProjectJob::FOLDER && $this->folderExists);
		$home->method('get')->willReturnCallback(fn (): Folder => $this->exportFolderMock());
		$home->method('newFolder')->willReturnCallback(
			function (string $name): Folder {
				$this->assertSame(ExportProjectJob::FOLDER, $name);
				$this->folderExists = true;
				return $this->exportFolderMock();
			}
		);

		return $home;
	}//end home()

	/**
	 * The export folder mock over `$this->exportFolder`.
	 *
	 * @return Folder
	 */
	private function exportFolderMock(): Folder {
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturnCallback(fn (string $name): bool => array_key_exists($name, $this->exportFolder));
		$folder->method('newFile')->willReturnCallback(
			function (string $name): File {
				$this->exportFolder[$name] = '';
				$file = $this->createMock(File::class);
				$file->method('getName')->willReturn($name);
				$file->method('getId')->willReturn(42);
				$file->method('putContent')->willReturnCallback(
					function ($stream) use ($name): void {
						$this->exportFolder[$name] = (string)stream_get_contents($stream);
						if ($this->quotaFull === true) {
							throw new NotEnoughSpaceException('Not enough free space');
						}
					}
				);
				$file->method('delete')->willReturnCallback(
					function () use ($name): void {
						unset($this->exportFolder[$name]);
					}
				);
				return $file;
			}
		);

		return $folder;
	}//end exportFolderMock()

	/**
	 * Open zip bytes.
	 *
	 * @param string $content The zip bytes.
	 *
	 * @return ZipArchive
	 */
	private function zipOf(string $content): ZipArchive {
		$path              = (string)tempnam(sys_get_temp_dir(), 'pqz');
		$this->temporary[] = $path;
		file_put_contents($path, $content);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($path));
		return $zip;
	}//end zipOf()
}//end class
