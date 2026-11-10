<?php

/**
 * The leave flow checks and saves the same project, whatever identifier the caller sends.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\Planninq\Controller\ProjectController;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\WorkItemKeyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * An identifier another table also answers to (a task's numeric _id or slug) neither passes the
 * membership check on that task nor makes the system write land on another project.
 *
 * @covers \OCA\Planninq\Controller\ProjectController
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 * @uses   \OCA\Planninq\Service\ProjectRoles
 */
class ProjectLeaveBindingTest extends TestCase {
	use MembershipFixture;

	/**
	 * Carol is on a task whose copied members list names her; the project that also answers to its id is Eve's.
	 *
	 * @return void
	 */
	public function testATaskAnsweringToTheIdDoesNotLetTheCallerWriteTheProject(): void {
		foreach (['7', 'shared-slug'] as $identifier) {
			$objects = $this->objects();
			$objects->aliases = ['task' => [$identifier => 't-carol'], 'project' => [$identifier => 'p-eve']];

			$response = $this->controller(objects: $objects, uid: 'carol')->leaveProject($identifier);

			self::assertSame(expected: 403, actual: $response->getStatus(), message: $identifier);
			self::assertSame(expected: [], actual: $objects->saves, message: $identifier);
			self::assertSame(expected: ['eve', 'erin'], actual: $objects->rows['project']['p-eve']['members'], message: $identifier);
		}
	}//end testATaskAnsweringToTheIdDoesNotLetTheCallerWriteTheProject()

	/**
	 * Only the task answers to the id: that is no project, so nothing is written.
	 *
	 * @return void
	 */
	public function testAnIdOnlyATaskAnswersToIsNotAProject(): void {
		$objects = $this->objects();
		$objects->aliases = ['task' => ['7' => 't-carol']];

		$response = $this->controller(objects: $objects, uid: 'carol')->leaveProject('7');

		self::assertSame(expected: 404, actual: $response->getStatus());
		self::assertSame(expected: [], actual: $objects->saves);

		// A lookup that hands back a task, or a project without a uuid, is refused the same way.
		$objects = $this->objects();
		$objects->seed('project', '', ['title' => 'No uuid', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol', 'bob']]);
		$objects->misfiled = ['9' => ['task', 't-carol'], 'blank' => ['project', '']];
		foreach (['9', 'blank'] as $identifier) {
			$response = $this->controller(objects: $objects, uid: 'carol')->leaveProject($identifier);
			self::assertSame(expected: 404, actual: $response->getStatus(), message: $identifier);
		}

		self::assertSame(expected: [], actual: $objects->saves);
	}//end testAnIdOnlyATaskAnswersToIsNotAProject()

	/**
	 * Leaving by another identifier of the caller's own project saves that project by its uuid.
	 *
	 * @return void
	 */
	public function testTheSaveNamesTheUuidOfTheProjectThatWasChecked(): void {
		$objects = $this->objects();
		$objects->aliases = ['project' => ['carols-slug' => 'p-carol']];

		$response = $this->controller(objects: $objects, uid: 'bob')->leaveProject('carols-slug');

		self::assertSame(expected: 200, actual: $response->getStatus());
		self::assertSame(expected: ['p-carol'], actual: array_column($objects->saves, 'uuid'));
		self::assertSame(expected: 'project', actual: $objects->saves[0]['schema']);
		self::assertSame(expected: ['carol'], actual: $objects->rows['project']['p-carol']['members']);
	}//end testTheSaveNamesTheUuidOfTheProjectThatWasChecked()

	/**
	 * OpenRegister throws on a miss: the caller gets the same 404 as for no project, and nothing is written.
	 *
	 * @return void
	 */
	public function testAMissThrownByOpenRegisterIsNotFound(): void {
		$objects = $this->objects();
		$objects->missWith = new DoesNotExistException('Object not found');

		$response = $this->controller(objects: $objects, uid: 'carol')->leaveProject('gone');

		self::assertSame(expected: 404, actual: $response->getStatus());
		self::assertSame(expected: ['error' => 'Project not found.'], actual: $response->getData());
		self::assertSame(expected: [], actual: $objects->saves);
	}//end testAMissThrownByOpenRegisterIsNotFound()

	/**
	 * Another failure of the read is no miss: it is classified, without telling the caller why.
	 *
	 * @return void
	 */
	public function testAnotherReadFailureIsAServerError(): void {
		$objects = $this->objects();
		$objects->missWith = new \RuntimeException('database gone');

		$response = $this->controller(objects: $objects, uid: 'carol')->leaveProject('gone');

		self::assertSame(expected: 500, actual: $response->getStatus());
		self::assertSame(expected: ['error' => 'Failed to complete leave project.'], actual: $response->getData());
		self::assertSame(expected: [], actual: $objects->saves);
	}//end testAnotherReadFailureIsAServerError()

	/**
	 * A user id in the request names nobody: the session user is checked and taken off the project.
	 *
	 * @return void
	 */
	public function testAForgedUserParameterIsIgnored(): void {
		$objects  = $this->objects();
		$response = $this->controller(objects: $objects, uid: 'mallory', forged: 'eve')->leaveProject('p-eve');
		self::assertSame(expected: 403, actual: $response->getStatus());
		self::assertSame(expected: [], actual: $objects->saves);

		$response = $this->controller(objects: $objects, uid: 'bob', forged: 'carol')->leaveProject('p-carol');
		self::assertSame(expected: 200, actual: $response->getStatus());
		self::assertSame(expected: ['carol'], actual: $objects->rows['project']['p-carol']['members']);
		self::assertSame(expected: 'carol', actual: $objects->rows['project']['p-carol']['owner']);
	}//end testAForgedUserParameterIsIgnored()

	/**
	 * An OpenRegister-like double: the schema argument scopes find (setSchema does not), without it the first table wins.
	 *
	 * @return InMemoryObjectService
	 */
	private function objects(): InMemoryObjectService {
		$schemaIds = ['task' => $this->schemaId(slug: 'task'), 'project' => $this->schemaId(slug: 'project')];
		$objects   = new class($schemaIds) extends InMemoryObjectService {
			/**
			 * Other identifiers per table: [table][identifier] => uuid.
			 *
			 * @var array<string,array<string,string>>
			 */
			public array $aliases = [];

			/**
			 * Rows a lookup answers from another table than the one asked: [identifier] => [table, uuid].
			 *
			 * @var array<string,array{0:string,1:string}>
			 */
			public array $misfiled = [];

			/**
			 * What a miss throws, as OpenRegister does; null answers null.
			 *
			 * @var \Throwable|null
			 */
			public ?\Throwable $missWith = null;

			/**
			 * @param array<string,string> $schemaIds Schema slug => schema id.
			 */
			public function __construct(
				private array $schemaIds,
			) {
			}

			/**
			 * {@inheritDoc}
			 */
			public function find(
				int|string $id,
				?ObjectFindOptions $options = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
			): ?object {
				$schema = $options?->schema;
				if (isset($this->misfiled[(string)$id]) === true) {
					[$table, $uuid] = $this->misfiled[(string)$id];
					return self::entity(uuid: $uuid, data: $this->rows[$table][$uuid], register: '1', schema: $this->schemaIds[$table]);
				}

				foreach (($schema === null ? ['task', 'project'] : [(string)$schema]) as $table) {
					$uuid = (isset($this->rows[$table][(string)$id]) === true) ? (string)$id : ($this->aliases[$table][(string)$id] ?? null);
					if ($uuid !== null) {
						return self::entity(uuid: $uuid, data: $this->rows[$table][$uuid], register: '1', schema: $this->schemaIds[$table]);
					}
				}

				if ($this->missWith !== null) {
					throw $this->missWith;
				}

				return null;
			}
		};

		$objects->seed('project', 'p-eve', ['title' => 'Eve', 'status' => 'active', 'owner' => 'eve', 'members' => ['eve', 'erin']]);
		$objects->seed('project', 'p-carol', ['title' => 'Carol', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol', 'bob']]);
		$objects->seed('task', 't-carol', ['title' => 'Task', 'status' => 'open', 'project' => 'p-carol', 'owner' => 'carol', 'members' => ['carol', 'bob']]);

		return $objects;
	}//end objects()

	/**
	 * The controller for one caller over the double and the real scope resolver.
	 *
	 * @param InMemoryObjectService $objects The double.
	 * @param string                $uid     The caller.
	 * @param string                $forged  What the request claims for every parameter.
	 *
	 * @return ProjectController
	 */
	private function controller(InMemoryObjectService $objects, string $uid, string $forged = ''): ProjectController {
		$this->objects = $objects;
		$resolver      = $this->scopeResolver();
		$container     = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\\Planninq\\Listener\\TaskScopeResolver' ? $resolver : $objects)
		);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturn($forged === '' ? null : $forged);
		$request->method('getParams')->willReturn(['uid' => $forged, 'userId' => $forged]);

		return new ProjectController(
			request: $request,
			settingsService: $this->createMock(originalClassName: SettingsService::class),
			userSession: $session,
			container: $container,
			logger: new NullLogger(),
			boardColumns: $this->createMock(originalClassName: BoardColumnService::class),
			keys: $this->createMock(originalClassName: WorkItemKeyService::class),
		);
	}//end controller()
}//end class
