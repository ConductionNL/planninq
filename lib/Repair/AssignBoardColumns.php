<?php

/**
 * Planninq AssignBoardColumns repair step
 *
 * The board used to group cards by task status and never set a task's column.
 * It now groups them by column, so every existing task without one would drop
 * off the board. This step gives each project its default columns when it has
 * none, and puts every task that has no column and is not cancelled into the
 * column matching its status. Tasks that carry a column are left alone, so the
 * step is safe to run on every upgrade.
 *
 * @category Repair
 * @package  OCA\Planninq\Repair
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Places every column-less task of every project into a board column.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
 */
class AssignBoardColumns implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param BoardColumnService       $columns    Creates columns and places tasks.
	 * @param ProjectMembershipService $membership Tells whether OpenRegister is installed.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private BoardColumnService $columns,
		private ProjectMembershipService $membership,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name, shown by occ.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function getName(): string {
		return 'Put every Planninq task that has no board column into the column matching its status';
	}//end getName()

	/**
	 * Run the placement over every project; never throws.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function run(IOutput $output): void {
		if ($this->membership->isAvailable() === false) {
			$output->warning('OpenRegister is not installed; skipping the Planninq board column placement.');
			return;
		}

		try {
			$result = $this->columns->assignAll();
			$output->info(
				sprintf(
					'Placed %d task(s) in a board column across %d project(s); created %d column(s).',
					$result['assigned'],
					$result['projects'],
					$result['created']
				)
			);
		} catch (\Throwable $e) {
			$output->warning('Could not place Planninq tasks in board columns: ' . $e->getMessage());
			$this->logger->error(
				'Planninq: board column placement failed; tasks without a column stay off the board until it runs again',
				['exception' => $e->getMessage()]
			);
		}
	}//end run()
}//end class
