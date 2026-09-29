<?php

/**
 * Planninq ProjectTreeService
 *
 * Reads the parent chain of projects: a programme holds projects, a project
 * holds subprojects, and no deeper. A project can never sit under itself or
 * under one of its own subprojects.
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
class ProjectTreeService {

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
	 * A guard against a chain that already loops in stored data.
	 *
	 * @var int
	 */
	private const WALK_LIMIT = 50;

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership Reads projects as the system.
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
}//end class
