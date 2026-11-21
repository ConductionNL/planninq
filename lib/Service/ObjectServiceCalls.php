<?php

/**
 * Planninq ObjectServiceCalls
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The find and save calls on OpenRegister's ObjectService that a controller
 * makes for one schema: a find scoped to that schema's table with RBAC on,
 * and a save as an explicit server-trusted write. Keeps the OpenRegister
 * option value objects out of the controllers that only need those two calls.
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
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;

/**
 * Scoped find and server-trusted save calls on OpenRegister's ObjectService.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
final class ObjectServiceCalls {
	/**
	 * Find one object in the table of a register and schema, with RBAC on.
	 *
	 * Scoped to the table: an unscoped find answers the first table holding
	 * the id, _id, slug or uri. OpenRegister's exceptions are passed on, so the
	 * caller can tell a missing object from a refused one.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $register      The register slug.
	 * @param string $schema        The schema slug.
	 * @param string $id            The identifier.
	 *
	 * @return object|null The entity, or null when OpenRegister answers none.
	 *
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function find(object $objectService, string $register, string $schema, string $id): ?object {
		$entity = $objectService->find(id: $id, options: new ObjectFindOptions(register: $register, schema: $schema));
		if (is_object($entity) === false) {
			return null;
		}

		return $entity;
	}//end find()

	/**
	 * Save one object as an explicit server-trusted write (`_rbac: false`).
	 *
	 * Only for writes the caller has already been checked for. Without a uuid
	 * the object is created; with one it is updated. OpenRegister's exceptions
	 * are passed on.
	 *
	 * @param object              $objectService OpenRegister's ObjectService.
	 * @param string              $register      The register slug.
	 * @param string              $schema        The schema slug.
	 * @param array<string,mixed> $data          The object data.
	 * @param string|null         $uuid          The uuid to update, or null to create.
	 *
	 * @return object The saved entity.
	 *
	 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function saveAsSystem(object $objectService, string $register, string $schema, array $data, ?string $uuid = null): object {
		return $objectService->saveObject(
			object: $data,
			options: new ObjectSaveOptions(target: new ObjectFindTarget(register: $register, schema: $schema), uuid: $uuid),
			_rbac: false
		);
	}//end saveAsSystem()
}//end class
