<?php

/**
 * Contract of TaskScopeResolver
 *
 * The public surface of TaskScopeResolver that other components call, so they depend
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
 * Contract of TaskScopeResolver.
 *
 * @category Kernel
 * @package  OCA\Planninq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/planninq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TaskScopeResolverInterface {

	/**
	 * Decide whether a register/schema id pair is the Planninq `task` schema.
	 *
	 * @param string $registerId The OR register id from the event object.
	 * @param string $schemaId The OR schema id from the event object.
	 *
	 * @return bool Whether this is a Planninq task.
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function isPlanninqTask(string $registerId, string $schemaId): bool;

	/**
	 * The schema slug of a register/schema id pair, when the register is planninq.
	 *
	 * @param string $registerId The OR register id from the event object.
	 * @param string $schemaId The OR schema id from the event object.
	 *
	 * @return string The schema slug, or '' for another register or an unresolvable pair.
	 *
	 * @spec openspec/specs/tasks/spec.md
	 */
	public function planninqSchemaSlug(string $registerId, string $schemaId): string;

	/**
	 * Fetch the member ids of a project (plus its owner) from OpenRegister.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return string[] The project's member ids, or [] when unresolvable.
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function projectMembers(string $projectId): array;
}//end interface
