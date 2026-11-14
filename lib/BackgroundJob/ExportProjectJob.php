<?php

/**
 * Planninq ExportProjectJob
 *
 * Runs one project export as the user who asked for it: gathers the project
 * through ProjectExportService, writes the zip, puts it in that user's Files
 * under `Planninq exports/`, records the completed export through
 * OpenRegister's export audit recorder and tells the user with a
 * notification. On any failure no partial zip stays behind and the user gets
 * the failure notification with the reason.
 *
 * @category BackgroundJob
 * @package  OCA\Planninq\BackgroundJob
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Exception\ProjectExportException;
use OCA\Planninq\Service\ProjectExportArchive;
use OCA\Planninq\Service\ProjectExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * One queued project export.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) It joins the export service, Files, sessions, notifications and the audit trail.
 * @SuppressWarnings(PHPMD.ExcessiveParameterList) Each collaborator is a Nextcloud service the job needs once.
 */
class ExportProjectJob extends QueuedJob {

	/**
	 * The folder in the user's Files that receives the zips.
	 */
	public const FOLDER = 'Planninq exports';

	/**
	 * Failure reason: the requester lacks the export permission.
	 */
	public const REASON_PERMISSION = 'permission';

	/**
	 * Failure reason: the requester's quota is too small.
	 */
	public const REASON_SPACE = 'space';

	/**
	 * Failure reason: anything else.
	 */
	public const REASON_ERROR = 'error';

	/**
	 * Constructor
	 *
	 * @param ITimeFactory         $time          The clock.
	 * @param ProjectExportService $exports       Gathers the project.
	 * @param ProjectExportArchive $archive       Writes the zip.
	 * @param IUserManager         $userManager   Resolves the requester.
	 * @param IUserSession         $userSession   Runs the export as the requester.
	 * @param IRootFolder          $rootFolder    The requester's Files.
	 * @param ITempManager         $tempManager   The local zip file.
	 * @param INotificationManager $notifications Sends the ready or failed notification.
	 * @param ContainerInterface   $container     Resolves OpenRegister's export audit recorder.
	 * @param LoggerInterface      $logger        The logger.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly ProjectExportService $exports,
		private readonly ProjectExportArchive $archive,
		private readonly IUserManager $userManager,
		private readonly IUserSession $userSession,
		private readonly IRootFolder $rootFolder,
		private readonly ITempManager $tempManager,
		private readonly INotificationManager $notifications,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Run the export the job was queued with.
	 *
	 * @param mixed $argument `['projectId' => string, 'userId' => string]`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	protected function run($argument): void {
		$projectId = '';
		$userId    = '';
		if (is_array($argument) === true) {
			$projectId = (string)($argument['projectId'] ?? '');
			$userId    = (string)($argument['userId'] ?? '');
		}

		$this->export(projectId: $projectId, userId: $userId);
	}//end run()

	/**
	 * Export one project for one user.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $userId    The requester.
	 *
	 * @return string|null The zip's path in the user's Files, or null when the export failed.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function export(string $projectId, string $userId): ?string {
		$user = null;
		if ($projectId !== '' && $userId !== '') {
			$user = $this->userManager->get($userId);
		}

		if ($user === null) {
			$this->logger->warning('Planninq: a project export was queued without a known project or user', ['project' => $projectId, 'user' => $userId]);
			return null;
		}

		$previous = $this->userSession->getUser();
		$this->userSession->setUser($user);
		try {
			return $this->exportAs(user: $user, projectId: $projectId);
		} finally {
			$this->userSession->setUser($previous);
		}
	}//end export()

	/**
	 * Export one project with the requester already in the session.
	 *
	 * @param IUser  $user      The requester.
	 * @param string $projectId The project uuid.
	 *
	 * @return string|null The zip's path in the user's Files, or null on failure.
	 */
	private function exportAs(IUser $user, string $projectId): ?string {
		$started = $this->time->getDateTime();
		$local   = null;
		$target  = null;
		$title   = $projectId;
		try {
			$content = $this->exports->collect(projectId: $projectId);
			if ($content['title'] !== '') {
				$title = $content['title'];
			}

			$local   = (string)$this->tempManager->getTemporaryFile('.zip');
			$this->archive->write(content: $content, path: $local, started: $started);

			$target = $this->place(user: $user, local: $local, name: $this->zipName(content: $content, started: $started));
			$this->recorder()->recordCompleted(
				profile: ProjectExportService::PROFILE,
				rowCount: $this->archive->objectCount(content: $content),
				format: 'zip',
				valueMode: 'stored',
				register: $content['registerId'],
				actorId: $user->getUID()
			);
			$path = self::FOLDER . '/' . $target->getName();
			$this->notify(
				user: $user,
				projectId: $projectId,
				subject: 'project_export_ready',
				parameters: ['project' => $title, 'path' => $path, 'fileId' => (int)$target->getId()]
			);

			return $path;
		} catch (ProjectExportException $e) {
			// The export gate has recorded its refusal on the audit trail already.
			$reason = self::REASON_ERROR;
			if ($e->getReason() === ProjectExportException::REFUSED) {
				$reason = self::REASON_PERMISSION;
			}

			$this->fail(user: $user, projectId: $projectId, title: $title, reason: $reason, recorded: $reason === self::REASON_PERMISSION, error: $e);
		} catch (NotEnoughSpaceException $e) {
			$this->remove(file: $target);
			$this->fail(user: $user, projectId: $projectId, title: $title, reason: self::REASON_SPACE, recorded: false, error: $e);
		} catch (\Throwable $e) {
			$this->remove(file: $target);
			$this->fail(user: $user, projectId: $projectId, title: $title, reason: self::REASON_ERROR, recorded: false, error: $e);
		} finally {
			if ($local !== null && $local !== '' && file_exists($local) === true) {
				@unlink($local);
			}
		}//end try

		return null;
	}//end exportAs()

	/**
	 * Copy the local zip into the user's export folder.
	 *
	 * A file that was created but not completely written is removed before
	 * the exception travels on, so no partial zip stays in Files.
	 *
	 * @param IUser  $user  The requester.
	 * @param string $local The local zip.
	 * @param string $name  The file name wanted.
	 *
	 * @return File The written file.
	 *
	 * @throws NotEnoughSpaceException When the quota is too small.
	 */
	private function place(IUser $user, string $local, string $name): File {
		$folder = $this->exportFolder(user: $user);
		$file   = $folder->newFile($this->freeName(folder: $folder, name: $name));
		$stream = fopen($local, 'r');
		try {
			$file->putContent($stream);
		} catch (\Throwable $e) {
			$this->remove(file: $file);
			throw $e;
		} finally {
			if (is_resource($stream) === true) {
				fclose($stream);
			}
		}

		return $file;
	}//end place()

	/**
	 * The user's export folder, created when missing.
	 *
	 * @param IUser $user The requester.
	 *
	 * @return Folder
	 */
	private function exportFolder(IUser $user): Folder {
		$home = $this->rootFolder->getUserFolder($user->getUID());
		if ($home->nodeExists(self::FOLDER) === true) {
			$folder = $home->get(self::FOLDER);
			if ($folder instanceof Folder) {
				return $folder;
			}
		}

		return $home->newFolder(self::FOLDER);
	}//end exportFolder()

	/**
	 * A name not yet taken in the folder: "X.zip", else "X (1).zip", and so on.
	 *
	 * @param Folder $folder The folder.
	 * @param string $name   The name wanted.
	 *
	 * @return string
	 */
	private function freeName(Folder $folder, string $name): string {
		$stem      = substr($name, 0, -4);
		$candidate = $name;
		for ($i = 1; $folder->nodeExists($candidate) === true; $i++) {
			$candidate = $stem . ' (' . $i . ').zip';
		}

		return $candidate;
	}//end freeName()

	/**
	 * `<project key>-<yyyy-mm-dd-hhmm>.zip`, with the title or "project" when there is no key.
	 *
	 * @param array<string,mixed> $content The export.
	 * @param \DateTimeInterface  $started When the export started.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function zipName(array $content, \DateTimeInterface $started): string {
		$label = trim((string)($content['key'] ?? ''));
		if ($label === '') {
			$label = trim((string)($content['title'] ?? ''));
		}

		$label = trim((string)preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', '-', $label), ' .-');
		if ($label === '') {
			$label = 'project';
		}

		return $label . '-' . $started->format('Y-m-d-Hi') . '.zip';
	}//end zipName()

	/**
	 * Record a failure that the gate did not record, log it and tell the user.
	 *
	 * @param IUser      $user      The requester.
	 * @param string     $projectId The project uuid.
	 * @param string     $title     The project title.
	 * @param string     $reason    One of the REASON_* constants.
	 * @param bool       $recorded  Whether the audit trail holds the failure already.
	 * @param \Throwable $error     What went wrong.
	 *
	 * @return void
	 */
	private function fail(IUser $user, string $projectId, string $title, string $reason, bool $recorded, \Throwable $error): void {
		$this->logger->warning('Planninq: a project export failed', ['project' => $projectId, 'reason' => $reason, 'exception' => $error->getMessage()]);
		if ($recorded === false) {
			try {
				$this->recorder()->recordRefused(
					profile: ProjectExportService::PROFILE,
					rule: 'planninq-export-' . $reason,
					reason: $error->getMessage(),
					actorId: $user->getUID()
				);
			} catch (\Throwable $e) {
				$this->logger->warning('Planninq: a failed project export was not recorded', ['project' => $projectId, 'exception' => $e->getMessage()]);
			}
		}

		$this->notify(user: $user, projectId: $projectId, subject: 'project_export_failed', parameters: ['project' => $title, 'reason' => $reason]);
	}//end fail()

	/**
	 * Delete a file that was left behind, ignoring a file that is gone already.
	 *
	 * @param File|null $file The file.
	 *
	 * @return void
	 */
	private function remove(?File $file): void {
		if ($file === null) {
			return;
		}

		try {
			$file->delete();
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: a partial project export could not be removed', ['exception' => $e->getMessage()]);
		}
	}//end remove()

	/**
	 * Send one notification to the requester.
	 *
	 * @param IUser               $user       The requester.
	 * @param string              $projectId  The project uuid.
	 * @param string              $subject    `project_export_ready` or `project_export_failed`.
	 * @param array<string,mixed> $parameters The subject parameters.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-3.2
	 */
	private function notify(IUser $user, string $projectId, string $subject, array $parameters): void {
		try {
			$notification = $this->notifications->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($user->getUID())
				->setDateTime($this->time->getDateTime())
				->setObject('project', $projectId)
				->setSubject($subject, $parameters);
			$this->notifications->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: the project export notification was not sent', ['project' => $projectId, 'exception' => $e->getMessage()]);
		}
	}//end notify()

	/**
	 * OpenRegister's export audit recorder.
	 *
	 * @return object
	 */
	private function recorder(): object {
		return $this->container->get('OCA\\OpenRegister\\Service\\Export\\ExportAuditRecorder');
	}//end recorder()
}//end class
