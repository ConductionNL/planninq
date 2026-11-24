<?php

/**
 * Planninq BackfillProjectTemplateFlag repair step
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Writes `isTemplate: false` on every project that carries no value for it.
 * The dashboard tiles count projects with `isTemplate: false`, and
 * OpenRegister's filters drop a row whose value is NULL, so projects made
 * before the property existed would fall out of every tile until this runs.
 * A project that already carries a value, true or false, is left alone, so
 * the step is idempotent. It never throws, since an exception under
 * `<install>` aborts the install.
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

use OCA\OpenRegister\Kernel\Contracts\Value;
use OCA\Planninq\Kernel\Contracts\ProjectMembershipServiceInterface;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Gives every project without a template flag the flag false.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
class BackfillProjectTemplateFlag implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface                $container  Resolves OpenRegister's ObjectService.
	 * @param ProjectMembershipServiceInterface $membership Reads the project rows and tells whether OpenRegister is installed.
	 * @param LoggerInterface                   $logger     The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private ProjectMembershipServiceInterface $membership,
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
		return 'Mark every Planninq project without a template flag as not a template';
	}//end getName()

	/**
	 * Write the flag on every project that lacks it; never throws.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
	 */
	public function run(IOutput $output): void {
		if ($this->membership->isAvailable() === false) {
			$output->warning('OpenRegister is not installed; skipping the Planninq template flag back-fill.');
			return;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$written       = 0;
			foreach ($this->membership->rows(schema: 'project', filters: []) as $project) {
				if (isset($project['data']['isTemplate']) === true) {
					continue;
				}

				$written += $this->writeFlag(objectService: $objectService, project: $project);
			}

			$output->info(sprintf('Marked %d project(s) as not a template.', $written));
		} catch (\Throwable $e) {
			$output->warning('Could not mark Planninq projects as not a template: ' . $e->getMessage());
			$this->logger->error(
				'Planninq: template flag back-fill failed; older projects stay out of the dashboard tiles until it runs again',
				['exception' => $e->getMessage()]
			);
		}
	}//end run()

	/**
	 * Store one project with the flag false, counting nothing on failure.
	 *
	 * @param object                                    $objectService OpenRegister's ObjectService.
	 * @param array{id:string,data:array<string,mixed>} $project       The project row.
	 *
	 * @return int 1 when written, 0 when not.
	 *
	 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
	 */
	private function writeFlag(object $objectService, array $project): int {
		$options = new Value\ObjectSaveOptions(
			target: new Value\ObjectFindTarget(register: 'planninq', schema: 'project'),
			uuid: $project['id'],
			policy: new Value\SavePolicy(silent: true)
		);
		try {
			$objectService->saveObject(object: ['isTemplate' => false] + $project['data'], options: $options, _rbac: false, _multitenancy: false);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: could not mark a project as not a template', ['project' => $project['id'], 'exception' => $e->getMessage()]);
			return 0;
		}

		return 1;
	}//end writeFlag()
}//end class
