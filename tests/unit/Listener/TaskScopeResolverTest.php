<?php

/**
 * Tests for TaskScopeResolver.
 *
 * Covers the slug resolution against OpenRegister's mappers (including the
 * per-request cache and the fail-closed paths) and the project member lookup.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

use OCA\Planninq\Listener\TaskScopeResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Listener\TaskScopeResolver
 */
class TaskScopeResolverTest extends TestCase {

	private const REGISTER_MAPPER = 'OCA\\OpenRegister\\Db\\RegisterMapper';
	private const SCHEMA_MAPPER = 'OCA\\OpenRegister\\Db\\SchemaMapper';
	private const OBJECT_SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * A mapper double answering `find()` from an id => slug map.
	 *
	 * A slug of null makes `find()` return an object without `getSlug()`; an id
	 * missing from the map makes it throw.
	 *
	 * @param array<string,string|null> $slugs Id to slug.
	 *
	 * @return object
	 */
	private function mapper(array $slugs): object {
		return new class($slugs) {
			/** @var array<int,array{id:string,multitenancy:bool}> */
			public array $calls = [];

			/**
			 * @param array<string,string|null> $slugs Id to slug.
			 */
			public function __construct(
				private array $slugs,
			) {
			}

			/**
			 * @param string $id The id.
			 * @param bool $_multitenancy Organisation filter flag.
			 *
			 * @return object
			 */
			public function find(string $id, bool $_multitenancy = true): object {
				$this->calls[] = ['id' => $id, 'multitenancy' => $_multitenancy];
				if (array_key_exists($id, $this->slugs) === false) {
					throw new \RuntimeException('not found: ' . $id);
				}

				$slug = $this->slugs[$id];
				if ($slug === null) {
					return new \stdClass();
				}

				return new class($slug) {
					/**
					 * @param string $slug The slug.
					 */
					public function __construct(
						private string $slug,
					) {
					}

					/**
					 * @return string
					 */
					public function getSlug(): string {
						return $this->slug;
					}
				};
			}
		};

	}//end mapper()

	/**
	 * A container serving the given services by id.
	 *
	 * @param array<string,object> $services Service id to instance.
	 *
	 * @return ContainerInterface
	 */
	private function container(array $services): ContainerInterface {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($services): object {
				if (array_key_exists($id, $services) === false) {
					throw new \RuntimeException('no service ' . $id);
				}

				return $services[$id];
			}
		);

		return $container;

	}//end container()

	/**
	 * An entity with the register/schema/uuid accessors and an object payload.
	 *
	 * @param string|null $register Register id.
	 * @param string|null $schema Schema id.
	 * @param string|null $uuid Uuid.
	 * @param mixed $object The object payload.
	 *
	 * @return object
	 */
	private function entity(?string $register, ?string $schema, ?string $uuid, mixed $object = []): object {
		return new class($register, $schema, $uuid, $object) {
			/**
			 * @param string|null $r Register.
			 * @param string|null $s Schema.
			 * @param string|null $u Uuid.
			 * @param mixed $o Object.
			 */
			public function __construct(
				private ?string $r,
				private ?string $s,
				private ?string $u,
				private mixed $o,
			) {
			}

			/** @return string|null */
			public function getRegister(): ?string {
				return $this->r;
			}

			/** @return string|null */
			public function getSchema(): ?string {
				return $this->s;
			}

			/** @return string|null */
			public function getUuid(): ?string {
				return $this->u;
			}

			/** @return mixed */
			public function getObject(): mixed {
				return $this->o;
			}
		};

	}//end entity()

	/**
	 * An ObjectService double whose `find()` returns a fixed entity or throws.
	 *
	 * @param object|null $entity The entity to return.
	 * @param bool $throws Whether find() throws.
	 *
	 * @return object
	 */
	private function objectService(?object $entity, bool $throws = false): object {
		return new class($entity, $throws) {
			/** @var array<int,string> */
			public array $calls = [];

			/**
			 * @param object|null $entity Entity.
			 * @param bool $throws Throw flag.
			 */
			public function __construct(
				private ?object $entity,
				private bool $throws,
			) {
			}

			/** @param string $r Register. */
			public function setRegister(string $r): void {
				$this->calls[] = 'register:' . $r;
			}

			/** @param string $s Schema. */
			public function setSchema(string $s): void {
				$this->calls[] = 'schema:' . $s;
			}

			/**
			 * @param string $id Id.
			 * @param string $register Register.
			 * @param string $schema Schema.
			 *
			 * @return object|null
			 */
			public function find(string $id, string $register, string $schema): ?object {
				$this->calls[] = 'find:' . $id . ':' . $register . ':' . $schema;
				if ($this->throws === true) {
					throw new \RuntimeException('boom');
				}

				return $this->entity;
			}
		};

	}//end objectService()

	/**
	 * The resolver over the given services.
	 *
	 * @param array<string,object> $services Container services.
	 * @param LoggerInterface|null $logger Logger.
	 *
	 * @return TaskScopeResolver
	 */
	private function resolver(array $services, ?LoggerInterface $logger = null): TaskScopeResolver {
		return new TaskScopeResolver(
			container: $this->container(services: $services),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class)),
		);

	}//end resolver()

	/**
	 * Planninq register + task schema is a task; mappers are read without the org filter.
	 *
	 * @return void
	 */
	public function testPlanninqTaskSchemaIsATask(): void {
		$registers = $this->mapper(slugs: ['1' => 'planninq']);
		$schemas = $this->mapper(slugs: ['7' => 'task']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $registers, self::SCHEMA_MAPPER => $schemas]);

		$this->assertTrue($resolver->isPlanninqTask(registerId: '1', schemaId: '7'));
		$this->assertSame([['id' => '1', 'multitenancy' => false]], $registers->calls);
		$this->assertSame([['id' => '7', 'multitenancy' => false]], $schemas->calls);

	}//end testPlanninqTaskSchemaIsATask()

	/**
	 * An empty id is never a task, and nothing is looked up.
	 *
	 * @return void
	 */
	public function testEmptyIdsAreNotATask(): void {
		$registers = $this->mapper(slugs: ['1' => 'planninq']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $registers]);

		$this->assertFalse($resolver->isPlanninqTask(registerId: '', schemaId: '7'));
		$this->assertFalse($resolver->isPlanninqTask(registerId: '1', schemaId: ''));
		$this->assertSame([], $registers->calls);

	}//end testEmptyIdsAreNotATask()

	/**
	 * Another register stops before the schema lookup.
	 *
	 * @return void
	 */
	public function testOtherRegisterIsNotATask(): void {
		$schemas = $this->mapper(slugs: ['7' => 'task']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'other']), self::SCHEMA_MAPPER => $schemas]);

		$this->assertFalse($resolver->isPlanninqTask(registerId: '1', schemaId: '7'));
		$this->assertSame([], $schemas->calls);

	}//end testOtherRegisterIsNotATask()

	/**
	 * A non-task schema of the planninq register is not a task.
	 *
	 * @return void
	 */
	public function testOtherSchemaIsNotATask(): void {
		$resolver = $this->resolver(services: [
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['7' => 'project']),
		]);

		$this->assertFalse($resolver->isPlanninqTask(registerId: '1', schemaId: '7'));

	}//end testOtherSchemaIsNotATask()

	/**
	 * A failing lookup is logged and resolves to no slug, so not a task.
	 *
	 * @return void
	 */
	public function testFailingLookupIsLoggedAndNotATask(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('debug')->with(
			'Planninq: could not resolve OpenRegister slug',
			['service' => self::REGISTER_MAPPER, 'id' => '9', 'exception' => 'not found: 9']
		);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $this->mapper(slugs: [])], logger: $logger);

		$this->assertFalse($resolver->isPlanninqTask(registerId: '9', schemaId: '7'));

	}//end testFailingLookupIsLoggedAndNotATask()

	/**
	 * An entity without a slug accessor resolves to no slug.
	 *
	 * @return void
	 */
	public function testEntityWithoutSlugAccessorResolvesEmpty(): void {
		$resolver = $this->resolver(services: [
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['7' => null]),
		]);

		$this->assertSame('', $resolver->planninqSchemaSlug(registerId: '1', schemaId: '7'));

	}//end testEntityWithoutSlugAccessorResolvesEmpty()

	/**
	 * The schema slug is resolved once per register/schema pair.
	 *
	 * @return void
	 */
	public function testSchemaSlugIsCachedPerPair(): void {
		$registers = $this->mapper(slugs: ['1' => 'planninq']);
		$schemas = $this->mapper(slugs: ['7' => 'release', '8' => 'task']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $registers, self::SCHEMA_MAPPER => $schemas]);

		$this->assertSame('release', $resolver->planninqSchemaSlug(registerId: '1', schemaId: '7'));
		$this->assertSame('release', $resolver->planninqSchemaSlug(registerId: '1', schemaId: '7'));
		$this->assertSame('task', $resolver->planninqSchemaSlug(registerId: '1', schemaId: '8'));
		$this->assertCount(2, $registers->calls);
		$this->assertCount(2, $schemas->calls);

	}//end testSchemaSlugIsCachedPerPair()

	/**
	 * Another register yields '' and the miss is cached too.
	 *
	 * @return void
	 */
	public function testSchemaSlugOfAnotherRegisterIsEmptyAndCached(): void {
		$registers = $this->mapper(slugs: ['2' => 'other']);
		$schemas = $this->mapper(slugs: ['7' => 'task']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $registers, self::SCHEMA_MAPPER => $schemas]);

		$this->assertSame('', $resolver->planninqSchemaSlug(registerId: '2', schemaId: '7'));
		$this->assertSame('', $resolver->planninqSchemaSlug(registerId: '2', schemaId: '7'));
		$this->assertCount(1, $registers->calls);
		$this->assertSame([], $schemas->calls);

	}//end testSchemaSlugOfAnotherRegisterIsEmptyAndCached()

	/**
	 * An empty id pair yields '' without a lookup.
	 *
	 * @return void
	 */
	public function testSchemaSlugOfEmptyIdsIsEmpty(): void {
		$registers = $this->mapper(slugs: ['1' => 'planninq']);
		$resolver = $this->resolver(services: [self::REGISTER_MAPPER => $registers]);

		$this->assertSame('', $resolver->planninqSchemaSlug(registerId: '', schemaId: ''));
		$this->assertSame('', $resolver->planninqSchemaSlug(registerId: '1', schemaId: ''));
		$this->assertSame([], $registers->calls);

	}//end testSchemaSlugOfEmptyIdsIsEmpty()

	/**
	 * isObjectOfSchema needs the schema slug and a uuid.
	 *
	 * @return void
	 */
	public function testIsObjectOfSchema(): void {
		$resolver = $this->resolver(services: [
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['5' => 'project']),
		]);

		$this->assertTrue($resolver->isObjectOfSchema(entity: $this->entity(register: '1', schema: '5', uuid: 'p-1'), schema: 'project'));
		$this->assertFalse($resolver->isObjectOfSchema(entity: $this->entity(register: '1', schema: '5', uuid: 'p-1'), schema: 'task'));
		$this->assertFalse($resolver->isObjectOfSchema(entity: $this->entity(register: '1', schema: '5', uuid: null), schema: 'project'));
		$this->assertFalse($resolver->isObjectOfSchema(entity: $this->entity(register: null, schema: null, uuid: 'p-1'), schema: 'project'));

	}//end testIsObjectOfSchema()

	/**
	 * Project members are the members plus the owner, as strings.
	 *
	 * @return void
	 */
	public function testProjectMembersIncludeTheOwner(): void {
		$objects = $this->objectService(entity: $this->entity(register: '1', schema: '5', uuid: 'p-1', object: ['members' => ['alice', 42], 'owner' => 'olga']));
		$resolver = $this->resolver(services: [
			self::OBJECT_SERVICE  => $objects,
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['5' => 'project']),
		]);

		$this->assertSame(['alice', '42', 'olga'], $resolver->projectMembers(projectId: 'p-1'));
		$this->assertSame(['register:planninq', 'schema:project', 'find:p-1:planninq:project'], $objects->calls);

	}//end testProjectMembersIncludeTheOwner()

	/**
	 * A non-array members value and an empty owner yield no members.
	 *
	 * @return void
	 */
	public function testProjectMembersIgnoreMalformedMembersAndEmptyOwner(): void {
		$resolver = $this->resolver(services: [
			self::OBJECT_SERVICE  => $this->objectService(entity: $this->entity(register: '1', schema: '5', uuid: 'p-1', object: ['members' => 'alice', 'owner' => ''])),
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['5' => 'project']),
		]);

		$this->assertSame([], $resolver->projectMembers(projectId: 'p-1'));

	}//end testProjectMembersIgnoreMalformedMembersAndEmptyOwner()

	/**
	 * An empty project id resolves to no members without a lookup.
	 *
	 * @return void
	 */
	public function testProjectMembersOfEmptyIdIsEmpty(): void {
		$objects = $this->objectService(entity: null);
		$resolver = $this->resolver(services: [self::OBJECT_SERVICE => $objects]);

		$this->assertSame([], $resolver->projectMembers(projectId: ''));
		$this->assertSame([], $objects->calls);

	}//end testProjectMembersOfEmptyIdIsEmpty()

	/**
	 * A missing project, or an object of another schema, yields no members.
	 *
	 * @return void
	 */
	public function testProjectMembersOfMissingOrForeignObjectIsEmpty(): void {
		$missing = $this->resolver(services: [self::OBJECT_SERVICE => $this->objectService(entity: null)]);
		$this->assertSame([], $missing->projectMembers(projectId: 'p-1'));

		$foreign = $this->resolver(services: [
			self::OBJECT_SERVICE  => $this->objectService(entity: $this->entity(register: '1', schema: '7', uuid: 'p-1', object: ['owner' => 'olga'])),
			self::REGISTER_MAPPER => $this->mapper(slugs: ['1' => 'planninq']),
			self::SCHEMA_MAPPER   => $this->mapper(slugs: ['7' => 'task']),
		]);
		$this->assertSame([], $foreign->projectMembers(projectId: 'p-1'));

	}//end testProjectMembersOfMissingOrForeignObjectIsEmpty()

	/**
	 * A failing find is logged at debug and yields no members.
	 *
	 * @return void
	 */
	public function testProjectMembersLookupFailureIsLogged(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('debug')->with(
			'Planninq: could not resolve project members for task activity',
			['project' => 'p-1', 'exception' => 'boom']
		);
		$resolver = $this->resolver(services: [self::OBJECT_SERVICE => $this->objectService(entity: null, throws: true)], logger: $logger);

		$this->assertSame([], $resolver->projectMembers(projectId: 'p-1'));

	}//end testProjectMembersLookupFailureIsLogged()
}//end class
