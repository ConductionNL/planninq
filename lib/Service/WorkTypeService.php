<?php

/**
 * Planninq Work Type Service
 *
 * Renaming a work type on the entries that already carry it: queued from the
 * admin settings when the admin asks for it, and run as the system in the
 * background, since the entries belong to many people.
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

use OCA\Planninq\BackgroundJob\WorkTypeRenameJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

/**
 * Queues and performs the rename of a work type on existing time entries.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
class WorkTypeService {

	use ReadsObjectRowId;

	/**
	 * The admin settings key that asks for a rename on existing entries.
	 *
	 * @var string
	 */
	public const RENAME_KEY = 'work_type_rename';

	/**
	 * The schema carrying the work type.
	 *
	 * @var string
	 */
	public const SCHEMA = 'plannedTimeEntry';

	/**
	 * The longest work type name accepted.
	 *
	 * @var int
	 */
	public const MAX_NAME_LENGTH = 100;

	/**
	 * Constructor.
	 *
	 * @param IJobList            $jobs    Runs the rename in the background.
	 * @param OpenRegisterObjects $objects OpenRegister's object service, when installed.
	 * @param LoggerInterface     $logger  The logger.
	 */
	public function __construct(
		private IJobList $jobs,
		private OpenRegisterObjects $objects,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Queue the rename the submitted admin settings ask for, if any.
	 *
	 * @param array<string,mixed> $data The submitted settings; `work_type_rename` is `{from, to}` or its JSON.
	 *
	 * @return bool True when a rename was queued.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function queueRename(array $data): bool {
		$rename = $this->rename(value: ($data[self::RENAME_KEY] ?? null));
		if ($rename === null) {
			return false;
		}

		$this->jobs->add(WorkTypeRenameJob::class, $rename);
		return true;
	}//end queueRename()

	/**
	 * A valid rename: two different non-empty names, or null.
	 *
	 * @param mixed $value `{from, to}` or its JSON.
	 *
	 * @return array{from:string,to:string}|null
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function rename(mixed $value): ?array {
		if (is_string($value) === true) {
			$value = json_decode($value, true);
		}

		if (is_array($value) === false || is_string($value['from'] ?? null) === false || is_string($value['to'] ?? null) === false) {
			return null;
		}

		$from = trim($value['from']);
		$to   = trim($value['to']);
		if ($from === '' || $to === '' || $from === $to || mb_strlen($to) > self::MAX_NAME_LENGTH) {
			return null;
		}

		return ['from' => $from, 'to' => $to];
	}//end rename()

	/**
	 * Write the new name on every time entry that carries the old one.
	 *
	 * Runs as the system (`_rbac: false`, all organisations) and silent: the
	 * admin asked for it, and only `workType` changes. One entry that refuses
	 * the write is logged and does not stop the rest.
	 *
	 * @param string $from The old name.
	 * @param string $to   The new name.
	 *
	 * @return int The number of entries renamed.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function renameEntries(string $from, string $to): int {
		$objectService = $this->objects->objectService();
		if ($objectService === null || $from === '' || $to === '' || $from === $to) {
			return 0;
		}

		$results = $objectService->searchObjectsBySlug(
			registerSlug: ProjectMembershipService::REGISTER,
			schemaSlug: self::SCHEMA,
			filters: ['workType' => $from],
			_rbac: false,
			_multitenancy: false
		);
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			$results = (array)$results['results'];
		}

		$renamed = 0;
		foreach ((array)$results as $row) {
			$renamed += $this->renameOne(objectService: $objectService, row: $row, from: $from, to: $to);
		}

		$this->logger->info('Planninq: renamed a work type on existing entries', ['from' => $from, 'to' => $to, 'entries' => $renamed]);

		return $renamed;
	}//end renameEntries()

	/**
	 * Rename one entry; 1 when written, 0 when skipped or refused.
	 *
	 * @param object $objectService OpenRegister's object service.
	 * @param mixed  $row           The entry as found.
	 * @param string $from          The old name.
	 * @param string $to            The new name.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	private function renameOne(object $objectService, mixed $row, string $from, string $to): int {
		$id   = $this->extractId(row: $row);
		$data = $this->data(row: $row);
		if ($id === '' || ($data['workType'] ?? null) !== $from) {
			return 0;
		}

		try {
			$objectService->saveObject(
				object: ['workType' => $to] + $data,
				register: ProjectMembershipService::REGISTER,
				schema: self::SCHEMA,
				uuid: $id,
				_rbac: false,
				_multitenancy: false,
				silent: true
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: could not rename the work type of an entry', ['object' => $id, 'exception' => $e->getMessage()]);
			return 0;
		}

		return 1;
	}//end renameOne()

	/**
	 * The object data of a found row.
	 *
	 * @param mixed $row The row: an entity or an array.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	private function data(mixed $row): array {
		if (is_object($row) === true && method_exists($row, 'getObject') === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			unset($row['@self']);
			return $row;
		}

		return [];
	}//end data()
}//end class
