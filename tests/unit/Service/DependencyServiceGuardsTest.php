<?php

/**
 * Unit tests for DependencyService's refusal paths.
 *
 * Covers the guards create() runs before it writes (an empty end, a task that
 * does not exist, no session, a project that cannot be found) and the
 * early-outs of the task-delete cascade (no task id, an edge row without an id).
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

use OCA\Planninq\Exception\DependencyValidationException;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\DependencyService;
use OCP\App\IAppManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for the guards in DependencyService.
 *
 * @covers \OCA\Planninq\Service\DependencyService
 * @uses   \OCA\Planninq\Service\DependencyRepository
 * @uses   \OCA\Planninq\Exception\DependencyValidationException
 */
class DependencyServiceGuardsTest extends TestCase {

	/**
	 * Build the service around an ObjectService double and an optional signed-in user.
	 *
	 * @param object               $objects The ObjectService double.
	 * @param string               $uid     The signed-in user, '' for no session.
	 * @param LoggerInterface|null $logger  The logger, a mock when null.
	 *
	 * @return DependencyService
	 */
	private function service(object $objects, string $uid = 'carol', ?LoggerInterface $logger = null): DependencyService {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$logger = ($logger ?? $this->createMock(originalClassName: LoggerInterface::class));

		$user = null;
		if ($uid !== '') {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DependencyService(
			repository: new DependencyRepository(container: $container, logger: $logger, appManager: $appManager),
			graph: new DependencyGraph(),
			userSession: $session,
			logger: $logger,
		);
	}//end service()

	/**
	 * An ObjectService double: rows per schema, find() answers null on a miss, deletes recorded.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $rows  Schema => uuid => data.
	 * @param array<int,array<string,mixed>>                  $edges The dependency search answer.
	 *
	 * @return object
	 */
	private function objects(array $rows = [], array $edges = []): object {
		return new class($rows, $edges) {
			/** @var array<int,string> */
			public array $deleted = [];

			/** @var array<int,array<string,mixed>> */
			public array $saved = [];

			/**
			 * @param array<string,array<string,array<string,mixed>>> $rows  Rows.
			 * @param array<int,array<string,mixed>>                  $edges Edges.
			 */
			public function __construct(private array $rows, private array $edges) {
			}

			public function setRegister(string $register): void {
			}

			public function setSchema(string $schema): void {
			}

			public function find(string $id, mixed $register = null, mixed $schema = null): ?object {
				$data = ($this->rows[(string)$schema][$id] ?? null);
				if ($data === null) {
					return null;
				}

				return new class($data) {
					/**
					 * @param array<string,mixed> $data The data.
					 */
					public function __construct(private array $data) {
					}

					public function getObject(): array {
						return $this->data;
					}
				};
			}

			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array {
				return $this->edges;
			}

			public function saveObject(array $object, string $register, string $schema, bool $_rbac = true): object {
				$this->saved[] = $object;
				throw new \LogicException('a refused create must not save');
			}

			public function deleteObject(string $register, string $schema, string $uuid, bool $_rbac = true): bool {
				$this->deleted[] = $uuid;
				return true;
			}
		};
	}//end objects()

	/**
	 * Run create() and answer the refusal's code and message.
	 *
	 * @param DependencyService $service The service.
	 * @param string            $blocker The blocker.
	 * @param string            $blocked The blocked task.
	 *
	 * @return array{0:int,1:string}
	 */
	private function refusal(DependencyService $service, string $blocker, string $blocked): array {
		try {
			$service->create($blocker, $blocked);
		} catch (DependencyValidationException $e) {
			return [$e->getCode(), $e->getMessage()];
		}

		self::fail('create(' . $blocker . ', ' . $blocked . ') was not refused');
	}//end refusal()

	/**
	 * An empty end is refused as a validation error before OpenRegister is reached.
	 *
	 * @return void
	 */
	public function testAnEmptyEndIsRefused(): void {
		$objects = $this->objects();
		foreach ([['', 'B'], ['A', ''], ['', '']] as [$blocker, $blocked]) {
			self::assertSame(
				[DependencyValidationException::CODE_VALIDATION, 'Both blocker and blocked task are required.'],
				$this->refusal($this->service(objects: $objects), $blocker, $blocked)
			);
		}

		self::assertSame([], $objects->saved);
	}//end testAnEmptyEndIsRefused()

	/**
	 * A task that does not exist is refused as not found, whichever end it is.
	 *
	 * @return void
	 */
	public function testAMissingTaskIsRefusedAsNotFound(): void {
		$objects = $this->objects(rows: ['task' => ['A' => ['project' => 'P']]]);
		foreach ([['A', 'gone'], ['gone', 'A']] as [$blocker, $blocked]) {
			self::assertSame(
				[DependencyValidationException::CODE_NOT_FOUND, 'One or both tasks could not be found.'],
				$this->refusal($this->service(objects: $objects), $blocker, $blocked)
			);
		}
	}//end testAMissingTaskIsRefusedAsNotFound()

	/**
	 * Without a session, a create between two tasks of one project is refused as unauthenticated.
	 *
	 * @return void
	 */
	public function testNoSessionIsRefusedAsUnauthenticated(): void {
		$objects = $this->objects(
			rows: [
				'task'    => ['A' => ['project' => 'P'], 'B' => ['project' => 'P']],
				'project' => ['P' => ['owner' => 'carol']],
			]
		);

		self::assertSame(
			[DependencyValidationException::CODE_UNAUTHENTICATED, 'Authentication required.'],
			$this->refusal($this->service(objects: $objects, uid: ''), 'A', 'B')
		);
		self::assertSame([], $objects->saved);
	}//end testNoSessionIsRefusedAsUnauthenticated()

	/**
	 * Tasks naming a project that cannot be found are refused as not found.
	 *
	 * @return void
	 */
	public function testAProjectThatCannotBeFoundIsRefused(): void {
		$objects = $this->objects(rows: ['task' => ['A' => ['project' => 'P'], 'B' => ['project' => 'P']]]);

		self::assertSame(
			[DependencyValidationException::CODE_NOT_FOUND, 'Project not found.'],
			$this->refusal($this->service(objects: $objects), 'A', 'B')
		);
		self::assertSame([], $objects->saved);
	}//end testAProjectThatCannotBeFoundIsRefused()

	/**
	 * The cascade for an empty task id removes nothing and does not reach OpenRegister.
	 *
	 * @return void
	 */
	public function testTheCascadeForAnEmptyTaskIdRemovesNothing(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::never())->method('get');
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		$service = new DependencyService(
			repository: new DependencyRepository(container: $container, logger: $logger, appManager: $appManager),
			graph: new DependencyGraph(),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			logger: $logger,
		);

		self::assertSame(0, $service->removeEdgesForTask(''));
	}//end testTheCascadeForAnEmptyTaskIdRemovesNothing()

	/**
	 * An edge row without an id is skipped by the cascade; when nothing matches, nothing is logged.
	 *
	 * @return void
	 */
	public function testTheCascadeSkipsAnEdgeWithoutAnId(): void {
		$objects = $this->objects(
			edges: [
				['blocker' => 'A', 'blocked' => 'B'],
				['id' => 'e-2', 'blocker' => 'B', 'blocked' => 'A'],
			]
		);
		self::assertSame(1, $this->service(objects: $objects)->removeEdgesForTask('A'));
		self::assertSame(['e-2'], $objects->deleted);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::never())->method('info');
		$untouched = $this->objects(edges: [['blocker' => 'A', 'blocked' => 'B']]);
		self::assertSame(0, $this->service(objects: $untouched, logger: $logger)->removeEdgesForTask('A'));
		self::assertSame([], $untouched->deleted);
	}//end testTheCascadeSkipsAnEdgeWithoutAnId()
}//end class
