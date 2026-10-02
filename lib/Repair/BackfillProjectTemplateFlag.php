<?php

/**
 * Planninq repair step: mark every existing project as not a template
 *
 * The dashboard's project figures count `isTemplate: false` (OpenRegister's
 * aggregation evaluates scalar equality only), and a project stored before
 * the flag existed has no value at all, so it would drop out of every
 * figure. This step writes `isTemplate: false` on each project that has no
 * value; a second run writes nothing.
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
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCA\Planninq\Service\ProjectMembershipService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes `isTemplate: false` on every project that has no value.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
class BackfillProjectTemplateFlag implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects with RBAC off.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name, shown by occ.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
	 */
	public function getName(): string {
		return 'Mark every Planninq project stored before templates existed as not a template';
	}//end getName()

	/**
	 * Write the flag where it is missing; never throws.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
	 */
	public function run(IOutput $output): void {
		if ($this->membership->isAvailable() === false) {
			$output->warning('OpenRegister is not installed; skipping the Planninq template flag.');
			return;
		}

		try {
			$output->info(sprintf('Marked %d project(s) as not a template.', $this->backfill()));
		} catch (\Throwable $e) {
			$output->warning('Could not mark Planninq projects as not a template: ' . $e->getMessage());
			$this->logger->error(
				'Planninq: template flag back-fill failed; those projects drop out of the dashboard figures until it runs again',
				['exception' => $e->getMessage()]
			);
		}
	}//end run()

	/**
	 * Write `isTemplate: false` on every project without a value.
	 *
	 * @return int The number of projects written.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
	 */
	public function backfill(): int {
		$objects = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
		$written = 0;
		foreach ($this->membership->rows(schema: 'project', filters: []) as $project) {
			if (is_bool($project['data']['isTemplate'] ?? null) === true) {
				continue;
			}

			$data = $project['data'];
			$data['isTemplate'] = false;
			try {
				$objects->saveObject(
					object: $data,
					register: ProjectMembershipService::REGISTER,
					schema: 'project',
					uuid: $project['id'],
					_rbac: false,
					_multitenancy: false,
					silent: true,
					_validation: false
				);
				$written++;
			} catch (\Throwable $e) {
				$this->logger->error('Planninq: could not mark a project as not a template', ['project' => $project['id'], 'exception' => $e->getMessage()]);
			}
		}

		return $written;
	}//end backfill()
}//end class
