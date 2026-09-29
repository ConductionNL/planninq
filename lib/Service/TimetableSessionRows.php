<?php

/**
 * Planninq Timetable Session Rows
 *
 * Reads the rows OpenRegister's ObjectService answers (entities or arrays, a
 * paginated `results` block or a plain list) and projects a stored session
 * onto the contract's read shape. Kept apart from the timetable rules so each
 * class stays small enough to read in one go.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use Throwable;

/**
 * Normalises ObjectService rows and shapes sessions for reading.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
 */
class TimetableSessionRows {

	/**
	 * Fields returned as strings, empty when unset.
	 *
	 * @var string[]
	 */
	private const TEXT_FIELDS = ['externalRef', 'sourceSystem', 'subject', 'title', 'startsAt', 'endsAt'];

	/**
	 * Fields returned as strings, null when unset.
	 *
	 * @var string[]
	 */
	private const OPTIONAL_FIELDS = ['groupReference', 'cohortId', 'teacherReference', 'teacherUserId', 'roomReference', 'roomLabel'];

	/**
	 * Normalise an ObjectService result set to a plain list of rows.
	 *
	 * @param mixed $results The raw ObjectService return value.
	 *
	 * @return array<int,mixed>
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function listOf(mixed $results): array {
		if (is_array($results) === true && array_key_exists('results', $results) === true) {
			return array_values((array)$results['results']);
		}

		if (is_array($results) === true) {
			return array_values($results);
		}

		return [];
	}//end listOf()

	/**
	 * Extract the object id from an ObjectService row (entity or array).
	 *
	 * `is_callable()` rather than `method_exists()`: OpenRegister's entity
	 * answers its accessors through `__call()` (see TimelineController).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function idOf(mixed $row): string {
		if (is_object($row) === true) {
			return $this->entityId(entity: $row);
		}

		if (is_array($row) === true && isset($row['@self']['id']) === true) {
			return (string)$row['@self']['id'];
		}

		if (is_array($row) === true) {
			return (string)($row['id'] ?? '');
		}

		return '';
	}//end idOf()

	/**
	 * Extract the data array from an ObjectService row (entity or array).
	 *
	 * @param mixed $row An entity object or a plain array row.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 */
	public function dataOf(mixed $row): array {
		if (is_object($row) === true && is_callable([$row, 'getObject']) === true) {
			return (array)$row->getObject();
		}

		if (is_array($row) === true) {
			return $row;
		}

		return [];
	}//end dataOf()

	/**
	 * Project a stored row onto the contract's read shape.
	 *
	 * @param mixed $row An ObjectEntity or a plain array row.
	 *
	 * @return array<string,mixed>|null The session, or null when it has no id.
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-another-app-reads-sessions-through-a-typed-event-req-005
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-a-draft-lesson-is-readable-only-by-the-teacher-it-names-and-by-admins
	 */
	public function toReadShape(mixed $row): ?array {
		$id = $this->idOf(row: $row);
		if ($id === '') {
			return null;
		}

		$data = $this->dataOf(row: $row);
		$session = ['id' => $id];
		foreach (self::TEXT_FIELDS as $field) {
			$session[$field] = (string)($data[$field] ?? '');
		}

		foreach (self::OPTIONAL_FIELDS as $field) {
			$session[$field] = $this->optionalText(value: ($data[$field] ?? null));
		}

		if ($session['title'] === '') {
			$session['title'] = $session['subject'];
		}

		$session['status'] = 'scheduled';
		if (in_array(($data['status'] ?? 'scheduled'), ['cancelled', 'draft'], true) === true) {
			$session['status'] = $data['status'];
		}

		return $session;
	}//end toReadShape()

	/**
	 * A scalar as a non-empty string, else null.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string|null
	 */
	private function optionalText(mixed $value): ?string {
		if (is_scalar($value) === true && (string)$value !== '') {
			return (string)$value;
		}

		return null;
	}//end optionalText()

	/**
	 * Read an entity's id through its magic accessors.
	 *
	 * @param object $entity The entity.
	 *
	 * @return string
	 */
	private function entityId(object $entity): string {
		foreach (['getUuid', 'getId'] as $getter) {
			if (is_callable([$entity, $getter]) === false) {
				continue;
			}

			try {
				$value = $entity->$getter();
			} catch (Throwable $e) {
				continue;
			}

			if (is_scalar($value) === true && (string)$value !== '') {
				return (string)$value;
			}
		}

		return '';
	}//end entityId()
}//end class
