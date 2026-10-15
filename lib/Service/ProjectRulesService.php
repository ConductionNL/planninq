<?php

/**
 * Planninq ProjectRulesService
 *
 * The two rules a project write must meet that OpenRegister cannot express:
 * a parent chain of three levels at most without cycles (a programme holds
 * projects, a project holds subprojects), and custom field values that fit
 * the fields an admin defined.
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
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

/**
 * Parent chain checks for projects.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 */

/**
 * Parent chain and custom field checks for project writes.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.2
 */
class ProjectRulesService {


	/**
	 * The deepest a chain may be: programme, project, subproject.
	 *
	 * @var int
	 */
	public const MAX_DEPTH = 3;

	/**
	 * The answer when the new parent is the project or one of its descendants.
	 *
	 * @var string
	 */
	public const CYCLE = 'cycle';

	/**
	 * The answer when the chain would grow deeper than MAX_DEPTH.
	 *
	 * @var string
	 */
	public const TOO_DEEP = 'tooDeep';

	/**
	 * The schema slug of a field definition.
	 *
	 * @var string
	 */
	public const FIELD_SCHEMA = 'projectField';

	/**
	 * A guard against a chain that already loops in stored data.
	 *
	 * @var int
	 */
	private const WALK_LIMIT = 50;

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects and field definitions as the system.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
	) {
	}//end __construct()

	/**
	 * Why a project may not sit under a parent, or null when it may.
	 *
	 * @param string $projectId The project, '' for one being created.
	 * @param string $parentId  The new parent.
	 *
	 * @return string|null CYCLE, TOO_DEEP or null.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
	 */
	public function refusal(string $projectId, string $parentId): ?string {
		if ($parentId === '') {
			return null;
		}

		$ancestors = $this->ancestors(parentId: $parentId);
		if ($projectId !== '' && ($parentId === $projectId || in_array($projectId, $ancestors, true) === true)) {
			return self::CYCLE;
		}

		// The parent's chain plus the project with the levels below it.
		$depth = count($ancestors) + $this->height(projectId: $projectId);
		if ($depth > self::MAX_DEPTH) {
			return self::TOO_DEEP;
		}

		return null;
	}//end refusal()

	/**
	 * The parent and its ancestors, nearest first.
	 *
	 * @param string $parentId The parent project.
	 *
	 * @return array<int,string>
	 */
	private function ancestors(string $parentId): array {
		$chain   = [];
		$current = $parentId;
		$steps   = 0;
		while ($current !== '' && in_array($current, $chain, true) === false && $steps < self::WALK_LIMIT) {
			$steps++;
			$chain[] = $current;
			$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $current);
			$current = $this->referenceId(value: ($project['parent'] ?? null));
		}

		return $chain;
	}//end ancestors()

	/**
	 * How many levels sit below a project, the project itself counted as one.
	 *
	 * @param string $projectId The project, '' for one being created.
	 * @param int    $guard     The walk depth so far.
	 *
	 * @return int
	 */
	private function height(string $projectId, int $guard=0): int {
		if ($projectId === '' || $guard > self::MAX_DEPTH) {
			return 1;
		}

		$below = 0;
		foreach ($this->membership->rows(schema: ProjectMembershipService::PROJECT_SCHEMA, filters: ['parent' => $projectId]) as $child) {
			$below = max($below, $this->height(projectId: $child['id'], guard: ($guard + 1)));
		}

		return (1 + $below);
	}//end height()

	/**
	 * A reference value as a UUID string.
	 *
	 * @param mixed $value A UUID string or a resolved object.
	 *
	 * @return string
	 */
	private function referenceId(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['@self']['id'] ?? ''));
		}

		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end referenceId()

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
		$fields  = $this->definitions();
		$unknown = $this->unknownKey(values: $values, stored: $stored, fields: $fields);
		if ($unknown !== null) {
			return sprintf('There is no project field "%s".', $unknown);
		}

		foreach ($fields as $key => $field) {
			$problem = $this->valueProblem(field: $field, value: ($values[$key] ?? null), label: (string)($field['label'] ?? $key));
			if ($problem !== null) {
				return $problem;
			}
		}

		return null;
	}//end problem()

	/**
	 * The first key no field defines, unless it is stored unchanged.
	 *
	 * @param array<string,mixed>               $values The new values.
	 * @param array<string,mixed>               $stored The stored values.
	 * @param array<string,array<string,mixed>> $fields The definitions by key.
	 *
	 * @return string|null
	 */
	private function unknownKey(array $values, array $stored, array $fields): ?string {
		foreach ($values as $key => $value) {
			$kept = (array_key_exists($key, $stored) === true && $stored[$key] === $value);
			if (isset($fields[(string)$key]) === false && $kept === false) {
				return (string)$key;
			}
		}

		return null;
	}//end unknownKey()

	/**
	 * What is wrong with one field's value, or null.
	 *
	 * @param array<string,mixed> $field The field definition.
	 * @param mixed               $value The value.
	 * @param string              $label The field's label.
	 *
	 * @return string|null
	 */
	private function valueProblem(array $field, mixed $value, string $label): ?string {
		if ($value === null || $value === '') {
			if (($field['required'] ?? false) === true) {
				return sprintf('%s is required.', $label);
			}

			return null;
		}

		if ($this->fits(field: $field, value: $value) === false) {
			return sprintf('%s does not take this value.', $label);
		}

		return null;
	}//end valueProblem()

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
		foreach ($this->membership->rows(schema: self::FIELD_SCHEMA, filters: []) as $row) {
			$data = $row['data'];
			$key  = (string)($data['key'] ?? '');
			if ($key !== '' && (($data['appliesTo'] ?? 'project') === 'project')) {
				$fields[$key] = $data;
			}
		}

		return $fields;
	}//end definitions()
}//end class
