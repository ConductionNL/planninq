<?php

/**
 * Unit tests for LabelService.
 *
 * Covers the pure usage-count aggregation (counting tasks per label UUID, with
 * de-duplication of duplicate UUIDs within one task), the listing-with-usage
 * shape (sorted by title, usageCount attached), and the cascade delete against a
 * stub OpenRegister ObjectService: it sweeps the deleted UUID from every
 * referencing task (and only that UUID), leaves other labels untouched, deletes
 * the label object last, and is idempotent on a re-run after a partial sweep.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\LabelService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for LabelService.
 */
class LabelServiceTest extends TestCase {

	/**
	 * Mock container.
	 *
	 * @var ContainerInterface&MockObject
	 */
	private ContainerInterface&MockObject $container;

	/**
	 * Mock logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}//end setUp()

	/**
	 * Build the service with the current mocks.
	 *
	 * @return LabelService
	 */
	private function service(): LabelService {
		return new LabelService(container: $this->container, logger: $this->logger);
	}//end service()

	// ── Pure usage aggregation ───────────────────────────────────────────────

	/**
	 * countUsageByLabel counts each referencing task once per distinct UUID.
	 *
	 * @return void
	 */
	public function testCountUsageByLabel(): void {
		$tasks = [
			['labels' => ['L1', 'L2']],
			['labels' => ['L1']],
			['labels' => []],
			['labels' => ['L3', 'L3']], // duplicate within one task counts once
			['nolabels' => true],       // missing labels key ignored
		];

		$counts = LabelService::countUsageByLabel(tasks: $tasks);

		self::assertSame(2, $counts['L1']);
		self::assertSame(1, $counts['L2']);
		self::assertSame(1, $counts['L3']);
		self::assertArrayNotHasKey('', $counts);
	}//end testCountUsageByLabel()

	/**
	 * An empty task list yields an empty count map.
	 *
	 * @return void
	 */
	public function testCountUsageByLabelEmpty(): void {
		self::assertSame([], LabelService::countUsageByLabel(tasks: []));
	}//end testCountUsageByLabelEmpty()

	// ── Listing with usage ───────────────────────────────────────────────────

	/**
	 * listWithUsage attaches usage counts and sorts labels by title.
	 *
	 * @return void
	 */
	public function testListWithUsageSortsAndCounts(): void {
		$objectService = $this->makeObjectService(
			labels: [
				['@self' => ['id' => 'L-bug'], 'title' => 'Bug', 'color' => '#E74C3C'],
				['@self' => ['id' => 'L-area'], 'title' => 'Area', 'color' => '#4376FC'],
			],
			tasks: [
				['@self' => ['id' => 'T1'], 'labels' => ['L-bug']],
				['@self' => ['id' => 'T2'], 'labels' => ['L-bug']],
			],
		);
		$this->container->method('get')->willReturn($objectService);

		$labels = $this->service()->listWithUsage();

		self::assertCount(2, $labels);
		// Sorted by title: Area before Bug.
		self::assertSame('Area', $labels[0]['title']);
		self::assertSame(0, $labels[0]['usageCount']);
		self::assertSame('Bug', $labels[1]['title']);
		self::assertSame(2, $labels[1]['usageCount']);
		self::assertSame('L-bug', $labels[1]['id']);
	}//end testListWithUsageSortsAndCounts()

	/**
	 * listWithUsage returns an empty array when OpenRegister is unavailable.
	 *
	 * @return void
	 */
	public function testListWithUsageOpenRegisterUnavailable(): void {
		$this->container->method('get')->willThrowException(new \RuntimeException('no OR'));
		self::assertSame([], $this->service()->listWithUsage());
	}//end testListWithUsageOpenRegisterUnavailable()

	// ── Cascade delete ───────────────────────────────────────────────────────

	/**
	 * Cascade removes only the deleted UUID from referencing tasks, leaves other
	 * labels untouched, deletes the label object last, and reports tasksUpdated.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeSweepsAndDeletes(): void {
		$saveSink = [];
		$deleteSink = [];
		$objectService = $this->makeObjectService(
			labels: [['@self' => ['id' => 'L1'], 'title' => 'Bug', 'color' => '#E74C3C']],
			tasks: [
				['@self' => ['id' => 'T1'], 'title' => 'a', 'labels' => ['L1', 'L2']],
				['@self' => ['id' => 'T2'], 'title' => 'b', 'labels' => ['L2']],
				['@self' => ['id' => 'T3'], 'title' => 'c', 'labels' => ['L1']],
			],
			saveSink: $saveSink,
			deleteSink: $deleteSink,
		);
		$this->container->method('get')->willReturn($objectService);

		$result = $this->service()->deleteWithCascade(labelId: 'L1');

		// Two tasks referenced L1 (T1, T3); T2 untouched.
		self::assertSame(2, $result['tasksUpdated']);
		self::assertCount(2, $saveSink);

		$byId = [];
		foreach ($saveSink as $save) {
			$byId[$save['uuid']] = $save['object'];
		}
		self::assertArrayHasKey('T1', $byId);
		self::assertArrayHasKey('T3', $byId);
		self::assertArrayNotHasKey('T2', $byId);

		// T1 keeps L2, drops L1.
		self::assertSame(['L2'], array_values($byId['T1']['labels']));
		// T3 ends with an empty label array.
		self::assertSame([], array_values($byId['T3']['labels']));

		// Label object deleted last.
		self::assertSame(['L1'], $deleteSink);
	}//end testDeleteWithCascadeSweepsAndDeletes()

	/**
	 * Re-running the cascade after a partial sweep touches only remaining tasks
	 * (idempotent): a task already swept is not saved again.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeIdempotentRerun(): void {
		$saveSink = [];
		$deleteSink = [];
		// Simulate a state after a partial first run: T1 already swept (no L1),
		// T3 still references L1.
		$objectService = $this->makeObjectService(
			labels: [['@self' => ['id' => 'L1'], 'title' => 'Bug', 'color' => '#E74C3C']],
			tasks: [
				['@self' => ['id' => 'T1'], 'title' => 'a', 'labels' => ['L2']],
				['@self' => ['id' => 'T3'], 'title' => 'c', 'labels' => ['L1']],
			],
			saveSink: $saveSink,
			deleteSink: $deleteSink,
		);
		$this->container->method('get')->willReturn($objectService);

		$result = $this->service()->deleteWithCascade(labelId: 'L1');

		// Only T3 still carried L1.
		self::assertSame(1, $result['tasksUpdated']);
		self::assertCount(1, $saveSink);
		self::assertSame('T3', $saveSink[0]['uuid']);
	}//end testDeleteWithCascadeIdempotentRerun()

	/**
	 * Deleting with no referencing tasks still deletes the label and reports 0.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeNoReferences(): void {
		$deleteSink = [];
		$objectService = $this->makeObjectService(
			labels: [['@self' => ['id' => 'L9'], 'title' => 'Unused', 'color' => '#4376FC']],
			tasks: [['@self' => ['id' => 'T1'], 'title' => 'a', 'labels' => ['L1']]],
			deleteSink: $deleteSink,
		);
		$this->container->method('get')->willReturn($objectService);

		$result = $this->service()->deleteWithCascade(labelId: 'L9');

		self::assertSame(0, $result['tasksUpdated']);
		self::assertSame(['L9'], $deleteSink);
	}//end testDeleteWithCascadeNoReferences()

	/**
	 * An empty label id is rejected before any ObjectService call.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeRejectsEmptyId(): void {
		$this->container->expects($this->never())->method('get');
		$this->expectException(\RuntimeException::class);
		$this->service()->deleteWithCascade(labelId: '');
	}//end testDeleteWithCascadeRejectsEmptyId()

	/**
	 * Cascade throws when OpenRegister is unavailable.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeOpenRegisterUnavailable(): void {
		$this->container->method('get')->willThrowException(new \RuntimeException('no OR'));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('OpenRegister is not available.');
		$this->service()->deleteWithCascade(labelId: 'L1');
	}//end testDeleteWithCascadeOpenRegisterUnavailable()

	/**
	 * Build a stub ObjectService returning the supplied labels/tasks by schema,
	 * and recording saveObject/deleteObject calls into the supplied sinks.
	 *
	 * @param array<int,array<string,mixed>> $labels Label rows (each with a `@self.id`).
	 * @param array<int,array<string,mixed>> $tasks Task rows (each with a `@self.id`).
	 * @param array<int,array<string,mixed>> $saveSink Reference recording {uuid, object} per save.
	 * @param array<int,string> $deleteSink Reference recording deleted UUIDs.
	 *
	 * @return object
	 */
	private function makeObjectService(
		array $labels = [],
		array $tasks = [],
		array &$saveSink = [],
		array &$deleteSink = [],
	): object {
		return new class($labels, $tasks, $saveSink, $deleteSink) {
			/** @var array<int,array<string,mixed>> */
			private array $labels;

			/** @var array<int,array<string,mixed>> */
			private array $tasks;

			/** @var array<int,array<string,mixed>> */
			private array $saveSink;

			/** @var array<int,string> */
			private array $deleteSink;

			private string $schema = '';

			/**
			 * @param array<int,array<string,mixed>> $labels Label fixtures.
			 * @param array<int,array<string,mixed>> $tasks Task fixtures.
			 * @param array<int,array<string,mixed>> $saveSink Save recorder.
			 * @param array<int,string> $deleteSink Delete recorder.
			 */
			public function __construct(array $labels, array $tasks, array &$saveSink, array &$deleteSink) {
				$this->labels = $labels;
				$this->tasks = $tasks;
				$this->saveSink = &$saveSink;
				$this->deleteSink = &$deleteSink;
			}

			/**
			 * @param string $register Register slug (ignored).
			 *
			 * @return void
			 */
			public function setRegister(string $register): void {
			}

			/**
			 * @param string $schema Schema slug.
			 *
			 * @return void
			 */
			public function setSchema(string $schema): void {
				$this->schema = $schema;
			}

			/**
			 * Mirrors the real ObjectService entry point the production code
			 * calls. This double previously only offered `searchObjects()`,
			 * which LabelService stopped calling when the register/schema moved
			 * from setter state into explicit slug arguments — so every caller
			 * died with "undefined method searchObjectsBySlug()". The schema is
			 * now read from the ARGUMENT, not from `$this->schema`, which is
			 * exactly the invariant the production change established.
			 *
			 * @param string $registerSlug Register slug.
			 * @param string $schemaSlug Schema slug.
			 * @param array<string,mixed> $filters Optional filters (ignored — fixtures are pre-scoped).
			 *
			 * @return array<int,array<string,mixed>>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = []): array {
				if ($schemaSlug === 'task') {
					return $this->tasks;
				}
				if ($schemaSlug === 'label') {
					return $this->labels;
				}
				return [];
			}

			/**
			 * @param array<string,mixed> $object The object payload.
			 * @param string $register Register slug.
			 * @param string $schema Schema slug.
			 * @param string|null $uuid The object UUID being saved.
			 * @param bool $_rbac RBAC flag.
			 *
			 * @return object
			 */
			public function saveObject(array $object, string $register, string $schema, ?string $uuid = null, bool $_rbac = true): object {
				$this->saveSink[] = ['uuid' => $uuid, 'object' => $object];
				return new class($object) {
					/** @var array<string,mixed> */
					private array $data;

					/**
					 * @param array<string,mixed> $data Object data.
					 */
					public function __construct(array $data) {
						$this->data = $data;
					}

					/**
					 * @return array<string,mixed>
					 */
					public function jsonSerialize(): array {
						return $this->data;
					}
				};
			}

			/**
			 * @param string $register Register slug.
			 * @param string $schema Schema slug.
			 * @param string $uuid The object UUID.
			 *
			 * @return void
			 */
			public function deleteObject(string $register, string $schema, string $uuid): void {
				$this->deleteSink[] = $uuid;
			}
		};
	}//end makeObjectService()
}//end class
