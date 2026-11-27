<?php

/**
 * Planninq ObjectServiceCalls
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The calls on OpenRegister's ObjectService for one register and schema: a
 * find scoped to that table with RBAC on, a save as an explicit
 * server-trusted write, and a save inside OpenRegister's system-operation
 * scope. Keeps the OpenRegister option value objects out of the controllers,
 * listeners and services that only need those calls.
 *
 * Inside the system-operation scope planninq's own guard listeners stand
 * aside and no post-event is raised, so a caller that needs other objects in
 * step (a project's tasks, its finance lines) copies to them itself.
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
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Kernel\Contracts\Value as OpenRegisterValue;
use RuntimeException;

/**
 * Scoped find and trusted save calls on OpenRegister's ObjectService for one schema.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
final class ObjectServiceCalls {

	/**
	 * OpenRegister's system-operation scope, absent on older OpenRegister versions.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param object $objectService OpenRegister's ObjectService.
	 * @param string $register      The register slug.
	 * @param string $schema        The schema slug.
	 */
	public function __construct(
		private object $objectService,
		private string $register,
		private string $schema,
	) {
	}//end __construct()

	/**
	 * Find one object in this register and schema's table, with RBAC on.
	 *
	 * Scoped to the table: an unscoped find answers the first table holding
	 * the id, _id, slug or uri. OpenRegister's exceptions are passed on, so the
	 * caller can tell a missing object from a refused one.
	 *
	 * @param string $id The identifier.
	 *
	 * @return object|null The entity, or null when OpenRegister answers none.
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function find(string $id): ?object {
		$entity = $this->objectService->find(id: $id, options: new OpenRegisterValue\ObjectFindOptions(register: $this->register, schema: $this->schema));
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
	 * @param array<string,mixed> $data The object data.
	 * @param string|null         $uuid The uuid to update, or null to create.
	 *
	 * @return object The saved entity.
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function saveAsSystem(array $data, ?string $uuid = null): object {
		return $this->objectService->saveObject(
			object: $data,
			options: new OpenRegisterValue\ObjectSaveOptions(target: $this->target(), uuid: $uuid),
			_rbac: false
		);
	}//end saveAsSystem()

	/**
	 * Whether this OpenRegister has the system-operation scope to save in.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function hasSystemScope(): bool {
		return class_exists(self::OR_SYSTEM_CONTEXT);
	}//end hasSystemScope()

	/**
	 * Store an object's whole data inside the system-operation scope.
	 *
	 * No RBAC, no multitenancy, silent and without validation.
	 *
	 * @param array<string,mixed> $data The whole object data.
	 * @param string              $uuid The uuid to update.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When OpenRegister has no system-operation scope.
	 * @throws \Throwable       What OpenRegister throws when it refuses the write.
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function saveInSystemScope(array $data, string $uuid): void {
		if ($this->hasSystemScope() === false) {
			throw new RuntimeException('OpenRegister has no system-operation scope.');
		}

		$class         = self::OR_SYSTEM_CONTEXT;
		$objectService = $this->objectService;
		$options       = new OpenRegisterValue\ObjectSaveOptions(
			target: $this->target(),
			uuid: $uuid,
			policy: new OpenRegisterValue\SavePolicy(silent: true, validation: false)
		);
		$class::run(
			static fn () => $objectService->saveObject(object: $data, options: $options, _rbac: false, _multitenancy: false)
		);
	}//end saveInSystemScope()

	/**
	 * The register and schema as OpenRegister's save target.
	 *
	 * @return OpenRegisterValue\ObjectFindTarget
	 */
	private function target(): OpenRegisterValue\ObjectFindTarget {
		return new OpenRegisterValue\ObjectFindTarget(register: $this->register, schema: $this->schema);
	}//end target()
}//end class
