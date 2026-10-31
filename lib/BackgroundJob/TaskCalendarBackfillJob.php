<?php

/**
 * Planninq TaskCalendarBackfillJob
 *
 * Queued when a user switches on "Show my tasks in Nextcloud Tasks": exports
 * the tasks assigned to them once, outside the settings request
 * (planning-calendar).
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
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
 */

declare(strict_types=1);

namespace OCA\Planninq\BackgroundJob;

use OCA\Planninq\Service\TaskCalendarExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Exports one user's assigned tasks once.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
 */
class TaskCalendarBackfillJob extends QueuedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory              $time   The clock.
	 * @param TaskCalendarExportService $export The export.
	 */
	public function __construct(
		ITimeFactory $time,
		private TaskCalendarExportService $export,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Run the backfill.
	 *
	 * @param mixed $argument ['userId' => the user].
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
	 */
	protected function run($argument): void {
		$this->backfill(argument: $argument);
	}//end run()

	/**
	 * Export the user's assigned tasks.
	 *
	 * @param mixed $argument ['userId' => the user].
	 *
	 * @return int How many tasks were written.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.4
	 */
	public function backfill(mixed $argument): int {
		$userId = '';
		if (is_array($argument) === true) {
			$userId = (string)($argument['userId'] ?? '');
		}

		return $this->export->backfill(userId: $userId);
	}//end backfill()
}//end class
