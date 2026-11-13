<?php

/**
 * Unit tests for DependencyRepository's read paths.
 *
 * Covers the container failure behind objectService(), the task lookup's
 * empty-id and missing-row answers, a project without tasks, and the result
 * shapes OpenRegister can return (paginated `results`, a list of entities, a
 * non-array, and rows that are neither entity nor array).
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
use OCA\Planninq\Service\DependencyRepository;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for DependencyRepository's lookups and result normalisation.
 *
 * @covers \OCA\Planninq\Service\DependencyRepository
 * @uses   \OCA\Planninq\Exception\DependencyValidationException
 */
class DependencyRepositoryReadsTest extends TestCase {

	/**
	 * Build the repository with OpenRegister installed and the given container.
	 *
	 * @param ContainerInterface|null $container The container, a never-called mock when null.
	 * @param LoggerInterface|null    $logger    The logger, a mock when null.
	 *
	 * @return DependencyRepository
	 */
	private function repository(?ContainerInterface $container = null, ?LoggerInterface $logger = null): DependencyRepository {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new DependencyRepository(
			container: ($container ?? $this->createMock(originalClassName: ContainerInterface::class)),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class)),
			appManager: $appManager,
		);
	}//end repository()

	/**
	 * An ObjectService double answering searches with a fixed value and finds from a map.
	 *
	 * @param mixed                $searchResult What searchObjectsBySlug() returns.
	 * @param array<string,object> $found        Map of id => entity for find().
	 *
	 * @return object
	 */
	private function objectService(mixed $searchResult = [], array $found = []): object {
		return new class($searchResult, $found) {
			/** @var array<int,array<string,mixed>> */
			public array $searches = [];

			/** @var array<int,string> */
			public array $schemas = [];

			/**
			 * @param mixed                $searchResult Search answer.
			 * @param array<string,object> $found        Find answers.
			 */
			public function __construct(private mixed $searchResult, private array $found) {
			}

			public function setRegister(string $register): void {
			}

			public function setSchema(string $schema): void {
				$this->schemas[] = $schema;
			}

			public function find(string $id, mixed $register = null, mixed $schema = null): ?object {
				return ($this->found[$id] ?? null);
			}

			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): mixed {
				$this->searches[] = ['schema' => $schemaSlug, 'filters' => $filters, '_rbac' => $_rbac, '_multitenancy' => $_multitenancy];
				return $this->searchResult;
			}
		};
	}//end objectService()

	/**
	 * An entity as OpenRegister returns it: uuid and object data.
	 *
	 * @param string              $uuid The uuid.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return object
	 */
	private function entity(string $uuid, array $data): object {
		return new class($uuid, $data) {
			/**
			 * @param string              $uuid The uuid.
			 * @param array<string,mixed> $data The data.
			 */
			public function __construct(private string $uuid, private array $data) {
			}

			public function getUuid(): string {
				return $this->uuid;
			}

			public function getObject(): array {
				return $this->data;
			}
		};
	}//end entity()

	/**
	 * A container that throws is reported as an unavailable OpenRegister, and logged.
	 *
	 * @return void
	 */
	public function testAContainerFailureIsReportedAsUnavailable(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::once())
			->method('get')
			->with('OCA\\OpenRegister\\Service\\ObjectService')
			->willThrowException(new \RuntimeException('boom'));

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Planninq: OpenRegister ObjectService unavailable', ['exception' => 'boom']);

		try {
			$this->repository(container: $container, logger: $logger)->objectService();
			self::fail('Expected DependencyValidationException');
		} catch (DependencyValidationException $e) {
			self::assertSame(DependencyValidationException::CODE_UNAVAILABLE, $e->getCode());
			self::assertSame('OpenRegister is not available.', $e->getMessage());
		}
	}//end testAContainerFailureIsReportedAsUnavailable()

	/**
	 * An empty task id answers null without touching OpenRegister.
	 *
	 * @return void
	 */
	public function testFetchTaskWithAnEmptyIdAnswersNull(): void {
		$objects = $this->objectService();

		self::assertNull($this->repository()->fetchTask(objectService: $objects, taskId: ''));
		self::assertSame([], $objects->schemas);
	}//end testFetchTaskWithAnEmptyIdAnswersNull()

	/**
	 * A task OpenRegister does not find answers null; a found one answers its data.
	 *
	 * @return void
	 */
	public function testFetchTaskAnswersNullForAMissingTaskAndTheDataOtherwise(): void {
		$objects = $this->objectService(found: ['t-1' => $this->entity('t-1', ['title' => 'One', 'project' => 'p'])]);

		self::assertNull($this->repository()->fetchTask(objectService: $objects, taskId: 't-missing'));
		self::assertSame(['title' => 'One', 'project' => 'p'], $this->repository()->fetchTask(objectService: $objects, taskId: 't-1'));
		self::assertSame(['task', 'task'], $objects->schemas);
	}//end testFetchTaskAnswersNullForAMissingTaskAndTheDataOtherwise()

	/**
	 * A project without tasks has no edges, and the edge table is never read.
	 *
	 * @return void
	 */
	public function testAProjectWithoutTasksHasNoEdges(): void {
		$objects = $this->objectService(searchResult: ['results' => []]);

		self::assertSame([], $this->repository()->fetchProjectEdges(objectService: $objects, projectId: 'p-1'));
		self::assertSame(
			[['schema' => 'task', 'filters' => ['project' => 'p-1'], '_rbac' => true, '_multitenancy' => true]],
			$objects->searches
		);
	}//end testAProjectWithoutTasksHasNoEdges()

	/**
	 * A paginated result's `results` are read, entity rows by their uuid, and rows without an id skipped.
	 *
	 * @return void
	 */
	public function testPaginatedEntityResultsGiveTheTaskIds(): void {
		$objects = $this->objectService(
			searchResult: [
				'results' => [
					$this->entity('t-1', []),
					['@self' => ['id' => 't-2']],
					['id' => 't-3'],
					['title' => 'no id'],
				],
				'total'   => 4,
			]
		);

		self::assertSame(['t-1', 't-2', 't-3'], $this->repository()->fetchProjectTaskIds(objectService: $objects, projectId: 'p-1'));
	}//end testPaginatedEntityResultsGiveTheTaskIds()

	/**
	 * A non-array search result is read as no rows.
	 *
	 * @return void
	 */
	public function testANonArrayResultIsNoRows(): void {
		$objects = $this->objectService(searchResult: null);

		self::assertSame([], $this->repository()->fetchProjectTaskIds(objectService: $objects, projectId: 'p-1'));
		self::assertSame([], $this->repository()->fetchAllEdges(objectService: $objects));
	}//end testANonArrayResultIsNoRows()

	/**
	 * Edges are read as the system: entity rows give their data and uuid, a scalar row gives an empty edge.
	 *
	 * @return void
	 */
	public function testFetchAllEdgesNormalisesEntityAndScalarRows(): void {
		$objects = $this->objectService(
			searchResult: [
				$this->entity('e-1', ['blocker' => 'A', 'blocked' => 'B']),
				['id' => 'e-2', 'blocker' => 'B', 'blocked' => 'C'],
				'garbage',
			]
		);

		self::assertSame(
			[
				['blocker' => 'A', 'blocked' => 'B', 'id' => 'e-1'],
				['id' => 'e-2', 'blocker' => 'B', 'blocked' => 'C'],
				['id' => ''],
			],
			$this->repository()->fetchAllEdges(objectService: $objects)
		);
		self::assertSame(
			[['schema' => 'dependency', 'filters' => [], '_rbac' => false, '_multitenancy' => false]],
			$objects->searches
		);
	}//end testFetchAllEdgesNormalisesEntityAndScalarRows()

	/**
	 * A project's edges are those whose blocker is one of its tasks.
	 *
	 * @return void
	 */
	public function testProjectEdgesAreThoseWhoseBlockerIsInTheProject(): void {
		$objects = new class () {
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array {
				if ($schemaSlug === 'task') {
					return [['id' => 'A'], ['id' => 'B']];
				}

				return [
					['id' => 'e-1', 'blocker' => 'A', 'blocked' => 'B'],
					['id' => 'e-2', 'blocker' => 'X', 'blocked' => 'A'],
				];
			}
		};

		self::assertSame(
			[['id' => 'e-1', 'blocker' => 'A', 'blocked' => 'B']],
			$this->repository()->fetchProjectEdges(objectService: $objects, projectId: 'p-1')
		);
	}//end testProjectEdgesAreThoseWhoseBlockerIsInTheProject()
}//end class
