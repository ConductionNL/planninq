<?php

/**
 * Planninq ProjectFieldService
 *
 * Checks a project's custom field values against the fields an admin defined:
 * text, number, date, choice, person or yes/no, optionally required.
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
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Validates project custom field values.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
 */
class ProjectFieldService {

	/**
	 * The schema slug of a field definition.
	 *
	 * @var string
	 */
	public const SCHEMA = 'projectField';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads the definitions as the system.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
	) {
	}//end __construct()

	/**
	 * The first problem with a set of values, or null when they fit their fields.
	 *
	 * A value whose field an admin removed may stay as it is stored, so a
	 * removed field that comes back finds its values again (design, risks).
	 *
	 * @param array<string,mixed> $values The project's customFields.
	 * @param array<string,mixed> $stored The stored customFields, [] on a create.
	 *
	 * @return string|null A message naming the field.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
	 */
	public function problem(array $values, array $stored=[]): ?string {
		$fields = $this->definitions();
		foreach ($values as $key => $value) {
			$kept = (array_key_exists($key, $stored) === true && $stored[$key] === $value);
			if (isset($fields[(string)$key]) === false && $kept === false) {
				return sprintf('There is no project field "%s".', (string)$key);
			}
		}

		foreach ($fields as $key => $field) {
			$label = (string)($field['label'] ?? $key);
			$value = ($values[$key] ?? null);
			if ($value === null || $value === '') {
				if (($field['required'] ?? false) === true) {
					return sprintf('%s is required.', $label);
				}

				continue;
			}

			if ($this->fits(field: $field, value: $value) === false) {
				return sprintf('%s does not take this value.', $label);
			}
		}

		return null;
	}//end problem()

	/**
	 * Whether a non-empty value fits its field's type.
	 *
	 * @param array<string,mixed> $field The field definition.
	 * @param mixed               $value The value.
	 *
	 * @return bool
	 */
	private function fits(array $field, mixed $value): bool {
		return match ((string)($field['type'] ?? 'text')) {
			'number' => (is_int($value) === true || is_float($value) === true),
			'date' => (is_string($value) === true && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1),
			'choice' => (is_string($value) === true && in_array($value, (array)($field['options'] ?? []), true) === true),
			'boolean' => is_bool($value),
			default => is_string($value),
		};
	}//end fits()

	/**
	 * The project field definitions keyed by their key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function definitions(): array {
		$fields = [];
		foreach ($this->membership->rows(schema: self::SCHEMA, filters: []) as $row) {
			$data = $row['data'];
			$key  = (string)($data['key'] ?? '');
			if ($key !== '' && (($data['appliesTo'] ?? 'project') === 'project')) {
				$fields[$key] = $data;
			}
		}

		return $fields;
	}//end definitions()
}//end class
