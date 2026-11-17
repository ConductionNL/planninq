<?php

/**
 * Planninq WorkTypeRenameJob
 *
 * Queued when an admin renames a work type and asks to rename it on the
 * existing entries too: writes the new name on every entry with the old one.
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
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\WorkTypeService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Renames a work type on the existing time entries.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
class WorkTypeRenameJob extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory    $time      The time factory the job base needs.
	 * @param WorkTypeService $workTypes Performs the rename.
	 */
	public function __construct(
		ITimeFactory $time,
		private WorkTypeService $workTypes,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Rename the work type named in the argument.
	 *
	 * @param mixed $argument ['from' => old name, 'to' => new name].
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	protected function run($argument): void {
		$this->renameFromArgument(argument: $argument);
	}//end run()

	/**
	 * Rename the work type named in the argument; a malformed argument renames nothing.
	 *
	 * @param mixed $argument ['from' => old name, 'to' => new name].
	 *
	 * @return int The number of entries renamed.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function renameFromArgument(mixed $argument): int {
		$rename = $this->workTypes->rename(value: $argument);
		if ($rename === null) {
			return 0;
		}

		return $this->workTypes->renameEntries(from: $rename['from'], to: $rename['to']);
	}//end renameFromArgument()
}//end class
