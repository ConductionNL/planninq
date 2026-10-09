<?php

/**
 * Planninq WorkflowResync
 *
 * `occ planninq:workflow:resync <workflow-id>` re-runs the column sync for
 * every project on a workflow, for the projects a workflow change could not
 * reach the first time.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Command;

use OCA\Planninq\Service\WorkflowSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Re-runs the workflow column sync.
 */
class WorkflowResync extends Command {

	/**
	 * Constructor.
	 *
	 * @param WorkflowSyncService $sync Does the column sync.
	 */
	public function __construct(private WorkflowSyncService $sync) {
		parent::__construct();
	}//end __construct()

	/**
	 * Name the command and its argument.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
	 */
	protected function configure(): void {
		$this->setName(name: 'planninq:workflow:resync');
		$this->setDescription(description: 'Bring the columns of every project on a workflow back in step with it');
		$this->addArgument(name: 'workflow', mode: InputArgument::REQUIRED, description: 'The workflow UUID');
	}//end configure()

	/**
	 * Run the sync and report what it did.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int 0 when every project is in step, 1 when some failed.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$result = $this->sync->syncWorkflow(workflowId: (string)$input->getArgument('workflow'));
		$output->writeln('Projects in step: ' . $result['synced']);
		foreach ($result['failed'] as $project => $reason) {
			$output->writeln('<error>Project ' . $project . ' failed: ' . $reason . '</error>');
		}

		if ($result['failed'] !== []) {
			return 1;
		}

		return 0;
	}//end execute()
}//end class
