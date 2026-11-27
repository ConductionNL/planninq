<?php

/**
 * Planninq ApplyRetentionCategories repair step
 *
 * The register import rewrites planninq's live schemas from the register
 * file, which names no selectielijst category. This step writes the admin's
 * pick back into them after the import (retention-on-planninq-records task
 * 2.1), as ApplyProjectPolicy does for the project reviewers. It never
 * throws, so an install is never aborted by it.
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
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-2.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\RetentionCategoryService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Writes the retention categories into the live schemas after an import.
 *
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
class ApplyRetentionCategories implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param RetentionCategoryService $retention Keeps the pick and writes it.
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function __construct(
		private RetentionCategoryService $retention,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function getName(): string {
		return 'Write the retention categories of Planninq records into their schemas';
	}//end getName()

	/**
	 * Write the pick; warn when a schema could not be written.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/tasks.md#task-2.1
	 */
	public function run(IOutput $output): void {
		if ($this->retention->apply() === false) {
			$output->warning('Planninq retention categories were not written to every schema; pick them again under Retention in the admin settings.');
		}
	}//end run()
}//end class
