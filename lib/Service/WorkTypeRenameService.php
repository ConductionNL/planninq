<?php

/**
 * Planninq WorkTypeRenameService
 *
 * Renames a work type in the admin's list and, when asked, queues the job that
 * gives the time entries that carry the old name the new one.
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
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\BackgroundJob\WorkTypeRenameJob;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Work type renames.
 */
class WorkTypeRenameService {

	/**
	 * OpenRegister's system-operation scope.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig               $appConfig  Holds the work type list.
	 * @param ProjectMembershipService $membership Reads the time entries.
	 * @param DependencyRepository     $repository Resolves OpenRegister's ObjectService.
	 * @param IJobList                 $jobs       Queues the entry update.
	 * @param LoggerInterface          $logger     The logger.
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private ProjectMembershipService $membership,
		private DependencyRepository $repository,
		private IJobList $jobs,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Rename a work type in the list.
	 *
	 * @param string $from          The current name.
	 * @param string $to            The new name.
	 * @param bool   $updateEntries Whether entries that carry the old name get the new one.
	 *
	 * @return array{ok:bool,error?:string,queued?:bool} `error` is `unknown`, `empty` or `duplicate` when the rename is refused.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function rename(string $from, string $to, bool $updateEntries): array {
		$to   = trim($to);
		$list = $this->names();
		if (in_array($from, $list, true) === false) {
			return ['ok' => false, 'error' => 'unknown'];
		}

		if ($to === '') {
			return ['ok' => false, 'error' => 'empty'];
		}

		foreach ($list as $name) {
			if ($name !== $from && mb_strtolower($name) === mb_strtolower($to)) {
				return ['ok' => false, 'error' => 'duplicate'];
			}
		}

		$renamed = array_map(
			static function (string $name) use ($from, $to): string {
				if ($name === $from) {
					return $to;
				}

				return $name;
			},
			$list
		);
		$this->appConfig->setValueString(Application::APP_ID, SettingsService::WORK_TYPES_KEY, (string)json_encode($renamed));

		$queued = ($updateEntries === true && $from !== $to);
		if ($queued === true) {
			$this->jobs->add(WorkTypeRenameJob::class, ['from' => $from, 'to' => $to]);
		}

		return ['ok' => true, 'queued' => $queued];
	}//end rename()

	/**
	 * Give the entries that carry `$from` the name `$to`, as the system.
	 *
	 * @param string $from The old name.
	 * @param string $to   The new name.
	 *
	 * @return int The number of entries written.
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function apply(string $from, string $to): int {
		$written = 0;
		$service = $this->repository->objectService();
		$rows    = $this->membership->rows(schema: 'plannedTimeEntry', filters: ['workType' => $from]);
		$work    = function () use ($rows, $from, $to, $service, &$written): void {
			foreach ($rows as $row) {
				if (($row['data']['workType'] ?? null) !== $from) {
					continue;
				}

				try {
					$service->saveObject(
						object: array_merge($row['data'], ['workType' => $to]),
						register: ProjectMembershipService::REGISTER,
						schema: 'plannedTimeEntry',
						uuid: $row['id'],
						_rbac: false,
						_multitenancy: false,
						silent: true,
						_validation: false
					);
					$written++;
				} catch (\Throwable $e) {
					$this->logger->error('Planninq: a time entry kept its old work type', ['entry' => $row['id'], 'exception' => $e->getMessage()]);
				}
			}
		};

		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === true) {
			$class::run($work);
			return $written;
		}

		$work();

		return $written;
	}//end apply()

	/**
	 * The stored work type names.
	 *
	 * @return array<int,string>
	 */
	private function names(): array {
		$decoded = json_decode($this->appConfig->getValueString(Application::APP_ID, SettingsService::WORK_TYPES_KEY, '[]'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_filter($decoded, 'is_string'));
	}//end names()
}//end class
