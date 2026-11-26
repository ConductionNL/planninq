<?php

/**
 * Planninq LoggedHoursMover
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Books the hours of one planninq `plannedTimeEntry` on humaniq's
 * `TimeEntry`, the owner of logged hours, and points the planninq row at it.
 * Shared by the MoveLoggedHoursToHumaniq repair step (every existing row) and
 * LoggedHoursHandoverListener (a row created with its hours on it), so both
 * produce the same humaniq entry for the same row.
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
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Kernel\Contracts\Value\SavePolicy;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Moves the hours of one planninq time entry onto a humaniq `TimeEntry`.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
class LoggedHoursMover {

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
	 * OpenRegister's ObjectService, resolved at runtime.
	 *
	 * @var string
	 */
	public const OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService at runtime.
	 * @param LoggerInterface    $logger    Records a row that could not move.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Move one row's hours, resolving OpenRegister's ObjectService first.
	 *
	 * @param array{id: string, data: array<string,mixed>} $row The planninq row.
	 *
	 * @return bool True when the row now references its humaniq entry.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
	 */
	public function moveRow(array $row): bool {
		try {
			$objectService = $this->container->get(self::OBJECT_SERVICE);
		} catch (Throwable $exception) {
			$this->logger->error(
				'Planninq: OpenRegister could not be reached to move the hours of a time entry to humaniq; the next upgrade tries again',
				['object' => $row['id'], 'exception' => $exception->getMessage()]
			);
			return false;
		}

		return $this->move(objectService: $objectService, row: $row);
	}//end moveRow()

	/**
	 * Whether a planninq row still has hours to move: it references no humaniq
	 * entry yet and carries a positive `duration`.
	 *
	 * @param array<string,mixed> $data The planninq row.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
	 */
	public function hasHoursToMove(array $data): bool {
		if ($this->referenceId(value: ($data['timeEntry'] ?? null)) !== '') {
			return false;
		}

		return ((float)($data['duration'] ?? 0) > 0);
	}//end hasHoursToMove()

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
			'hours' => round(((float)($data['duration'] ?? 0)) / 60.0, 2),
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
	 * Create the humaniq entry for one row and point the row at it.
	 *
	 * The humaniq entry's uuid is derived from the planninq row, so a run that
	 * stopped between the two writes updates it next time instead of booking
	 * the hours twice. A failure is logged and leaves the row unreferenced, so
	 * the repair step tries it again on the next upgrade.
	 *
	 * @param object                                       $objectService OpenRegister's ObjectService.
	 * @param array{id: string, data: array<string,mixed>} $row           The planninq row.
	 *
	 * @return bool True when the row now references its humaniq entry.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
	 */
	public function move(object $objectService, array $row): bool {
		try {
			$created = $objectService->saveObject(
				object: $this->ownerEntry(data: $row['data']),
				options: new ObjectSaveOptions(
					target: new ObjectFindTarget(register: self::OWNER_REGISTER, schema: self::OWNER_SCHEMA),
					uuid: self::ownerUuid(plannedId: $row['id'])
				),
				_rbac: false,
				_multitenancy: false
			);
			$ownerId = (new ObjectRows())->rowId(row: $created);
			if ($ownerId === '') {
				throw new RuntimeException('humaniq answered the time entry without an id');
			}

			$data = $row['data'];
			$data['timeEntry'] = $ownerId;
			$objectService->saveObject(
				object: $data,
				options: new ObjectSaveOptions(
					target: new ObjectFindTarget(register: self::REGISTER, schema: self::SCHEMA),
					uuid: $row['id'],
					policy: new SavePolicy(silent: true)
				),
				_rbac: false,
				_multitenancy: false
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
	 * A reference as a uuid string: a plain string, or a resolved object
	 * carrying its id.
	 *
	 * @param mixed $value The reference.
	 *
	 * @return string The uuid, or ''.
	 *
	 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
	 */
	public function referenceId(mixed $value): string {
		if (is_string($value) === true) {
			return trim($value);
		}

		if (is_array($value) === true) {
			return (string)($value['@self']['id'] ?? ($value['id'] ?? ''));
		}

		return '';
	}//end referenceId()
}//end class
