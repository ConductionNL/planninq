<?php

/**
 * Planninq TaskReportReaderService
 *
 * Works out who reads a shared report, and keeps that in step. A report
 * shared with "everyone who can read its projects" carries the users
 * (`readers`) and groups (`readerGroups`) who read at least one of its
 * projects, because OpenRegister's rules cannot look into the projects. The
 * lists are written when the report is saved (TaskReportReadersListener) and
 * again when one of its projects changes who may read it
 * (ProjectMembershipSyncListener), so a removed member stops reading it.
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
 * @spec openspec/specs/custom-reports/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Who reads a shared report.
 *
 * @spec openspec/specs/custom-reports/spec.md
 */
class TaskReportReaderService {

	/**
	 * The report schema slug.
	 *
	 * @var string
	 */
	public const SCHEMA = 'taskReport';

	/**
	 * The `shared` value that opens a report to the readers of its projects.
	 *
	 * @var string
	 */
	private const SHARED_WITH_READERS = 'readers';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects and reports as the system.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService.
	 * @param IAppManager              $appManager Tells whether OpenRegister is installed.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The users and groups who read at least one of the report's projects, or none for a private report.
	 *
	 * @param array<string,mixed> $data The report.
	 *
	 * @return array{readers: array<int,string>, readerGroups: array<int,string>}
	 *
	 * @spec openspec/specs/custom-reports/spec.md
	 */
	public function readersOf(array $data): array {
		$users  = [];
		$groups = [];
		if (($data['shared'] ?? null) === self::SHARED_WITH_READERS) {
			foreach ($this->membership->normalise(members: ($data['projects'] ?? null)) as $projectId) {
				$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
				if ($project === null) {
					continue;
				}

				$lists  = $this->membership->childLists(project: $project);
				$users  = array_merge(
					$users,
					$lists['members'],
					$lists['viewers'],
					$this->membership->normalise(members: ($project[ProjectMembershipService::READERS_FIELD] ?? null))
				);
				$groups = array_merge($groups, $lists['memberGroups'], $lists['viewerGroups']);
			}
		}

		return [
			'readers'      => $this->membership->normalise(members: $users),
			'readerGroups' => $this->membership->normalise(members: $groups),
		];
	}//end readersOf()

	/**
	 * Rewrite the reader lists of every shared report that covers a project.
	 *
	 * @param string $projectId The project whose readers changed.
	 *
	 * @return int The number of reports written.
	 *
	 * @spec openspec/specs/custom-reports/spec.md
	 */
	public function syncProject(string $projectId): int {
		if ($projectId === '' || $this->appManager->isInstalled('openregister') === false) {
			return 0;
		}

		$written = 0;
		foreach ($this->membership->rows(schema: self::SCHEMA, filters: ['shared' => self::SHARED_WITH_READERS]) as $report) {
			if (in_array($projectId, $this->membership->normalise(members: ($report['data']['projects'] ?? null)), true) === false) {
				continue;
			}

			$lists = $this->readersOf(data: $report['data']);
			if ($this->membership->normalise(members: ($report['data']['readers'] ?? null)) === $lists['readers']
				&& $this->membership->normalise(members: ($report['data']['readerGroups'] ?? null)) === $lists['readerGroups']
			) {
				continue;
			}

			$written += $this->write(id: $report['id'], data: array_merge($report['data'], $lists));
		}

		return $written;
	}//end syncProject()

	/**
	 * Write one report as the system, silently; a refused write is logged.
	 *
	 * @param string              $id   The report UUID.
	 * @param array<string,mixed> $data The report data.
	 *
	 * @return int 1 when written, 0 when refused.
	 */
	private function write(string $id, array $data): int {
		unset($data['@self'], $data['id']);
		try {
			$this->container->get('OCA\\OpenRegister\\Service\\ObjectService')->saveObject(
				object: $data,
				register: ProjectMembershipService::REGISTER,
				schema: self::SCHEMA,
				uuid: $id,
				_rbac: false,
				_multitenancy: false,
				silent: true
			);
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: could not update who reads a shared report', ['report' => $id, 'exception' => $e->getMessage()]);
			return 0;
		}

		return 1;
	}//end write()
}//end class
