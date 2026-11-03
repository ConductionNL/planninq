<?php

/**
 * Planninq Timetable Row Rules
 *
 * The row rules of the school timetable: which fields a delivered lesson
 * carries and needs, which status it may have, and when a stored lesson
 * already holds what a delivery sends.
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
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Normalises, validates and compares timetable rows.
 *
 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
 */
final class TimetableRowRules {

	/**
	 * Fields a row must carry, besides the batch's own source system.
	 *
	 * @var string[]
	 */
	private const REQUIRED_FIELDS = ['externalRef', 'subject', 'startsAt', 'endsAt'];

	/**
	 * Every field a caller may set. `sourceSystem` comes from the batch and
	 * `importedAt` from planninq, so neither is copied from a row.
	 *
	 * @var string[]
	 */
	private const WRITABLE_FIELDS = [
		'externalRef',
		'subject',
		'title',
		'startsAt',
		'endsAt',
		'groupReference',
		'cohortId',
		'teacherReference',
		'teacherUserId',
		'roomReference',
		'roomLabel',
		'status',
		'courseId',
		'onlineMeetingUrl',
	];

	/**
	 * The fields compared as moments in time rather than as strings.
	 *
	 * @var string[]
	 */
	private const DATE_FIELDS = ['startsAt', 'endsAt'];

	/**
	 * The two states a lesson can be in.
	 *
	 * @var string[]
	 */
	private const STATUSES = ['draft', 'scheduled', 'cancelled'];

	/**
	 * Constructor.
	 *
	 * @param TimetableSessionRows $rows The stored-row reader, for cleared fields and course checks.
	 */
	public function __construct(
		private readonly TimetableSessionRows $rows,
	) {
	}//end __construct()

	/**
	 * Normalise and validate one raw row.
	 *
	 * @param mixed  $raw          The raw row.
	 * @param string $sourceSystem The batch's source system.
	 *
	 * @return array{row:array<string,mixed>}|array{rejection:array{externalRef:string|null,errorCode:string,errorMessage:string}}
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
	 */
	public function checked(mixed $raw, string $sourceSystem): array {
		if (is_array($raw) === false) {
			return ['rejection' => $this->rejection(externalRef: null, code: 'missing-fields', message: 'The row is not an object.')];
		}

		$row   = $this->normalise(raw: $raw, sourceSystem: $sourceSystem);
		$error = $this->validate(row: $row);
		if ($error !== null) {
			return ['rejection' => $this->rejection(externalRef: ($row['externalRef'] ?? null), code: $error['code'], message: $error['message'])];
		}

		return ['row' => $row];
	}//end checked()

	/**
	 * Whether a delivery would turn a published (scheduled or cancelled) lesson back into a draft.
	 *
	 * @param array<string,mixed> $stored   The stored session data.
	 * @param array<string,mixed> $incoming The normalised incoming row.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/timetable-draft-review/specs/timetable-draft-review/spec.md#requirement-a-later-delivery-never-turns-a-published-lesson-into-a-draft
	 */
	public function wouldUnpublish(array $stored, array $incoming): bool {
		return ($incoming['status'] ?? null) === 'draft' && ($stored['status'] ?? 'scheduled') !== 'draft';
	}//end wouldUnpublish()

	/**
	 * Copy the writable fields a row carries, trimmed, under the batch's source.
	 *
	 * Only keys present in the row are copied, so a later delivery that omits a
	 * field keeps the stored value. A row's own `sourceSystem` is ignored.
	 *
	 * @param array<string,mixed> $raw          The raw row.
	 * @param string              $sourceSystem The batch's source system.
	 *
	 * @return array<string,mixed> The normalised row.
	 */
	private function normalise(array $raw, string $sourceSystem): array {
		$row = ['sourceSystem' => $sourceSystem];

		foreach (self::WRITABLE_FIELDS as $field) {
			$value = $raw[$field] ?? null;
			if (is_scalar($value) === true) {
				$row[$field] = trim((string)$value);
			}
		}

		return $this->rows->withClearedFields(row: $row, raw: $raw);
	}//end normalise()

	/**
	 * Check a normalised row against the contract's row rules.
	 *
	 * @param array<string,mixed> $row The normalised row.
	 *
	 * @return array{code:string,message:string}|null The first problem found, or null.
	 */
	private function validate(array $row): ?array {
		$missing = array_values(
			array_filter(
				self::REQUIRED_FIELDS,
				static fn (string $field): bool => ($row[$field] ?? '') === ''
			)
		);

		if ($missing !== []) {
			return ['code' => 'missing-fields', 'message' => 'Missing required field(s): ' . implode(', ', $missing)];
		}

		return $this->timesError(row: $row) ?? $this->statusError(row: $row) ?? $this->rows->courseOrLinkError(row: $row);
	}//end validate()

	/**
	 * The problem with a row's lesson times: unreadable, or not ending after the start.
	 *
	 * @param array<string,mixed> $row The normalised row, with both times present.
	 *
	 * @return array{code:string,message:string}|null
	 */
	private function timesError(array $row): ?array {
		$start = strtotime($row['startsAt']);
		$end   = strtotime($row['endsAt']);
		if ($start === false || $end === false || $end <= $start) {
			return ['code' => 'invalid-dates', 'message' => 'The lesson times cannot be read, or the lesson does not end after it starts.'];
		}

		return null;
	}//end timesError()

	/**
	 * The problem with a row's status: one the contract does not know.
	 *
	 * @param array<string,mixed> $row The normalised row.
	 *
	 * @return array{code:string,message:string}|null
	 */
	private function statusError(array $row): ?array {
		if (isset($row['status']) === true && in_array($row['status'], self::STATUSES, true) === false) {
			return ['code' => 'invalid-status', 'message' => 'Status must be draft, scheduled or cancelled.'];
		}

		return null;
	}//end statusError()

	/**
	 * Whether every field the incoming row carries already holds that value.
	 *
	 * Times are compared as moments, so the same lesson written with another
	 * offset or format is still unchanged.
	 *
	 * @param array<string,mixed> $stored   The stored session data.
	 * @param array<string,mixed> $incoming The normalised incoming row.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
	 */
	public function isUnchanged(array $stored, array $incoming): bool {
		foreach ($incoming as $field => $value) {
			if ($this->fieldIsUnchanged(field: $field, current: ($stored[$field] ?? null), value: (string)$value) === false) {
				return false;
			}
		}

		return true;
	}//end isUnchanged()

	/**
	 * Whether one stored field already holds the incoming value.
	 *
	 * @param string $field   The field name.
	 * @param mixed  $current The stored value.
	 * @param string $value   The incoming value.
	 *
	 * @return bool
	 */
	private function fieldIsUnchanged(string $field, mixed $current, string $value): bool {
		if (in_array($field, self::DATE_FIELDS, true) === true) {
			return is_string($current) === true && strtotime($current) === strtotime($value);
		}

		if ($field === 'status') {
			return ($current ?? 'scheduled') === $value;
		}

		return is_scalar($current ?? '') === true && (string)($current ?? '') === $value;
	}//end fieldIsUnchanged()

	/**
	 * Keep only the session fields of a stored row, dropping OpenRegister metadata.
	 *
	 * @param array<string,mixed> $data The stored data.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-is-idempotent-by-source-and-occurrence-id-req-002
	 */
	public function storedFields(array $data): array {
		$fields = array_merge(self::WRITABLE_FIELDS, ['sourceSystem', 'importedAt']);
		return array_intersect_key($data, array_flip($fields));
	}//end storedFields()

	/**
	 * Build one rejection entry.
	 *
	 * @param mixed  $externalRef The row's occurrence id, when it has one.
	 * @param string $code        The contract's rejection code.
	 * @param string $message     A readable reason.
	 *
	 * @return array{externalRef:string|null,errorCode:string,errorMessage:string}
	 *
	 * @spec openspec/changes/school-timetable-target/specs/school-timetable/spec.md#requirement-upsert-validates-before-it-writes-req-003
	 */
	public function rejection(mixed $externalRef, string $code, string $message): array {
		$ref = null;
		if (is_scalar($externalRef) === true && (string)$externalRef !== '') {
			$ref = (string)$externalRef;
		}

		return ['externalRef' => $ref, 'errorCode' => $code, 'errorMessage' => $message];
	}//end rejection()
}//end class
