<?php

/**
 * Planninq Task Attachments
 *
 * Stores a mail's attachments as files on its task.
 *
 * @category Service
 * @package  OCA\Planninq\Service\MailIntake
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Service\MailIntake;

use OCA\OpenRegister\Service\File\FileTarget;
use OCA\OpenRegister\Service\File\FileUpload;
use OCA\OpenRegister\Service\FileService;
use Psr\Container\ContainerInterface;

/**
 * Writes attachments through OpenRegister's object files, unshared.
 *
 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
 */
class TaskAttachments {

	/**
	 * @param ContainerInterface $container Resolves OpenRegister's file service.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * Attach one file to a task.
	 *
	 * @param string $taskId   The task uuid.
	 * @param string $fileName The file name.
	 * @param string $content  The file content.
	 *
	 * @return void
	 *
	 * @throws \Throwable When OpenRegister refuses the file.
	 *
	 * @spec openspec/changes/tasks-create-by-email/specs/task-mail-intake/spec.md#requirement-a-members-mail-to-a-project-address-becomes-a-task
	 */
	public function attach(string $taskId, string $fileName, string $content): void {
		$this->container->get(FileService::class)->addFile(
			target: new FileTarget(object: $taskId),
			upload: new FileUpload(fileName: $fileName, content: $content, share: false)
		);
	}//end attach()
}//end class
