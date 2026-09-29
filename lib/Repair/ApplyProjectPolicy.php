<?php

/**
 * Planninq ApplyProjectPolicy repair step
 *
 * The register import rewrites the live `project` schema from the register
 * file, which names admins as the only reviewers of project requests. This
 * step runs after the import and writes the reviewer groups of the saved
 * creation policy back in.
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
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\ProjectPolicySchemaService;
use OCA\Planninq\Service\SettingsService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Re-applies the project reviewers after the register import.
 */
class ApplyProjectPolicy implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param SettingsService            $settings     Knows the reviewer groups.
	 * @param ProjectPolicySchemaService $policySchema Writes them into the live schema.
	 */
	public function __construct(
		private SettingsService $settings,
		private ProjectPolicySchemaService $policySchema,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Write the reviewers of Planninq project requests into the project schema';
	}//end getName()

	/**
	 * Write the reviewers; never throws.
	 *
	 * @param IOutput $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function run(IOutput $output): void {
		if ($this->policySchema->apply(groups: $this->settings->reviewerGroups()) === false) {
			$output->warning('Planninq project reviewers were not written; admins still review project requests.');
		}
	}//end run()
}//end class
