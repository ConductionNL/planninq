<?php

/**
 * Planninq MoveLoggedHoursToHumaniq repair step.
 *
 * Logged hours live once, on humaniq's `TimeEntry`, so project reports and
 * payroll read the same number. Every existing `plannedTimeEntry` that does
 * not reference one yet gets one humaniq `TimeEntry` carrying its hours, and
 * a reference to it. Its rate stays in planninq.
 *
 * @category Repair
 * @package  OCA\Planninq\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @link https://www.planninq.app
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
 */

declare(strict_types=1);

namespace OCA\Planninq\Repair;

use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves the hours of every planninq time entry onto a humaniq `TimeEntry`.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
 */
class MoveLoggedHoursToHumaniq implements IRepairStep {

	/**
	 * Planninq's register slug.
	 *
	 * @var string
	 */
	public const REGISTER = 'planninq';

	/**
	 * Planninq's time entry schema slug, holding the rate and the reference.
	 *
	 * @var string
	 */
	public const SCHEMA = 'plannedTimeEntry';

	/**
	 * Humaniq's register slug, the owner of the hours.
	 *
	 * @var string
	 */
	public const OWNER_REGISTER = 'humaniq';

	/**
	 * Humaniq's time entry schema slug.
	 *
	 * @var string
	 */
	public const OWNER_SCHEMA = 'TimeEntry';

	/**
	 * The `<app>:<schema>` humaniq records a planninq task under. humaniq's
	 * `domainObjectType` names the owning app and schema (ADR-107 decision 6),
	 * so a bare `task` would not say which app to ask.
	 *
	 * @var string
	 */
	public const DOMAIN_OBJECT_TYPE = 'planninq:task';

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager Tells whether OpenRegister and humaniq are there.
	 * @param ContainerInterface $container  Resolves OpenRegister's ObjectService at runtime.
	 * @param LoggerInterface    $logger     Records a row that could not move.
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Human-readable step name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
	 */
	public function getName(): string {
		return 'Move logged hours to humaniq';
	}//end getName()

	/**
	 * Give every unmoved planninq time entry a humaniq `TimeEntry`.
	 *
	 * A row that already references one is left alone, so a second run
	 * creates nothing. Without humaniq the hours stay where they are, and the
	 * step says so.
	 *
	 * @param IOutput $output Repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
	 */
	public function run(IOutput $output): void {
		if ($this->appManager->isInstalled('openregister') === false) {
			$output->info('OpenRegister is not installed; no logged hours to move.');
			return;
		}

		if ($this->appManager->isEnabledForAnyone('humaniq') === false) {
			$output->warning('humaniq is not enabled; logged hours stay in planninq until it is, and this step moves them then.');
			return;
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$rows = $this->rows(objectService: $objectService);
		} catch (Throwable $exception) {
			$output->warning('Could not read the planninq time entries: ' . $exception->getMessage());
			return;
		}

		$moved = 0;
		$failed = 0;
		foreach ($rows as $row) {
			if ($this->referenceId(value: ($row['data']['timeEntry'] ?? null)) !== '') {
				continue;
			}

			if ($this->move(objectService: $objectService, row: $row) === true) {
				$moved++;
				continue;
			}

			$failed++;
		}

		$output->info(sprintf('Moved the hours of %d time entr(y/ies) to humaniq; %d could not be moved.', $moved, $failed));
	}//end run()

	/**
	 * The humaniq `TimeEntry` one planninq row becomes.
	 *
	 * Minutes become hours, the task becomes the polymorphic domain object
	 * pair, and the project stays a plain string (ADR-062 rule 7).
	 *
	 * @param array<string,mixed> $data The planninq row.
	 *
	 * @return array<string,mixed> The humaniq time entry.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
	 */
	public function ownerEntry(array $data): array {
		$entry = [
			'date' => (string)($data['date'] ?? ''),
			'hours' => round(((float)($data['duration'] ?? 0)) / 60, 2),
			'userId' => (string)($data['user'] ?? ''),
			'description' => (string)($data['description'] ?? ''),
			'billable' => (bool)($data['billable'] ?? false),
		];

		$project = $this->referenceId(value: ($data['project'] ?? null));
		if ($project !== '') {
			$entry['projectId'] = $project;
		}

		$task = $this->referenceId(value: ($data['task'] ?? null));
		if ($task !== '') {
			$entry['domainObjectType'] = self::DOMAIN_OBJECT_TYPE;
			$entry['domainObjectRef'] = $task;
		}

		return $entry;
	}//end ownerEntry()

	/**
	 * The uuid of the humaniq entry one planninq row moves to: the same row
	 * always gives the same uuid.
	 *
	 * @param string $plannedId The planninq row's uuid.
	 *
	 * @return string A version 4 shaped uuid.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
	 */
	public static function ownerUuid(string $plannedId): string {
		$hex = md5('planninq-hours:' . $plannedId);
		$variant = dechex((hexdec($hex[16]) & 0x3) | 0x8);

		$parts = [
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			'4' . substr($hex, 13, 3),
			$variant . substr($hex, 17, 3),
			substr($hex, 20, 12),
		];

		return implode('-', $parts);
	}//end ownerUuid()

	/**
	 * Every planninq time entry, read as the system: an upgrade has no user.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 *
	 * @return array<int, array{id: string, data: array<string,mixed>}> The rows.
	 */
	private function rows(object $objectService): array {
		$results = $objectService->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: self::SCHEMA,
			filters: [],
			_rbac: false,
			_multitenancy: false
		);

		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$rows = [];
		foreach ((array)$results as $row) {
			$id = $this->rowId(row: $row);
			if ($id !== '') {
				$rows[] = ['id' => $id, 'data' => $this->rowData(row: $row)];
			}
		}

		return $rows;
	}//end rows()

	/**
	 * Create the humaniq entry for one row and point the row at it.
	 *
	 * @param object                                         $objectService OpenRegister's ObjectService.
	 * @param array{id: string, data: array<string,mixed>}   $row           The planninq row.
	 *
	 * @return bool True when the row now references its humaniq entry.
	 */
	private function move(object $objectService, array $row): bool {
		try {
			// The humaniq entry's uuid is derived from the planninq row, so a
			// run that stopped between the two writes updates it next time
			// instead of booking the hours twice.
			$created = $objectService->saveObject(
				object: $this->ownerEntry(data: $row['data']),
				register: self::OWNER_REGISTER,
				schema: self::OWNER_SCHEMA,
				uuid: self::ownerUuid(plannedId: $row['id']),
				_rbac: false,
				_multitenancy: false
			);
			$ownerId = $this->rowId(row: $created);
			if ($ownerId === '') {
				throw new \RuntimeException('humaniq answered the time entry without an id');
			}

			$data = $row['data'];
			$data['timeEntry'] = $ownerId;
			$objectService->saveObject(
				object: $data,
				register: self::REGISTER,
				schema: self::SCHEMA,
				uuid: $row['id'],
				_rbac: false,
				_multitenancy: false,
				silent: true
			);
		} catch (Throwable $exception) {
			$this->logger->error(
				'Planninq: the hours of a time entry could not be moved to humaniq; the next upgrade tries again',
				['object' => $row['id'], 'exception' => $exception->getMessage()]
			);
			return false;
		}//end try

		return true;
	}//end move()

	/**
	 * The uuid of a search row or a saved entity.
	 *
	 * @param mixed $row The row.
	 *
	 * @return string The uuid, or ''.
	 */
	private function rowId(mixed $row): string {
		// `is_callable()`, not `method_exists()`: an OpenRegister entity serves
		// getUuid() through `__call()`.
		if (is_object($row) === true && is_callable([$row, 'getUuid']) === true) {
			return (string)($row->getUuid() ?? '');
		}

		if (is_array($row) === true) {
			return (string)($row['@self']['id'] ?? ($row['id'] ?? ''));
		}

		return '';
	}//end rowId()

	/**
	 * The data of a search row, entity or array.
	 *
	 * @param mixed $row The row.
	 *
	 * @return array<string,mixed> The object data.
	 */
	private function rowData(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			unset($row['@self']);
			return $row;
		}

		return [];
	}//end rowData()

	/**
	 * A reference as a uuid string: a plain string, or a resolved object
	 * carrying its id.
	 *
	 * @param mixed $value The reference.
	 *
	 * @return string The uuid, or ''.
	 */
	private function referenceId(mixed $value): string {
		if (is_string($value) === true) {
			return trim($value);
		}

		if (is_array($value) === true) {
			return (string)($value['@self']['id'] ?? ($value['id'] ?? ''));
		}

		return '';
	}//end referenceId()
}//end class
