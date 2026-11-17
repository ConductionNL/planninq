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

use OCA\Planninq\Exception\ProjectExportException;
use OCA\Planninq\Kernel\AppIdentity;
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
	 * Log message: a project export was queued without a known project or user.
	 */
	private const LOG_UNKNOWN_REQUEST = 'Planninq: a project export was queued without a known project or user';

	/**
	 * Log message: a project export failed.
	 */
	private const LOG_FAILED = 'Planninq: a project export failed';

	/**
	 * Log message: a failed project export was not recorded.
	 */
	private const LOG_NOT_RECORDED = 'Planninq: a failed project export was not recorded';

	/**
	 * Log message: a partial project export could not be removed.
	 */
	private const LOG_NOT_REMOVED = 'Planninq: a partial project export could not be removed';

	/**
	 * Log message: the project export notification was not sent.
	 */
	private const LOG_NOT_NOTIFIED = 'Planninq: the project export notification was not sent';

	/**
	 * Constructor
	 *
	 * The job resolves its collaborators (the export, the zip, the users, the
	 * Files, the notifications, OpenRegister's audit recorder and the logger)
	 * through the container when it runs.
	 *
	 * @param ITimeFactory       $time      The clock.
	 * @param ContainerInterface $container Resolves the collaborators.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly ContainerInterface $container,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * One collaborator, from the container.
	 *
	 * @param class-string<T> $class The collaborator's class or interface.
	 *
	 * @return T
	 *
	 * @template T of object
	 */
	private function get(string $class): object {
		return $this->container->get($class);
	}//end get()

	/**
	 * Log a warning.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	private function warn(string $message, array $context): void {
		$this->get(class: LoggerInterface::class)->warning($message, $context);
	}//end warn()

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
		$user = $this->requester(projectId: $projectId, userId: $userId);
		if ($user === null) {
			return null;
		}

		$session  = $this->get(class: IUserSession::class);
		$previous = $session->getUser();
		$session->setUser($user);
		try {
			return $this->exportAs(request: new ExportRequest(user: $user, projectId: $projectId));
		} finally {
			$session->setUser($previous);
		}
	}//end export()

	/**
	 * The user who queued the export, or null (logged) when the job names no known project or user.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $userId    The requester.
	 *
	 * @return IUser|null
	 */
	private function requester(string $projectId, string $userId): ?IUser {
		$user = null;
		if ($projectId !== '' && $userId !== '') {
			$user = $this->get(class: IUserManager::class)->get($userId);
		}

		if ($user === null) {
			$this->warn(message: self::LOG_UNKNOWN_REQUEST, context: ['project' => $projectId, 'user' => $userId]);
		}

		return $user;
	}//end requester()

	/**
	 * Export one project with the requester already in the session.
	 *
	 * @param ExportRequest $request The export.
	 *
	 * @return string|null The zip's path in the user's Files, or null on failure.
	 */
	private function exportAs(ExportRequest $request): ?string {
		try {
			return $this->deliver(request: $request, started: $this->time->getDateTime());
		} catch (\Throwable $e) {
			// The export gate refused before any file was placed; anything else may have left one.
			if ($e instanceof ProjectExportException === false) {
				$this->remove(file: $request->target);
			}

			$this->fail(request: $request, reason: self::reasonOf(error: $e), error: $e);
		} finally {
			$request->removeLocal();
		}

		return null;
	}//end exportAs()

	/**
	 * Why an export failed, as the notification and the audit trail name it.
	 *
	 * @param \Throwable $error What went wrong.
	 *
	 * @return string REASON_PERMISSION for the export gate's refusal, REASON_SPACE for a full quota, else REASON_ERROR.
	 */
	private static function reasonOf(\Throwable $error): string {
		if ($error instanceof ProjectExportException && $error->getReason() === ProjectExportException::REFUSED) {
			return self::REASON_PERMISSION;
		}

		if ($error instanceof NotEnoughSpaceException) {
			return self::REASON_SPACE;
		}

		return self::REASON_ERROR;
	}//end reasonOf()

	/**
	 * Gather, zip, place, record and notify: the export when nothing fails.
	 *
	 * @param ExportRequest      $request The export.
	 * @param \DateTimeInterface $started When it started.
	 *
	 * @return string The zip's path in the user's Files.
	 */
	private function deliver(ExportRequest $request, \DateTimeInterface $started): string {
		$content = $request->adopt(content: $this->get(class: ProjectExportService::class)->collect(projectId: $request->projectId));
		$request->local = (string)$this->get(class: ITempManager::class)->getTemporaryFile('.zip');
		$this->get(class: ProjectExportArchive::class)->write(content: $content, path: $request->local, started: $started);
		$request->target = $this->place(request: $request, name: $this->zipName(content: $content, started: $started));
		$this->recordDelivery(request: $request, content: $content);

		$path = self::FOLDER . '/' . $request->target->getName();
		$this->notify(
			request: $request,
			subject: 'project_export_ready',
			parameters: ['project' => $request->title, 'path' => $path, 'fileId' => (int)$request->target->getId()]
		);

		return $path;
	}//end deliver()

	/**
	 * Put the completed export on OpenRegister's audit trail.
	 *
	 * @param ExportRequest        $request The export.
	 * @param array<string, mixed> $content What was exported.
	 *
	 * @return void
	 */
	private function recordDelivery(ExportRequest $request, array $content): void {
		$this->recorder()->recordCompleted(
			profile: ProjectExportService::PROFILE,
			rowCount: $this->get(class: ProjectExportArchive::class)->objectCount(content: $content),
			format: 'zip',
			valueMode: 'stored',
			register: $content['registerId'],
			actorId: $request->user->getUID()
		);
	}//end recordDelivery()

	/**
	 * Copy the local zip into the user's export folder.
	 *
	 * A file that was created but not completely written is removed before
	 * the exception travels on, so no partial zip stays in Files.
	 *
	 * @param ExportRequest $request The export, with its local zip.
	 * @param string        $name    The file name wanted.
	 *
	 * @return File The written file.
	 *
	 * @throws NotEnoughSpaceException When the quota is too small.
	 */
	private function place(ExportRequest $request, string $name): File {
		$folder = $this->exportFolder(user: $request->user);
		$file   = $folder->newFile($this->freeName(folder: $folder, name: $name));
		try {
			$this->fill(file: $file, local: (string)$request->local);
		} catch (\Throwable $e) {
			$this->remove(file: $file);
			throw $e;
		}

		return $file;
	}//end place()

	/**
	 * Stream the local zip into the file.
	 *
	 * @param File   $file  The file in Files.
	 * @param string $local The local zip.
	 *
	 * @return void
	 */
	private function fill(File $file, string $local): void {
		$stream = fopen($local, 'r');
		try {
			$file->putContent($stream);
		} finally {
			if (is_resource($stream) === true) {
				fclose($stream);
			}
		}
	}//end fill()

	/**
	 * The user's export folder, created when missing.
	 *
	 * @param IUser $user The requester.
	 *
	 * @return Folder
	 */
	private function exportFolder(IUser $user): Folder {
		$home = $this->get(class: IRootFolder::class)->getUserFolder($user->getUID());
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
	 * Log a failed export, record it unless the export gate already did, and tell the requester.
	 *
	 * @param ExportRequest $request The export.
	 * @param string        $reason  REASON_PERMISSION, REASON_SPACE or REASON_ERROR.
	 * @param \Throwable    $error   What went wrong.
	 *
	 * @return void
	 */
	private function fail(ExportRequest $request, string $reason, \Throwable $error): void {
		$this->warn(message: self::LOG_FAILED, context: ['project' => $request->projectId, 'reason' => $reason, 'exception' => $error->getMessage()]);
		// A refusal is on the audit trail already: the export gate recorded it.
		if ($reason !== self::REASON_PERMISSION) {
			$this->recordFailure(request: $request, rule: 'planninq-export-' . $reason, error: $error);
		}

		$this->notify(request: $request, subject: 'project_export_failed', parameters: ['project' => $request->title, 'reason' => $reason]);
	}//end fail()

	/**
	 * Put a failed export on OpenRegister's audit trail, never failing over it.
	 *
	 * @param ExportRequest $request The export.
	 * @param string        $rule    The audit rule naming the failure.
	 * @param \Throwable    $error   What went wrong.
	 *
	 * @return void
	 */
	private function recordFailure(ExportRequest $request, string $rule, \Throwable $error): void {
		try {
			$this->recorder()->recordRefused(
				profile: ProjectExportService::PROFILE,
				rule: $rule,
				reason: $error->getMessage(),
				actorId: $request->user->getUID()
			);
		} catch (\Throwable $e) {
			$this->warn(message: self::LOG_NOT_RECORDED, context: ['project' => $request->projectId, 'exception' => $e->getMessage()]);
		}
	}//end recordFailure()

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
			$this->warn(message: self::LOG_NOT_REMOVED, context: ['exception' => $e->getMessage()]);
		}
	}//end remove()

	/**
	 * Tell the requester, never failing the job over it.
	 *
	 * @param ExportRequest        $request    The export.
	 * @param string               $subject    The notification subject.
	 * @param array<string, mixed> $parameters The subject parameters.
	 *
	 * @return void
	 */
	private function notify(ExportRequest $request, string $subject, array $parameters): void {
		try {
			$notifications = $this->get(class: INotificationManager::class);
			$notification  = $notifications->createNotification();
			$notification->setApp(AppIdentity::APP_ID)
				->setUser($request->user->getUID())
				->setDateTime($this->time->getDateTime())
				->setObject('project', $request->projectId)
				->setSubject($subject, $parameters);
			$notifications->notify($notification);
		} catch (\Throwable $e) {
			$this->warn(message: self::LOG_NOT_NOTIFIED, context: ['project' => $request->projectId, 'exception' => $e->getMessage()]);
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
