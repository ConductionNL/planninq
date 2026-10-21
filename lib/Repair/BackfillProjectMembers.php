<?php

/**
 * Planninq BackfillProjectMembers repair step
 *
 * Fills the `members` list of every task, column, phase and planned time entry
 * from its project (planninq#681).
 *
 * Rows stored before this release have no list, and the new authorization rule
 * `{"members": {"$contains": "$userId"}}` would hide them from every member.
 * Registered after InitializeSettings in both `<post-migration>` and
 * `<install>`: the register import that adds the property has to run first,
 * and `<install>` is the block that ran for the planix to planninq rename.
 * Idempotent: an object already in step is not written. It never throws, since
 * an exception under `<install>` aborts the install.
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
 * @spec openspec/specs/projects.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\ProjectMembershipService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Back-fills the denormalised members list on existing project objects.
 *
 * @spec openspec/specs/projects.md
 */
class BackfillProjectMembers implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Computes and writes the members list.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The name shown while the step runs.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function getName(): string {
		return 'Fill the members list of Planninq tasks, columns, phases and time entries from their project';
	}//end getName()

	/**
	 * Bring every project's objects in step.
	 *
	 * @param IOutput $output Progress output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/projects.md
	 */
	public function run(IOutput $output): void {
		if ($this->membership->isAvailable() === false) {
			$output->warning('OpenRegister is not installed; skipping the Planninq members back-fill.');
			return;
		}

		try {
			$result = $this->membership->syncAll();
			$output->info(
				sprintf(
					'Filled the members list on %d object(s) across %d project(s).',
					$result['written'],
					$result['projects']
				)
			);
		} catch (\Throwable $e) {
			$output->warning('Could not fill the Planninq members lists: ' . $e->getMessage());
			$this->logger->error(
				'Planninq: members back-fill failed; members may not see existing tasks until it runs again',
				['exception' => $e->getMessage()]
			);
		}
	}//end run()
}//end class
