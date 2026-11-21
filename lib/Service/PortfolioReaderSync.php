<?php

/**
 * Planninq PortfolioReaderSync
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Writes a portfolio's managers to the `portfolioReaders` list of each of its
 * projects, and clears both the portfolio and the list on its projects when
 * the portfolio is deleted. Each project is written as the system inside
 * OpenRegister's system-operation scope, which raises no post-event, so the
 * list is then copied onto the project's own objects and finance lines here.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\SavePolicy;
use OCA\Planninq\Kernel\Contracts\ProjectMembershipServiceInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the portfolio readers of a portfolio's projects in step with its managers.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
class PortfolioReaderSync {

	/**
	 * OpenRegister's system-operation scope, absent on older OpenRegister versions.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipServiceInterface $membership Reads the projects and copies the list onto their objects.
	 * @param FinanceLineService                $finance    Keeps the access copies on the projects' finance lines.
	 * @param ContainerInterface                $container  Resolves OpenRegister's ObjectService at runtime.
	 * @param LoggerInterface                   $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipServiceInterface $membership,
		private FinanceLineService $finance,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Give every project of a portfolio the portfolio's new managers as its readers.
	 *
	 * @param string            $portfolioId The portfolio UUID.
	 * @param array<int,string> $managers    The normalised managers list.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function managersChanged(string $portfolioId, array $managers): void {
		foreach ($this->projectsOf(portfolioId: $portfolioId) as $project) {
			$this->writeProject(
				projectId: $project['id'],
				data: array_merge($project['data'], [ProjectMembershipServiceInterface::READERS_FIELD => $managers])
			);
		}
	}//end managersChanged()

	/**
	 * Take every project out of a portfolio that is being deleted.
	 *
	 * @param string $portfolioId The portfolio UUID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function release(string $portfolioId): void {
		foreach ($this->projectsOf(portfolioId: $portfolioId) as $project) {
			$this->writeProject(
				projectId: $project['id'],
				data: array_merge($project['data'], ['portfolio' => null, ProjectMembershipServiceInterface::READERS_FIELD => []])
			);
		}
	}//end release()

	/**
	 * The projects of a portfolio, read as the system.
	 *
	 * @param string $portfolioId The portfolio UUID.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 */
	private function projectsOf(string $portfolioId): array {
		return $this->membership->rows(schema: ProjectMembershipServiceInterface::PROJECT_SCHEMA, filters: ['portfolio' => $portfolioId]);
	}//end projectsOf()

	/**
	 * Write a project as the system and copy its readers onto its objects.
	 *
	 * The system write raises no post-event, so the objects are brought in step here.
	 *
	 * @param string              $projectId The project UUID.
	 * @param array<string,mixed> $data      The project's new data.
	 *
	 * @return void
	 */
	private function writeProject(string $projectId, array $data): void {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			$this->logger->warning('Planninq: OpenRegister has no system-operation scope; portfolio readers were not updated', ['project' => $projectId]);
			return;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$class::run(
				static fn () => $objectService->saveObject(
					object: $data,
					options: new ObjectSaveOptions(
						target: new ObjectFindTarget(
							register: ProjectMembershipServiceInterface::REGISTER,
							schema: ProjectMembershipServiceInterface::PROJECT_SCHEMA
						),
						uuid: $projectId,
						policy: new SavePolicy(silent: true, validation: false)
					),
					_rbac: false,
					_multitenancy: false
				)
			);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: could not update the portfolio readers of a project', ['project' => $projectId, 'exception' => $e->getMessage()]);
			return;
		}

		$field   = ProjectMembershipServiceInterface::READERS_FIELD;
		$written = $this->membership->syncProjectMembers(projectId: $projectId, members: ($data[$field] ?? []), field: $field);
		$written += $this->finance->syncProject(projectId: $projectId);
		$this->logger->info('Planninq: portfolio readers updated on a project and its objects', ['project' => $projectId, 'written' => $written]);
	}//end writeProject()
}//end class
