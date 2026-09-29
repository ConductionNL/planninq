<?php

/**
 * Planninq FinanceLineService
 *
 * Works out who may see and change a project's finance lines, finds the
 * project a line from the finance system belongs to, and keeps the copies on
 * the lines in step with their project.
 *
 * WHY COPIES ON THE LINE
 * ----------------------
 * OpenRegister matches a read rule against the object itself, never across
 * schemas. A finance line therefore carries who may read it (`financeReaders`:
 * the project owner plus the managers of its portfolio), who may change it
 * (`projectOwner`) and the project's `portfolio`, which the portfolio totals
 * filter on. Plain members are left out on purpose: a budget is not everyone's
 * business (design decision 1).
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
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Access copies and project matching for finance lines.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
 */
class FinanceLineService {

	/**
	 * The finance line schema slug.
	 *
	 * @var string
	 */
	public const SCHEMA = 'financeLine';

	/**
	 * The Nextcloud group whose members write lines from the finance system.
	 *
	 * @var string
	 */
	public const IMPORT_GROUP = 'planninq-finance-import';

	/**
	 * The `source` of a line from the finance system.
	 *
	 * @var string
	 */
	public const SOURCE_IMPORT = 'import';

	/**
	 * The fields planninq keeps on a line; no person writes them.
	 *
	 * @var array<int,string>
	 */
	public const KEPT_FIELDS = ['financeReaders', 'projectOwner', 'portfolio'];

	/**
	 * OpenRegister's ambient system-operation marker, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects and lines as the system.
	 * @param ContainerInterface       $container  Resolves OpenRegister's ObjectService for the system write.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The kept fields a line of this project carries.
	 *
	 * A line without a project (from the finance system, matching no project)
	 * carries empty copies, so only admins and the import group read it.
	 *
	 * @param string $projectId The project UUID, or ''.
	 *
	 * @return array{financeReaders: array<int,string>, projectOwner: string|null, portfolio: string|null}
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function keptFieldsFor(string $projectId): array {
		$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		if ($project === null) {
			return ['financeReaders' => [], 'projectOwner' => null, 'portfolio' => null];
		}

		$owner   = $this->text(value: ($project['owner'] ?? null));
		$readers = $this->membership->normalise(members: ($project[ProjectMembershipService::READERS_FIELD] ?? []));
		if ($owner !== '') {
			$readers = $this->membership->normalise(members: array_merge($readers, [$owner]));
		}

		$portfolio = $this->text(value: ($project['portfolio'] ?? null));

		return [
			'financeReaders' => $readers,
			'projectOwner'   => ($owner === '' ? null : $owner),
			'portfolio'      => ($portfolio === '' ? null : $portfolio),
		];
	}//end keptFieldsFor()

	/**
	 * The project whose key equals a finance project number, or '' when none or more than one does.
	 *
	 * @param string $projectKey The project number the finance system sent.
	 *
	 * @return string The project UUID, or ''.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.1
	 */
	public function projectIdForKey(string $projectKey): string {
		$projectKey = trim($projectKey);
		if ($projectKey === '') {
			return '';
		}

		$rows = $this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['key' => $projectKey]);
		if (count($rows) !== 1) {
			// None, or an ambiguous key: the line waits in the unmatched list.
			return '';
		}

		return $rows[0]['id'];
	}//end projectIdForKey()

	/**
	 * The imported line that already holds a finance line id, other than the line being written.
	 *
	 * @param string $externalRef The finance system's line id.
	 * @param string $ownUuid     The UUID of the line being written, '' on a create.
	 *
	 * @return string The UUID of the other line, or ''.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.1
	 */
	public function importedLineWith(string $externalRef, string $ownUuid): string {
		if ($externalRef === '') {
			return '';
		}

		$filters = ['source' => self::SOURCE_IMPORT, 'externalRef' => $externalRef];
		foreach ($this->membership->rows(schema: self::SCHEMA, filters: $filters) as $row) {
			if ($row['id'] !== $ownUuid) {
				return $row['id'];
			}
		}

		return '';
	}//end importedLineWith()

	/**
	 * Bring the kept fields of every line of a project in step with the project.
	 *
	 * Called after a project changes owner, portfolio or portfolio managers.
	 * Writes run as the system, silent and without validation, like the
	 * members list on the other project objects.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return int The number of lines written.
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.3
	 */
	public function syncProject(string $projectId): int {
		if ($projectId === '') {
			return 0;
		}

		$kept    = $this->keptFieldsFor(projectId: $projectId);
		$written = 0;
		foreach ($this->membership->rows(schema: self::SCHEMA, filters: ['project' => $projectId]) as $line) {
			if ($this->inStep(data: $line['data'], kept: $kept) === true) {
				continue;
			}

			$written += $this->write(uuid: $line['id'], data: array_merge($line['data'], $kept));
		}

		return $written;
	}//end syncProject()

	/**
	 * Whether a line's kept fields already equal the given ones.
	 *
	 * @param array<string,mixed>                                                                 $data The line data.
	 * @param array{financeReaders: array<int,string>, projectOwner: string|null, portfolio: string|null} $kept The kept fields.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function inStep(array $data, array $kept): bool {
		return $this->membership->normalise(members: ($data['financeReaders'] ?? null)) === $kept['financeReaders']
			&& ($this->text(value: ($data['projectOwner'] ?? null)) === (string)$kept['projectOwner'])
			&& ($this->text(value: ($data['portfolio'] ?? null)) === (string)$kept['portfolio']);
	}//end inStep()

	/**
	 * Whether OpenRegister runs this write as a trusted system operation.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/portfolio-finance/tasks.md#task-1.1
	 */
	public function isSystemOperation(): bool {
		$class = self::OR_SYSTEM_CONTEXT;

		return (class_exists($class) === true && $class::isActive() === true);
	}//end isSystemOperation()

	/**
	 * Write one line as the system.
	 *
	 * @param string              $uuid The line UUID.
	 * @param array<string,mixed> $data The line's new data.
	 *
	 * @return int 1 when written, 0 when the write failed.
	 */
	private function write(string $uuid, array $data): int {
		$class = self::OR_SYSTEM_CONTEXT;
		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$save          = static fn () => $objectService->saveObject(
				object: $data,
				register: ProjectMembershipService::REGISTER,
				schema: self::SCHEMA,
				uuid: $uuid,
				_rbac: false,
				_multitenancy: false,
				silent: true,
				_validation: false
			);
			if (class_exists($class) === true) {
				$class::run($save);
				return 1;
			}

			$save();
		} catch (\Throwable $e) {
			$this->logger->error(
				'Planninq: could not bring a finance line in step with its project; the wrong people may see it',
				['line' => $uuid, 'exception' => $e->getMessage()]
			);
			return 0;
		}//end try

		return 1;
	}//end write()

	/**
	 * A stored reference or uid as a plain string.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return string
	 */
	private function text(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['uuid'] ?? ''));
		}

		if (is_scalar($value) === false) {
			return '';
		}

		return (string)$value;
	}//end text()
}//end class
