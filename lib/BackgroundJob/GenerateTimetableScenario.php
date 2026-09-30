<?php

/**
 * Planninq Generate Timetable Scenario job
 *
 * One time-boxed generator step for one scenario; it queues itself again while
 * the run improves and the time budget lasts, so no request waits on the solver.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\TimetableGenerationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;

/**
 * Runs one step of a timetable scenario run.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.2
 */
class GenerateTimetableScenario extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory               $time       The clock.
	 * @param TimetableGenerationService $generation Runs a step.
	 * @param IJobList                   $jobList    Queues the next step.
	 *
	 * @return void
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly TimetableGenerationService $generation,
		private readonly IJobList $jobList,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Run the step.
	 *
	 * @param mixed $argument `['scenario' => id]`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.2
	 */
	protected function run($argument): void {
		$this->runStep(argument: $argument);
	}//end run()

	/**
	 * Run one step and queue the next one when the run should go on; whether it was queued.
	 *
	 * @param mixed $argument `['scenario' => id]`.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.2
	 */
	public function runStep(mixed $argument): bool {
		$id = '';
		if (is_array($argument) === true) {
			$id = (string)($argument['scenario'] ?? '');
		}

		if ($id === '' || $this->generation->step(id: $id) === false) {
			return false;
		}

		$this->jobList->add(self::class, ['scenario' => $id]);
		return true;
	}//end runStep()
}//end class
