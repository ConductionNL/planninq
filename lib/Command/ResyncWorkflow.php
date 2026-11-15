<?php

/**
 * Planninq ResyncWorkflow
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * `occ planninq:workflow:resync <workflow>`: bring every project on a
 * workflow in step with it again, for when a sync failed halfway
 * (projects-templates-shared-workflow, task 2.3).
 *
 * @category Command
 * @package  OCA\Planninq\Command
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */

declare(strict_types=1);

namespace OCA\Planninq\Command;

use OCA\Planninq\Service\WorkflowColumnSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Re-runs the workflow column sync for one workflow.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
class ResyncWorkflow extends Command {

	/**
	 * Constructor.
	 *
	 * @param WorkflowColumnSync $sync The sync.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	public function __construct(
		private readonly WorkflowColumnSync $sync,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Name, description and the workflow argument.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	protected function configure(): void {
		$this->setName(name: 'planninq:workflow:resync');
		$this->setDescription(description: 'Bring the columns of every project on a workflow in step with it again.');
		$this->addArgument(name: 'workflow', mode: InputArgument::REQUIRED, description: 'The workflow uuid.');
	}//end configure()

	/**
	 * Run the sync and report it.
	 *
	 * @param InputInterface  $input  Console input.
	 * @param OutputInterface $output Console output.
	 *
	 * @return int 0 when every project was updated, 1 when one failed or the workflow has no projects to update.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$counts = $this->sync->syncWorkflow(workflowId: (string)$input->getArgument('workflow'));
		$output->writeln(
			sprintf(
				'Updated %d project(s), %d failed, %d task(s) moved out of removed columns.',
				$counts['projects'],
				$counts['failed'],
				$counts['moved']
			)
		);

		return (int)($counts['failed'] > 0);
	}//end execute()
}//end class
