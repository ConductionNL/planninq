<?php

/**
 * Planninq forge link id repair command
 *
 * The planninq:forge-links:repair-ids command lists the stored forge ids that
 * are not in canonical form; with --apply it rewrites them.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Command;

use OCA\Planninq\Service\ForgeLinkIdRepair;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dry run by default: it shows what would change and writes nothing until --apply.
 *
 * @spec openspec/specs/code-forge-links/spec.md#requirement-forge-activity-that-names-a-task-key-is-linked-through-integriq
 */
class RepairForgeLinkIds extends Command {

	/**
	 * Constructor.
	 *
	 * @param ForgeLinkIdRepair $repair Plans and applies the rewrites.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly ForgeLinkIdRepair $repair,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Name, description and the --apply option.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	protected function configure(): void {
		$this->setName(name: 'planninq:forge-links:repair-ids');
		$this->setDescription(description: 'List stored forge ids that are padded or in another case; rewrite them with --apply.');
		$this->addOption(
			name: 'apply',
			shortcut: null,
			mode: InputOption::VALUE_NONE,
			description: 'Rewrite the listed forge ids. Without it nothing is written.'
		);
	}//end configure()

	/**
	 * List the plan, and apply it when asked.
	 *
	 * @param InputInterface  $input  Console input.
	 * @param OutputInterface $output Console output.
	 *
	 * @return int 0 when nothing failed, 1 when a rewrite was refused.
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$apply  = ($input->getOption('apply') === true);
		$counts = ['would rewrite' => 0, 'rewritten' => 0, 'collides' => 0, 'failed' => 0];
		foreach ($this->repair->plan() as $step) {
			$outcome = $this->outcome(step: $step, apply: $apply);
			$counts[$outcome]++;
			$output->writeln(sprintf('%s %s: "%s" -> "%s" (%s)', $step['task'], $step['id'], $step['from'], $step['to'], $outcome));
		}

		$output->writeln(
			sprintf(
				'Would rewrite %d, rewrote %d forge id(s); %d collide(s) with a link already on the task and are left; %d failed.',
				$counts['would rewrite'],
				$counts['rewritten'],
				$counts['collides'],
				$counts['failed']
			)
		);

		return (int)($counts['failed'] > 0);
	}//end execute()

	/**
	 * What happens to one planned link: collides, would rewrite (a dry run), rewritten or failed.
	 *
	 * @param array{id:string,from:string,to:string,collides:bool,data:array<string,mixed>} $step  One entry of the plan.
	 * @param bool                                                              $apply Whether to write.
	 *
	 * @return string
	 */
	private function outcome(array $step, bool $apply): string {
		if ($step['collides'] === true) {
			return 'collides';
		}

		if ($apply === false) {
			return 'would rewrite';
		}

		if ($this->repair->apply(step: $step) === true) {
			return 'rewritten';
		}

		return 'failed';
	}//end outcome()
}//end class
