<?php

/**
 * Golden test for deleting a dependency edge.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\OpenRegister\Service\Object\ObjectDeletion;
use OCA\Planninq\Exception\DependencyValidationException;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\DependencyService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Records who may delete which edge: the lookups made, the answer and the delete.
 *
 * @covers \OCA\Planninq\Service\DependencyService
 * @uses   \OCA\Planninq\Service\DependencyRepository
 * @uses   \OCA\Planninq\Service\ProjectRoles
 * @uses   \OCA\Planninq\Exception\DependencyValidationException
 */
class DependencyDeleteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/dependency-delete.golden.json';

	private const PROJECT = 'p-1';

	private const EDGES = [
		'in-project'      => ['blocker' => 't-in', 'blocked' => 't-in2', 'type' => 'blocks'],
		'blocker-gone'    => ['blocker' => 't-gone', 'blocked' => 't-in', 'type' => 'blocks'],
		'blocker-no-proj' => ['blocker' => 't-loose', 'blocked' => 't-in', 'type' => 'blocks'],
		'both-gone'       => ['blocker' => 't-gone', 'blocked' => 't-gone2', 'type' => 'blocks'],
		'no-ends'         => ['blocker' => '', 'blocked' => '', 'type' => 'relates'],
		'blocked-no-proj' => ['blocker' => 't-gone', 'blocked' => 't-loose', 'type' => 'blocks'],
		'other-project'   => ['blocker' => 't-other', 'blocked' => 't-in', 'type' => 'blocks'],
	];

	/**
	 * Every case matches the recording.
	 *
	 * @return void
	 */
	public function testDeleteMatchesTheRecording(): void {
		$recorded = [];
		foreach (array_merge(array_keys(self::EDGES), ['missing-edge']) as $edge) {
			foreach (['carol', 'eve', 'grouped', 'root', ''] as $actor) {
				$objects = $this->objects();
				$answer  = 'deleted';
				try {
					$this->service(objects: $objects, actor: $actor)->delete(id: 'e-' . $edge);
				} catch (DependencyValidationException $e) {
					$answer = $e->getCode() . ' ' . $e->getMessage();
				}

				$recorded[$edge . ' / ' . ($actor === '' ? 'nobody' : $actor)] = [
					'answer' => $answer,
					'finds'  => array_map(static fn (array $find): string => $find['schema'] . ':' . $find['id'], $objects->finds),
					'left'   => array_keys($objects->rows['dependency']),
				];
			}
		}

		$fixture = [];
		if (is_file(self::FIXTURE) === true) {
			$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			$fixture = $recorded;
			file_put_contents(self::FIXTURE, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testDeleteMatchesTheRecording()

	/**
	 * An edge whose tasks name no project is deleted by an admin only; anyone else is refused and the edge stays.
	 *
	 * @return void
	 */
	public function testAnOrphanEdgeIsDeletedByAnAdminOnly(): void {
		foreach (['both-gone', 'no-ends', 'blocked-no-proj'] as $edge) {
			foreach (['carol', 'eve', 'grouped'] as $actor) {
				$objects = $this->objects();
				try {
					$this->service(objects: $objects, actor: $actor)->delete(id: 'e-' . $edge);
					self::fail($edge . ' / ' . $actor . ': deleted without the admin role');
				} catch (DependencyValidationException $e) {
					self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $e->getCode(), message: $edge . ' / ' . $actor);
				}

				self::assertArrayHasKey(key: 'e-' . $edge, array: $objects->rows['dependency'], message: $edge . ' / ' . $actor);
			}

			$objects = $this->objects();
			$this->service(objects: $objects, actor: 'root')->delete(id: 'e-' . $edge);
			self::assertArrayNotHasKey(key: 'e-' . $edge, array: $objects->rows['dependency'], message: $edge . ' / root');
		}
	}//end testAnOrphanEdgeIsDeletedByAnAdminOnly()

	/**
	 * Without a group manager nobody counts as an admin, so an orphan edge is refused to everyone and stays.
	 *
	 * @return void
	 */
	public function testNoGroupManagerMeansNoAdminForAnOrphan(): void {
		foreach (['root', 'carol', 'eve'] as $actor) {
			$objects = $this->objects();
			try {
				$this->service(objects: $objects, actor: $actor, groups: false)->delete(id: 'e-both-gone');
				self::fail($actor . ': deleted an orphan edge without a group manager');
			} catch (DependencyValidationException $e) {
				self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $e->getCode(), message: $actor);
			}

			self::assertArrayHasKey(key: 'e-both-gone', array: $objects->rows['dependency'], message: $actor);
		}
	}//end testNoGroupManagerMeansNoAdminForAnOrphan()

	/**
	 * An edge whose tasks are gone falls back to its stored project: that project's writers delete it, others are refused.
	 *
	 * @return void
	 */
	public function testAnOrphanFallsBackToItsStoredProject(): void {
		$cases = [
			'carol'   => 'deleted',
			'grouped' => DependencyValidationException::CODE_FORBIDDEN,
			'eve'     => DependencyValidationException::CODE_FORBIDDEN,
			'root'    => DependencyValidationException::CODE_FORBIDDEN,
		];
		foreach ($cases as $actor => $expected) {
			$objects = $this->objects();
			$objects->seed('dependency', 'e-stored', ['blocker' => 't-gone', 'blocked' => 't-gone2', 'type' => 'blocks', 'project' => self::PROJECT]);
			$answer = 'deleted';
			try {
				$this->service(objects: $objects, actor: $actor)->delete(id: 'e-stored');
			} catch (DependencyValidationException $e) {
				$answer = $e->getCode();
			}

			self::assertSame(expected: $expected, actual: $answer, message: $actor);
			self::assertSame(expected: ($expected !== 'deleted'), actual: isset($objects->rows['dependency']['e-stored']), message: $actor);
		}
	}//end testAnOrphanFallsBackToItsStoredProject()

	/**
	 * A stored project that cannot be read counts as none, so only an admin deletes the edge.
	 *
	 * @return void
	 */
	public function testAnUnreadableStoredProjectLeavesTheEdgeToAnAdmin(): void {
		foreach (['p-gone', ''] as $stored) {
			foreach (['carol' => DependencyValidationException::CODE_FORBIDDEN, 'root' => 'deleted'] as $actor => $expected) {
				$objects = $this->objects();
				$objects->seed('dependency', 'e-stored', ['blocker' => 't-gone', 'blocked' => 't-gone2', 'type' => 'blocks', 'project' => $stored]);
				$answer = 'deleted';
				try {
					$this->service(objects: $objects, actor: $actor)->delete(id: 'e-stored');
				} catch (DependencyValidationException $e) {
					$answer = $e->getCode();
				}

				self::assertSame(expected: $expected, actual: $answer, message: $stored . ' / ' . $actor);
			}
		}
	}//end testAnUnreadableStoredProjectLeavesTheEdgeToAnAdmin()

	/**
	 * A task's own project wins over the edge's stored one.
	 *
	 * @return void
	 */
	public function testATasksProjectWinsOverTheStoredOne(): void {
		$objects = $this->objects();
		$objects->seed('dependency', 'e-stored', ['blocker' => 't-other', 'blocked' => 't-gone', 'type' => 'blocks', 'project' => self::PROJECT]);
		try {
			$this->service(objects: $objects, actor: 'carol')->delete(id: 'e-stored');
			self::fail('carol deleted an edge of the other project');
		} catch (DependencyValidationException $e) {
			self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $e->getCode());
		}

		$this->service(objects: $objects, actor: 'eve')->delete(id: 'e-stored');
		self::assertArrayNotHasKey(key: 'e-stored', array: $objects->rows['dependency']);
	}//end testATasksProjectWinsOverTheStoredOne()

	/**
	 * After the guard passes, the edge is deleted as the server, so OpenRegister's admin-only delete rule does not refuse a member.
	 *
	 * @return void
	 */
	public function testAGuardedDeleteRunsWithRbacOff(): void {
		$objects = $this->objects();
		$this->service(objects: $objects, actor: 'carol')->delete(id: 'e-in-project');

		self::assertSame(
			expected: [['uuid' => 'e-in-project', 'schema' => 'dependency', '_rbac' => false, '_multitenancy' => true]],
			actual: $objects->deletes
		);
	}//end testAGuardedDeleteRunsWithRbacOff()

	/**
	 * Without a session every delete is refused as unauthenticated, before any lookup.
	 *
	 * @return void
	 */
	public function testNoSessionIsRefusedBeforeAnyLookup(): void {
		foreach (array_merge(array_keys(self::EDGES), ['missing-edge']) as $edge) {
			$objects = $this->objects();
			try {
				$this->service(objects: $objects, actor: '')->delete(id: 'e-' . $edge);
				self::fail($edge . ': deleted without a session');
			} catch (DependencyValidationException $e) {
				self::assertSame(expected: DependencyValidationException::CODE_UNAUTHENTICATED, actual: $e->getCode(), message: $edge);
			}

			self::assertSame(expected: [], actual: $objects->finds, message: $edge);
			self::assertSame(expected: array_map(static fn (string $name): string => 'e-' . $name, array_keys(self::EDGES)), actual: array_keys($objects->rows['dependency']), message: $edge);
		}
	}//end testNoSessionIsRefusedBeforeAnyLookup()

	/**
	 * An in-memory OpenRegister that finds by the schema set before the call, and answers null when nothing is stored.
	 *
	 * @return InMemoryObjectService
	 */
	private function objects(): InMemoryObjectService {
		$objects = new class () extends InMemoryObjectService {
			/**
			 * Other identifiers (_id, slug) per table: [table][identifier] => uuid.
			 *
			 * @var array<string,array<string,string>>
			 */
			public array $aliases = [];

			/**
			 * Projects that OpenRegister's RBAC hides from every caller but an admin.
			 *
			 * @var array<int,string>
			 */
			public array $unreadable = [];

			/**
			 * The uuid each deleteObject() call named, before any lookup.
			 *
			 * @var array<int,string>
			 */
			public array $requested = [];

			private mixed $current = null;

			public function setSchema(mixed $schema): static {
				$this->current = $schema;
				return $this;
			}

			// As OpenRegister: the schema argument scopes the lookup (setSchema does not);
			// without it every table is tried and the first hit wins.
			public function find(
				int|string $id,
				?array $_extend = [],
				bool $files = false,
				mixed $register = null,
				mixed $schema = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $_render = true,
				bool $_audit = true,
			): ?object {
				foreach (($schema === null ? ['task', 'project', 'dependency'] : [(string)$schema]) as $table) {
					$uuid = $this->resolve(table: $table, id: (string)$id);
					if ($uuid !== null && ($_rbac === false || $table !== 'project' || in_array($uuid, $this->unreadable, true) === false)) {
						return parent::find($uuid, $_extend, $files, $register, $table, $_rbac, $_multitenancy, $_render, $_audit);
					}
				}

				$this->finds[] = ['id' => (string)$id, 'schema' => $schema, '_rbac' => $_rbac];
				return null;
			}

			public function deleteObject(
				ObjectDeletion $deletion,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $_retentionSweep = false,
			): bool {
				$schema = $deletion->target->schema;
				$this->requested[] = $deletion->uuid;
				$hit = $this->resolve(table: (string)$schema, id: $deletion->uuid);
				if ($hit === null) {
					throw new \RuntimeException('DoesNotExist in ' . $schema);
				}

				$resolved = new ObjectDeletion(
					uuid: $hit,
					target: $deletion->target,
					currentUser: $deletion->currentUser,
					permanent: $deletion->permanent
				);
				return parent::deleteObject($resolved, $_rbac, $_multitenancy, $_retentionSweep);
			}

			private function resolve(string $table, string $id): ?string {
				if (isset($this->rows[$table][$id]) === true) {
					return $id;
				}

				return ($this->aliases[$table][$id] ?? null);
			}
		};

		$objects->seed('project', self::PROJECT, ['title' => 'P', 'owner' => 'carol', 'members' => ['carol'], 'groups' => ['team']]);
		$objects->seed('project', 'p-2', ['title' => 'Other', 'owner' => 'eve', 'members' => ['eve']]);
		$objects->seed('task', 't-in', ['title' => 'In', 'project' => self::PROJECT]);
		$objects->seed('task', 't-in2', ['title' => 'In 2', 'project' => self::PROJECT]);
		$objects->seed('task', 't-loose', ['title' => 'Loose', 'project' => '']);
		$objects->seed('task', 't-other', ['title' => 'Other', 'project' => 'p-2']);
		foreach (self::EDGES as $name => $edge) {
			$objects->seed('dependency', 'e-' . $name, $edge);
		}

		return $objects;
	}//end objects()

	/**
	 * The guard and the delete bind to the same edge: an identifier another table also answers to
	 * (a task's numeric _id or slug) neither passes the guard on that task nor deletes another project's edge.
	 *
	 * @return void
	 */
	public function testTheGuardChecksTheEdgeThatIsDeleted(): void {
		foreach (['7', 'e-victim'] as $identifier) {
			$objects = $this->objects();
			$objects->seed('task', 't-carol', ['title' => 'Carol task', 'project' => self::PROJECT]);
			$objects->seed('dependency', 'e-victim', ['blocker' => 't-other', 'blocked' => 't-other', 'type' => 'blocks', 'project' => 'p-2']);
			$objects->aliases = ['task' => ['7' => 't-carol', 'e-victim' => 't-carol'], 'dependency' => ['7' => 'e-victim']];
			$answer = 'deleted';
			try {
				$this->service(objects: $objects, actor: 'carol')->delete(id: $identifier);
			} catch (DependencyValidationException $e) {
				$answer = $e->getCode();
			}

			self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $answer, message: $identifier);
			self::assertArrayHasKey(key: 'e-victim', array: $objects->rows['dependency'], message: $identifier);
			self::assertSame(expected: [], actual: $objects->deletes, message: $identifier);
		}

		$objects = $this->objects();
		$objects->seed('dependency', 'e-mine', ['blocker' => 't-in', 'blocked' => 't-in2', 'type' => 'blocks']);
		$objects->aliases = ['dependency' => ['42' => 'e-mine']];
		$this->service(objects: $objects, actor: 'carol')->delete(id: '42');
		self::assertSame(expected: ['e-mine'], actual: $objects->requested, message: 'deleted by the uuid of the edge that was checked');
		self::assertArrayNotHasKey(key: 'e-mine', array: $objects->rows['dependency']);

		// The membership check reads the project table, not a task that answers to the project's id.
		$objects = $this->objects();
		$objects->seed('task', 't-decoy', ['title' => 'Decoy', 'project' => self::PROJECT, 'owner' => 'carol', 'members' => ['carol']]);
		$objects->aliases = ['task' => ['p-2' => 't-decoy']];
		$objects->seed('dependency', 'e-eve', ['blocker' => 't-other', 'blocked' => 't-other', 'type' => 'blocks']);
		try {
			$this->service(objects: $objects, actor: 'carol')->delete(id: 'e-eve');
			self::fail('carol deleted an edge of p-2 through a task that answers to p-2');
		} catch (DependencyValidationException $e) {
			self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $e->getCode());
		}

		self::assertArrayHasKey(key: 'e-eve', array: $objects->rows['dependency']);
	}//end testTheGuardChecksTheEdgeThatIsDeleted()

	/**
	 * An object without both ends is not a dependency, whoever asks; nothing is deleted.
	 *
	 * @return void
	 */
	public function testAnObjectWithoutBothEndsIsNotADependency(): void {
		foreach (['carol', 'root'] as $actor) {
			$objects = $this->objects();
			$objects->seed('dependency', 'e-half', ['blocker' => 't-in', 'type' => 'blocks']);
			try {
				$this->service(objects: $objects, actor: $actor)->delete(id: 'e-half');
				self::fail($actor . ': deleted an object that is not a dependency');
			} catch (DependencyValidationException $e) {
				self::assertSame(expected: DependencyValidationException::CODE_NOT_FOUND, actual: $e->getCode(), message: $actor);
			}

			self::assertSame(expected: [], actual: $objects->deletes, message: $actor);
		}

		$objects = $this->objects();
		$objects->seed('dependency', '', ['blocker' => 't-in', 'blocked' => 't-in2', 'type' => 'blocks']);
		$objects->aliases = ['dependency' => ['9' => '']];
		try {
			$this->service(objects: $objects, actor: 'root')->delete(id: '9');
			self::fail('deleted an edge without a uuid');
		} catch (DependencyValidationException $e) {
			self::assertSame(expected: DependencyValidationException::CODE_NOT_FOUND, actual: $e->getCode());
		}

		self::assertSame(expected: [], actual: $objects->deletes);
	}//end testAnObjectWithoutBothEndsIsNotADependency()

	/**
	 * The stored project counts only when the caller can read it: one RBAC hides is no project, so only an admin deletes the edge.
	 *
	 * @return void
	 */
	public function testAStoredProjectTheCallerCannotReadDoesNotCount(): void {
		$objects = $this->objects();
		$objects->unreadable = [self::PROJECT];
		$objects->seed('dependency', 'e-stored', ['blocker' => 't-gone', 'blocked' => 't-gone2', 'type' => 'blocks', 'project' => self::PROJECT]);
		try {
			$this->service(objects: $objects, actor: 'carol')->delete(id: 'e-stored');
			self::fail('deleted through a project the caller cannot read');
		} catch (DependencyValidationException $e) {
			self::assertSame(expected: DependencyValidationException::CODE_FORBIDDEN, actual: $e->getCode());
			self::assertSame(expected: 'Only an admin may delete a dependency whose tasks belong to no project.', actual: $e->getMessage());
		}

		self::assertArrayHasKey(key: 'e-stored', array: $objects->rows['dependency']);
		self::assertContains(needle: ['id' => self::PROJECT, 'schema' => 'project', '_rbac' => true], haystack: $objects->finds);
	}//end testAStoredProjectTheCallerCannotReadDoesNotCount()

	/**
	 * The dependency service acting for one user, or for nobody.
	 *
	 * @param InMemoryObjectService $objects The in-memory OpenRegister.
	 * @param string                $actor   The acting user id; empty for no session.
	 * @param bool                  $groups  Whether a group manager is wired in.
	 *
	 * @return DependencyService
	 */
	private function service(InMemoryObjectService $objects, string $actor, bool $groups=true): DependencyService {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groupManager = null;
		if ($groups === true) {
			$groupManager = $this->createMock(originalClassName: IGroupManager::class);
			$groupManager->method('getUserGroupIds')->willReturnCallback(
				static fn (IUser $who): array => ($who->getUID() === 'grouped' ? ['team'] : [])
			);
			$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($uid === 'root'));
		}

		return new DependencyService(
			repository: new DependencyRepository(container: $container, logger: new NullLogger(), appManager: $appManager),
			graph: new DependencyGraph(),
			userSession: $session,
			logger: new NullLogger(),
			groupManager: $groupManager
		);
	}//end service()
}//end class
