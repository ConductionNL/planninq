<?php

/**
 * Planninq Project Health Service
 *
 * A project carries the statuses of its newest status report as `health`
 * fields, so the portfolio overview can read and count projects without
 * reading every report. This service decides which report is the newest and
 * writes its statuses onto the project, as the system.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Service\Object\ObjectFindTarget;
use OCA\OpenRegister\Service\Object\ObjectSaveOptions;
use OCA\OpenRegister\Service\Object\SavePolicy;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Copies the newest status report of a project onto the project.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
 */
class ProjectHealthService {

	/**
	 * Slug of the status report schema.
	 *
	 * @var string
	 */
	public const REPORT_SCHEMA = 'projectStatusReport';

	/**
	 * The six aspects, as the suffix of `status<Aspect>` on a report and `health<Aspect>` on a project.
	 *
	 * @var array<int,string>
	 */
	public const ASPECTS = ['Money', 'Organisation', 'Time', 'Information', 'Quality', 'Risk'];

	/**
	 * The statuses from best to worst.
	 *
	 * @var array<int,string>
	 */
	public const STATUSES = ['onTrack', 'atRisk', 'offTrack'];

	/**
	 * OpenRegister's system-operation scope, by name, so planninq has no compile-time dependency on it.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads reports and projects as the system.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService for the write.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The health fields of a project, empty statuses when it has no report.
	 *
	 * @param array<string,mixed> $project The project data.
	 *
	 * @return array<string,string|null> Keyed `health<Aspect>`, `healthOverall` and `healthDate`.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function healthOf(array $project): array {
		$health = [];
		foreach ($this->healthKeys() as $key) {
			$value = ($project[$key] ?? null);
			$health[$key] = null;
			if (is_string($value) === true && $value !== '') {
				$health[$key] = $value;
			}
		}

		return $health;
	}//end healthOf()

	/**
	 * The health fields a report hands to its project.
	 *
	 * The overall status is worked out here as well as by OpenRegister's
	 * calculation, so the copy never depends on the order of save listeners.
	 *
	 * @param array<string,mixed>|null $report The report data, null for none.
	 *
	 * @return array<string,string|null>
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function healthFromReport(?array $report): array {
		$health = array_fill_keys($this->healthKeys(), null);
		if ($report === null) {
			return $health;
		}

		$health = array_merge($health, $this->aspectHealth(report: $report));

		$date = ($report['reportDate'] ?? null);
		if (is_string($date) === true && $date !== '') {
			$health['healthDate'] = substr($date, 0, 10);
		}

		return $health;
	}//end healthFromReport()

	/**
	 * The health per aspect a report names with a known status, and the worst of them as overall.
	 *
	 * @param array<string,mixed> $report The report.
	 *
	 * @return array<string,string>
	 */
	private function aspectHealth(array $report): array {
		$health = [];
		$worst  = -1;
		foreach (self::ASPECTS as $aspect) {
			$status = ($report['status' . $aspect] ?? null);
			$rank   = array_search($status, self::STATUSES, true);
			if ($rank === false) {
				continue;
			}

			$health['health' . $aspect] = $status;
			$worst = max($worst, $rank);
		}

		if ($worst >= 0) {
			$health['healthOverall'] = self::STATUSES[$worst];
		}

		return $health;
	}//end aspectHealth()

	/**
	 * The newest report among the stored ones, with one report replaced or left out.
	 *
	 * Newest is the latest `reportDate`. On a tie the report being saved wins,
	 * then the stored one listed last.
	 *
	 * @param string                   $projectId The project UUID.
	 * @param string                   $reportId  The report being saved or deleted.
	 * @param array<string,mixed>|null $saving    The report's new data, null when it is being deleted.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function newestReport(string $projectId, string $reportId, ?array $saving): ?array {
		$newest = $this->newestStored(projectId: $projectId, reportId: $reportId);
		if ($saving !== null && $this->isNewer(report: $saving, than: $newest) === true) {
			$newest = $saving;
		}

		return $newest;
	}//end newestReport()

	/**
	 * The newest stored report of a project other than the given one; the later of two on the same date.
	 *
	 * @param string $projectId The project uuid.
	 * @param string $reportId  The report to leave out.
	 *
	 * @return array<string,mixed>|null
	 */
	private function newestStored(string $projectId, string $reportId): ?array {
		$newest = null;
		foreach ($this->membership->rows(schema: self::REPORT_SCHEMA, filters: ['project' => $projectId]) as $row) {
			if ($row['id'] !== $reportId && $this->isNewer(report: $row['data'], than: $newest) === true) {
				$newest = $row['data'];
			}
		}

		return $newest;
	}//end newestStored()

	/**
	 * Whether a report is dated on or after another, or there is no other.
	 *
	 * @param array<string,mixed>      $report The report.
	 * @param array<string,mixed>|null $than   The report to beat, or null.
	 *
	 * @return bool
	 */
	private function isNewer(array $report, ?array $than): bool {
		return $than === null || $this->dateOf(report: $report) >= $this->dateOf(report: $than);
	}//end isNewer()

	/**
	 * Write the newest report's statuses onto its project, when they differ.
	 *
	 * The write runs in OpenRegister's system-operation scope: the person who
	 * saves a report may not update the project itself, and the project guard
	 * lets only such a write change the health fields.
	 *
	 * @param string                   $projectId The project UUID.
	 * @param string                   $reportId  The report being saved or deleted.
	 * @param array<string,mixed>|null $saving    The report's new data, null when it is being deleted.
	 *
	 * @return bool True when the project was written.
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function refresh(string $projectId, string $reportId, ?array $saving): bool {
		$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		if ($project === null) {
			return false;
		}

		$health = $this->healthFromReport(report: $this->newestReport(projectId: $projectId, reportId: $reportId, saving: $saving));
		if ($this->healthOf(project: $project) === $health) {
			return false;
		}

		return $this->writeProject(projectId: $projectId, data: array_merge($project, $health));
	}//end refresh()

	/**
	 * Save a project's new data in OpenRegister's system-operation scope.
	 *
	 * @param string              $projectId The project UUID.
	 * @param array<string,mixed> $data      The whole project data to store.
	 *
	 * @return bool True when the project was written.
	 */
	private function writeProject(string $projectId, array $data): bool {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === false) {
			$this->logger->warning('Planninq: OpenRegister has no system-operation scope, so a project status was not copied', ['project' => $projectId]);
			return false;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$class::run(
				static fn () => $objectService->saveObject(
					object: $data,
					options: new ObjectSaveOptions(
						target: new ObjectFindTarget(register: ProjectMembershipService::REGISTER, schema: ProjectMembershipService::PROJECT_SCHEMA),
						uuid: $projectId,
						policy: new SavePolicy(silent: true, validation: false)
					),
					_rbac: false,
					_multitenancy: false
				)
			);
		} catch (Throwable $e) {
			$this->logger->error(
				'Planninq: could not copy the newest status report onto its project',
				['project' => $projectId, 'exception' => $e->getMessage()]
			);
			return false;
		}

		return true;
	}//end writeProject()

	/**
	 * Whether OpenRegister is running a system operation, such as this service's own copy or an import.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()

	/**
	 * The keys of the health fields on a project.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
	 */
	public function healthKeys(): array {
		$keys = [];
		foreach (self::ASPECTS as $aspect) {
			$keys[] = 'health' . $aspect;
		}

		$keys[] = 'healthOverall';
		$keys[] = 'healthDate';

		return $keys;
	}//end healthKeys()

	/**
	 * A report's date, for ordering.
	 *
	 * @param array<string,mixed> $report The report data.
	 *
	 * @return string
	 */
	private function dateOf(array $report): string {
		return substr((string)($report['reportDate'] ?? ''), 0, 10);
	}//end dateOf()
}//end class
