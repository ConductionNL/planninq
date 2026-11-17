<?php

/**
 * Contract of ProjectMembershipService
 *
 * The public surface of ProjectMembershipService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Planninq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/planninq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Kernel\Contracts;

/**
 * Contract of ProjectMembershipService.
 *
 * @category Kernel
 * @package  OCA\Planninq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/planninq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ProjectMembershipServiceInterface {

	/**
	 * Schema slug of the project, whose membership is copied.
	 *
	 * @var string
	 */
	public const PROJECT_SCHEMA = 'project';

	/**
	 * The field holding the managers of a project's portfolio, copied like `members`.
	 *
	 * @var string
	 */
	public const READERS_FIELD = 'portfolioReaders';

	/**
	 * OpenRegister register slug of the planninq schemas.
	 *
	 * @var string
	 */
	public const REGISTER = 'planninq';

	/**
	 * Schemas that carry a copy of their project's members.
	 *
	 * @var array<int,string>
	 */
	public const SCOPED_SCHEMAS = [
		'task',
		'column',
		'projectPhase',
		'plannedTimeEntry',
		'projectLogEntry',
		'risk',
		'projectStatusReport',
		'projectRelease',
		'boardFilter',
		'forgeLink',
		'dependency',
		'wikiPage',
	];

	/**
	 * Every list a project hands down to its objects, keyed by the field the
	 * objects carry it in: `members` (users who write), `viewers` (users who
	 * read), `memberGroups` (the owning, manager and member groups, who write)
	 * and `viewerGroups` (groups who read).
	 *
	 * @param array<string,mixed> $project The project data.
	 *
	 * @return array<string,array<int,string>> Field to sorted, distinct ids.
	 *
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function childLists(array $project): array;

	/**
	 * Whether OpenRegister is installed, so there is anything to read or write.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function isAvailable(): bool;

	/**
	 * The members list of a project, cached for the request.
	 *
	 * @param string $projectId The project uuid.
	 *
	 * @return array<int,string>|null The user ids, or null when the project does not resolve.
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function membersOfProject(string $projectId): ?array;

	/**
	 * A stored `members` value as a sorted, distinct list, for comparison.
	 *
	 * @param mixed $members The stored value.
	 *
	 * @return array<int,string> The user ids.
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function normalise(mixed $members): array;

	/**
	 * One planninq object's stored data, read as the system, or null when it does not resolve.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.3
	 */
	public function objectData(string $schema, string $id): ?array;

	/**
	 * The owner of a project, or null when the project does not resolve.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-1.1
	 */
	public function ownerOfProject(string $projectId): ?string;

	/**
	 * The project an object of a scoped schema belongs to.
	 *
	 * A planned time entry may name only its task; its project is then the task's,
	 * and '' when the task it names does not resolve.
	 *
	 * @param string $schemaSlug The object's schema slug.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return string The project uuid, or '' when none resolves.
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function projectIdFor(string $schemaSlug, array $data): string;

	/**
	 * Every planninq object of one schema matching the filters, read with RBAC off.
	 *
	 * @param string              $schema  The schema slug.
	 * @param array<string,mixed> $filters OpenRegister search filters.
	 *
	 * @return array<int,array{id:string,data:array<string,mixed>}>
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-2.1
	 */
	public function rows(string $schema, array $filters): array;

	/**
	 * Bring every project's objects in step: the upgrade back-fill.
	 *
	 * An object whose project no longer exists is left as it is, so it stays
	 * visible to admins only.
	 *
	 * @return array{projects: int, written: int} Projects visited and objects written.
	 *
	 * @spec openspec/specs/projects/spec.md
	 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-2.3
	 */
	public function syncAll(): array;

	/**
	 * Write a project's members list to every object of that project that is out of step.
	 *
	 * Writes run as the system (`_rbac: false`, all organisations), silent (no
	 * audit row, event or notification per object) and without validation: the
	 * acting user changed the project, and only `members` changes on the object.
	 * One object that refuses the write is logged and does not stop the rest.
	 *
	 * The same writes keep `portfolioReaders` in step: pass that field and the
	 * managers of the project's portfolio.
	 *
	 * @param string            $projectId The project uuid.
	 * @param array<int,string> $members   The list, for `members` from membersFromProject().
	 * @param string            $field     `members`, or READERS_FIELD for the portfolio readers.
	 *
	 * @return int The number of objects written.
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function syncProjectMembers(string $projectId, array $members, string $field = 'members'): int;
}//end interface
