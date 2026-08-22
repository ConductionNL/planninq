<?php

/**
 * Planninq TaskScopeResolver.
 *
 * Decides whether an OpenRegister object belongs to the Planninq register's
 * `task` schema, and resolves a project's members — the two OpenRegister
 * lookups the {@see TaskActivityListener} needs, pulled out so the listener
 * stays simple and the resolution is independently testable.
 *
 * @category Listener
 * @package  OCA\Planninq\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Listener;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves Planninq task scope + project members from OpenRegister.
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
class TaskScopeResolver {
	/**
	 * OpenRegister register slug owning the Planninq schemas.
	 *
	 * Deliberately still the PRE-RENAME `planix`. The app id became `planninq`,
	 * but the register holding the live data is still slugged `planix` and this
	 * release ships no register-slug migration. Application::boot() repeats this
	 * value as a literal at subscription time and must stay in step with it.
	 *
	 * @var string
	 */
	public const REGISTER_SLUG = 'planix';

	/**
	 * OpenRegister schema slug for tasks.
	 *
	 * @var string
	 */
	public const TASK_SCHEMA_SLUG = 'task';

	/**
	 * OpenRegister schema slug for projects.
	 *
	 * @var string
	 */
	public const PROJECT_SCHEMA_SLUG = 'project';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container DI container (resolves OR services at runtime).
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

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
	public function isPlanninqTask(string $registerId, string $schemaId): bool {
		if ($registerId === '' || $schemaId === '') {
			return false;
		}

		if ($this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\RegisterMapper', id: $registerId) !== self::REGISTER_SLUG) {
			return false;
		}

		return $this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\SchemaMapper', id: $schemaId) === self::TASK_SCHEMA_SLUG;
	}//end isPlanninqTask()

	/**
	 * Fetch the member ids of a project (plus its owner) from OpenRegister.
	 *
	 * @param string $projectId The project UUID.
	 *
	 * @return string[] The project's member ids, or [] when unresolvable.
	 *
	 * @spec openspec/specs/task-collaboration/spec.md
	 */
	public function projectMembers(string $projectId): array {
		if ($projectId === '') {
			return [];
		}

		try {
			$objectService = $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
			$objectService->setRegister(self::REGISTER_SLUG);
			$objectService->setSchema(self::PROJECT_SCHEMA_SLUG);
			$data = $this->entityToArray(entity: $objectService->find($projectId));

			$members = ($data['members'] ?? []);
			if (is_array($members) === false) {
				$members = [];
			}

			$owner = ($data['owner'] ?? '');
			if (is_string($owner) === true && $owner !== '') {
				$members[] = $owner;
			}

			return array_map('strval', $members);
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Planninq: could not resolve project members for task activity',
				['project' => $projectId, 'exception' => $e->getMessage()]
			);
			return [];
		}//end try
	}//end projectMembers()

	/**
	 * Resolve an OpenRegister entity's slug via a mapper FQCN, by id.
	 *
	 * @param string $service The mapper FQCN (RegisterMapper or SchemaMapper).
	 * @param string $id The id to look up.
	 *
	 * @return string The slug, or '' when unresolvable / OR unavailable.
	 */
	private function resolveSlug(string $service, string $id): string {
		try {
			$entity = $this->container->get($service)->find($id);
			// `is_callable()`, NOT `method_exists()` — the same defect fixed in
			// LabelService::extractId(). OpenRegister's Register and Schema
			// entities extend \OCP\AppFramework\Db\Entity and declare no
			// `getSlug()`; the accessor is served by `__call()`, which
			// `method_exists()` cannot see. This method therefore returned ''
			// unconditionally, and every slug it was asked to resolve came back
			// empty with nothing logged.
			if (is_object($entity) === true && is_callable([$entity, 'getSlug']) === true) {
				return (string)$entity->getSlug();
			}
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Planninq: could not resolve OpenRegister slug',
				['service' => $service, 'id' => $id, 'exception' => $e->getMessage()]
			);
		}

		return '';
	}//end resolveSlug()

	/**
	 * Normalise an OpenRegister entity or array to a plain data array.
	 *
	 * @param mixed $entity An OR entity object or a plain array.
	 *
	 * @return array<string,mixed> The object data.
	 */
	private function entityToArray(mixed $entity): array {
		if (is_object($entity) === true && method_exists($entity, 'getObject') === true) {
			$data = $entity->getObject();
			if (is_array($data) === true) {
				return $data;
			}
		}

		if (is_array($entity) === true) {
			return $entity;
		}

		return [];
	}//end entityToArray()
}//end class
