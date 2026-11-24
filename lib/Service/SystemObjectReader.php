<?php

/**
 * Planninq SystemObjectReader
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Reads one planninq object as the system: RBAC and multitenancy off, no
 * rendering and no audit row. The object only counts when it belongs to the
 * schema asked for, so an identifier of another schema never resolves. Shared
 * by the membership service and the code-link service, which both read
 * objects without depending on what the acting user may see.
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

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\Planninq\Listener\TaskScopeResolver;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Finds one planninq object of a schema as the system.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
final class SystemObjectReader {

	/**
	 * The planninq register slug.
	 *
	 * @var string
	 */
	private const REGISTER = 'planninq';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService and the schema resolver.
	 * @param LoggerInterface    $logger    The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * One planninq object of this schema, or null when it does not resolve.
	 *
	 * Any failure (OpenRegister missing, the object gone, a read error) answers
	 * null and is logged at debug level.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The identifier.
	 *
	 * @return object|null The entity.
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function find(string $schema, string $id): ?object {
		if ($id === '') {
			return null;
		}

		try {
			$entity = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService')->find(
				id: $id,
				options: new ObjectFindOptions(register: self::REGISTER, schema: $schema, render: false, audit: false),
				_rbac: false,
				_multitenancy: false
			);
			if ($entity === null || $this->scopeResolver()->isObjectOfSchema(entity: $entity, schema: $schema) === false) {
				return null;
			}
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Planninq: could not read a planninq object as the system',
				['schema' => $schema, 'object' => $id, 'exception' => $e->getMessage()]
			);
			return null;
		}

		return $entity;
	}//end find()

	/**
	 * One planninq object of this schema as its own uuid and its data, or null.
	 *
	 * The id is the object's own uuid, whatever identifier found it.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The identifier.
	 *
	 * @return array{id:string,data:array<string,mixed>}|null
	 *
	 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
	 */
	public function row(string $schema, string $id): ?array {
		$entity = $this->find(schema: $schema, id: $id);
		if ($entity === null) {
			return null;
		}

		$rows = new ObjectRows();
		$uuid = $rows->rowId(row: $entity);
		if ($uuid === '') {
			return null;
		}

		return ['id' => $uuid, 'data' => $rows->data(row: $entity)];
	}//end row()

	/**
	 * The resolver telling which schema a found object belongs to.
	 *
	 * @return TaskScopeResolver
	 */
	private function scopeResolver(): TaskScopeResolver {
		return $this->container->get(TaskScopeResolver::class);
	}//end scopeResolver()
}//end class
