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
	 * Moved from `planix` to `planninq` together with the MigrateRegisterSlug
	 * repair step, which renames the register ROW. This literal only resolves
	 * because that step runs first: OpenRegister looks a register up by slug and
	 * by nothing else, so a renamed slug here without the row rename would find
	 * no register at all. Application::boot() repeats this value as a literal at
	 * subscription time and must stay in step with it.
	 *
	 * @var string
	 */
	public const REGISTER_SLUG = 'planninq';

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
	 * Schema slugs already resolved in this request, keyed `registerId:schemaId`.
	 *
	 * An empty string means "not a planninq schema". ObjectUpdatingEvent has no
	 * `getObject()`, so OpenRegister's subscription proxy cannot narrow it by
	 * schema and a pre-update listener sees every update on the instance; this
	 * keeps the answer for a pair it has already seen to one lookup.
	 *
	 * @var array<string,string>
	 */
	private array $schemaSlugCache = [];

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
	 * The schema slug of a register/schema id pair, when the register is planninq.
	 *
	 * @param string $registerId The OR register id from the event object.
	 * @param string $schemaId The OR schema id from the event object.
	 *
	 * @return string The schema slug, or '' for another register or an unresolvable pair.
	 *
	 * @spec openspec/specs/tasks/spec.md
	 */
	public function planninqSchemaSlug(string $registerId, string $schemaId): string {
		if ($registerId === '' || $schemaId === '') {
			return '';
		}

		$key = $registerId . ':' . $schemaId;
		if (array_key_exists($key, $this->schemaSlugCache) === false) {
			$slug = '';
			if ($this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\RegisterMapper', id: $registerId) === self::REGISTER_SLUG) {
				$slug = $this->resolveSlug(service: 'OCA\\OpenRegister\\Db\\SchemaMapper', id: $schemaId);
			}

			$this->schemaSlugCache[$key] = $slug;
		}

		return $this->schemaSlugCache[$key];
	}//end planninqSchemaSlug()

	/**
	 * Whether a found entity is a planninq object of one schema, with a uuid.
	 *
	 * OpenRegister retries a missed uuid without the schema, so a scoped find can
	 * answer another table of the register.
	 *
	 * @param object $entity The found entity.
	 * @param string $schema The schema slug it must belong to.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/projects/spec.md
	 */
	public function isObjectOfSchema(object $entity, string $schema): bool {
		$slug = $this->planninqSchemaSlug(
			registerId: (string)($entity->getRegister() ?? ''),
			schemaId: (string)($entity->getSchema() ?? '')
		);

		return ((string)($entity->getUuid() ?? '') !== '' && $slug === $schema);
	}//end isObjectOfSchema()

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
			$entity = $objectService->find(id: $projectId, register: self::REGISTER_SLUG, schema: self::PROJECT_SCHEMA_SLUG);
			if ($entity === null || $this->isObjectOfSchema(entity: $entity, schema: self::PROJECT_SCHEMA_SLUG) === false) {
				return [];
			}

			$data = (array)$entity->getObject();

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
			// A system read: the organisation filter hides these rows from a user in another organisation and from an upgrade.
			$entity = $this->container->get($service)->find(id: $id, _multitenancy: false);
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
}//end class
