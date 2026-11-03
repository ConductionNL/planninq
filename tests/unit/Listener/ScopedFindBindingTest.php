<?php

/**
 * A scoped find can answer another table; the membership reads refuse that answer.
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

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * OpenRegister retries a missed uuid without the schema, so a task uuid asked for as a project finds the task.
 *
 * @covers \OCA\Planninq\Listener\TaskScopeResolver
 * @covers \OCA\Planninq\Service\ProjectMembershipService
 */
class ScopedFindBindingTest extends TestCase {
	use MembershipFixture;

	private const TASK_UUID = '11111111-2222-4333-8444-555555555555';

	private const PROJECT_UUID = '66666666-7777-4888-8999-000000000000';

	private const COPIED = ['title' => 'Secret', 'owner' => 'eve', 'members' => ['eve', 'erin']];

	/**
	 * Seed one task and one project in the fixture's double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('task', self::TASK_UUID, self::COPIED);
		$this->objects->seed('project', self::PROJECT_UUID, ['title' => 'P', 'owner' => 'olga', 'members' => ['alice']]);
	}//end setUp()

	/**
	 * The activity audience comes from the project, scoped to the project table.
	 *
	 * @return void
	 */
	public function testProjectMembersReadsTheProjectOnly(): void {
		$resolver = $this->scopeResolver();

		self::assertSame(expected: ['alice', 'olga'], actual: $resolver->projectMembers(self::PROJECT_UUID));
		self::assertSame(expected: 'planninq', actual: $this->objects->finds[0]['register']);
		self::assertSame(expected: 'project', actual: $this->objects->finds[0]['schema']);
		self::assertSame(expected: [], actual: $resolver->projectMembers(self::TASK_UUID));
		self::assertSame(expected: [], actual: $resolver->projectMembers('no-such-project'));
	}//end testProjectMembersReadsTheProjectOnly()

	/**
	 * An entity needs the asked schema, the planninq register and a uuid.
	 *
	 * @return void
	 */
	public function testIsObjectOfSchema(): void {
		$resolver = $this->scopeResolver();
		$project  = $this->schemaId(slug: 'project');

		self::assertTrue($resolver->isObjectOfSchema(entity: InMemoryObjectService::entity(uuid: 'u', data: [], register: '1', schema: $project), schema: 'project'));
		self::assertFalse($resolver->isObjectOfSchema(entity: InMemoryObjectService::entity(uuid: 'u', data: [], register: '1', schema: $project), schema: 'task'));
		self::assertFalse($resolver->isObjectOfSchema(entity: InMemoryObjectService::entity(uuid: 'u', data: [], register: '9', schema: $project), schema: 'project'));
		self::assertFalse($resolver->isObjectOfSchema(entity: InMemoryObjectService::entity(uuid: '', data: [], register: '1', schema: $project), schema: 'project'));
	}//end testIsObjectOfSchema()

	/**
	 * The slugs are read as the system: the organisation filter must not decide what a row is.
	 *
	 * @return void
	 */
	public function testSlugsAreReadWithoutTheOrganisationFilter(): void {
		$mapper = new class {
			/**
			 * @param mixed $id            The id.
			 * @param bool  $_multitenancy Whether the organisation filter applies.
			 *
			 * @return object
			 */
			public function find(mixed $id, bool $_multitenancy = true): object {
				if ($_multitenancy === true) {
					throw new \RuntimeException('hidden by the organisation filter');
				}

				return new class {
					/**
					 * @return string
					 */
					public function getSlug(): string {
						return 'planninq';
					}//end getSlug()
				};
			}//end find()
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($mapper);

		$resolver = new TaskScopeResolver(container: $container, logger: new NullLogger());

		self::assertSame(expected: 'planninq', actual: $resolver->planninqSchemaSlug(registerId: '1', schemaId: '2'));
	}//end testSlugsAreReadWithoutTheOrganisationFilter()

	/**
	 * Every membership read refuses the task the retry finds, and still reads what it was asked for.
	 *
	 * @return void
	 */
	public function testMembershipReadsRefuseAnotherTable(): void {
		$membership = $this->membershipService();

		self::assertNull($membership->membersOfProject(self::TASK_UUID));
		self::assertNull($membership->ownerOfProject(self::TASK_UUID));
		self::assertNull($membership->objectData(schema: 'project', id: self::TASK_UUID));
		self::assertSame(expected: self::COPIED, actual: $membership->objectData(schema: 'task', id: self::TASK_UUID));
		self::assertSame(expected: 'olga', actual: $membership->ownerOfProject(self::PROJECT_UUID));
		self::assertSame(expected: ['alice', 'olga'], actual: $membership->membersOfProject(self::PROJECT_UUID));
	}//end testMembershipReadsRefuseAnotherTable()
}//end class
