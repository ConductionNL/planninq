<?php

/**
 * Export request
 *
 * One queued project export while it runs: who asked, which project, the
 * title it is known by, and the local zip and the file it became.
 *
 * @category  BackgroundJob
 * @package   OCA\Planninq\BackgroundJob
 * @author    Conduction B.V. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCP\Files\File;
use OCP\IUser;

/**
 * The state of one export run, so the steps pass one value around.
 */
final class ExportRequest {

	/**
	 * The title the notifications name the project by; the id until the project is read.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * The local zip, once one was made.
	 *
	 * @var string|null
	 */
	public ?string $local = null;

	/**
	 * The file in the user's Files, once one was created.
	 *
	 * @var File|null
	 */
	public ?File $target = null;

	/**
	 * Constructor.
	 *
	 * @param IUser  $user      The requester.
	 * @param string $projectId The project uuid.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function __construct(
		public readonly IUser $user,
		public readonly string $projectId,
	) {
		$this->title = $projectId;
	}//end __construct()

	/**
	 * Take the project's title from what was gathered, when it has one.
	 *
	 * @param array<string, mixed> $content ProjectExportService::collect().
	 *
	 * @return array<string, mixed> The same content.
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function adopt(array $content): array {
		if (($content['title'] ?? '') !== '') {
			$this->title = (string)$content['title'];
		}

		return $content;
	}//end adopt()

	/**
	 * Remove the local zip, when there is one.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/project-data-export/tasks.md#task-1.3
	 */
	public function removeLocal(): void {
		if ($this->local !== null && $this->local !== '' && file_exists($this->local) === true) {
			@unlink($this->local);
		}
	}//end removeLocal()
}//end class
