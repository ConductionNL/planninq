<?php

/**
 * Planninq WorkTypeRenameJob
 *
 * Queued by a work type rename: gives the time entries that carry the old name
 * the new one, as the system.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\WorkTypeRenameService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Renames the work type on existing time entries.
 */
class WorkTypeRenameJob extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory           $time    The time factory the job base needs.
	 * @param WorkTypeRenameService  $service Does the rename.
	 */
	public function __construct(
		ITimeFactory $time,
		private WorkTypeRenameService $service,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Rename on the entries named in the argument.
	 *
	 * @param mixed $argument ['from' => old name, 'to' => new name].
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	protected function run($argument): void {
		$this->renameEntries(argument: $argument);
	}//end run()

	/**
	 * Rename on the entries named in the argument.
	 *
	 * @param mixed $argument ['from' => old name, 'to' => new name].
	 *
	 * @return int The number of entries written.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function renameEntries(mixed $argument): int {
		$from = '';
		$to   = '';
		if (is_array($argument) === true) {
			$from = (string)($argument['from'] ?? '');
			$to   = (string)($argument['to'] ?? '');
		}

		if ($from === '' || $to === '' || $from === $to) {
			return 0;
		}

		return $this->service->apply(from: $from, to: $to);
	}//end renameEntries()
}//end class
