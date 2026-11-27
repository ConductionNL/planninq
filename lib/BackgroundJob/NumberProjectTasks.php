<?php

/**
 * Planninq NumberProjectTasks
 *
 * Queued once when a project gets its first key: gives the project's keyless
 * tasks keys in creation order through the same counter new tasks use.
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
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\WorkItemKeyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Numbers the existing tasks of a project that just got a key.
 */
class NumberProjectTasks extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory       $time The time factory the job base needs.
	 * @param WorkItemKeyService $keys The counter.
	 */
	public function __construct(
		ITimeFactory $time,
		private WorkItemKeyService $keys,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Number the project named in the argument.
	 *
	 * @param mixed $argument ['project' => uuid].
	 *
	 * @return void
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
	 */
	protected function run($argument): void {
		$this->numberProject(argument: $argument);
	}//end run()

	/**
	 * Number the project named in the argument; a second run finds nothing to number.
	 *
	 * @param mixed $argument ['project' => uuid].
	 *
	 * @return int The number of tasks numbered.
	 *
	 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.3
	 */
	public function numberProject(mixed $argument): int {
		$projectId = '';
		if (is_array($argument) === true) {
			$projectId = (string)($argument['project'] ?? '');
		}

		if ($projectId === '') {
			return 0;
		}

		return $this->keys->numberTasks(projectId: $projectId);
	}//end numberProject()
}//end class
