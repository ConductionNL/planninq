<?php

/**
 * Tests for RetentionCategoryService: the admin's selectielijst category per
 * planninq schema, listed from OpenRegister's selection list and written into
 * the live schemas' archive blocks.
 *
 * The live schemas start as the register file's own archive blocks, so the
 * write is tested against what the import leaves behind; a `project` schema
 * of another app's register must stay untouched.
 *
 * @category Tests
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

use OCA\Planninq\Repair\ApplyRetentionCategories;
use OCA\Planninq\Service\RetentionCategoryService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\RetentionCategoryService
 * @uses   \OCA\Planninq\Repair\ApplyRetentionCategories
 */
class RetentionCategoryServiceTest extends TestCase {

	/**
	 * Live schemas by id: 10-12 are planninq's, 99 is another app's `project`.
	 *
	 * @var array<int,object>
	 */
	private array $schemas = [];

	/**
	 * Ids of the schemas written, in order.
	 *
	 * @var array<int,int>
	 */
	public array $updates = [];

	/**
	 * The app config values.
	 *
	 * @var array<string,string>
	 */
	private array $config = [];

	/**
	 * The archival settings OpenRegister answers with.
	 *
	 * @var array<string,mixed>
	 */
	private array $archival = ['selectielijstRegister' => 7, 'selectielijstSchema' => 8];

	/**
	 * The filters findAll() was called with.
	 *
	 * @var array<string,mixed>|null
	 */
	public ?array $listed = null;

	/**
	 * Build the live schemas from the register file.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$register = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
		$schemas  = $register['components']['schemas'];
		$this->schemas = [
			10 => $this->schema(id: 10, slug: 'project', archive: $schemas['project']['archive']),
			11 => $this->schema(id: 11, slug: 'task', archive: $schemas['task']['archive']),
			12 => $this->schema(id: 12, slug: 'plannedTimeEntry', archive: $schemas['plannedTimeEntry']['archive']),
			99 => $this->schema(id: 99, slug: 'project', archive: ['enabled' => true, 'classification' => 'other-app']),
		];
		$this->updates = [];
	}//end setUp()

	/**
	 * A stand-in for OpenRegister's Schema entity.
	 *
	 * @param int                 $id      The id.
	 * @param string              $slug    The slug.
	 * @param array<string,mixed> $archive The archive block.
	 *
	 * @return object
	 */
	private function schema(int $id, string $slug, array $archive): object {
		return new class($id, $slug, $archive) {
			// phpcs:disable
			public function __construct(public int $id, public string $slug, public array $archive) {
			}
			public function getId(): int {
				return $this->id;
			}
			public function getArchive(): array {
				return $this->archive;
			}
			public function setArchive(array $archive): void {
				$this->archive = $archive;
			}
			// phpcs:enable
		};
	}//end schema()

	/**
	 * The live schema with this id.
	 *
	 * @param int $id The id.
	 *
	 * @return object
	 */
	public function live(int $id): object {
		return $this->schemas[$id];
	}//end live()

	/**
	 * The service over the stand-ins.
	 *
	 * @param array<int,mixed> $rows      The selection-list objects.
	 * @param bool             $installed Whether OpenRegister is installed.
	 *
	 * @return RetentionCategoryService
	 */
	private function service(array $rows = [], bool $installed = true): RetentionCategoryService {
		$test = $this;
		$register = new class {
			// phpcs:disable
			public function getSchemas(): array {
				return [10, 11, 12];
			}
			// phpcs:enable
		};
		$registerMapper = new class($register) {
			// phpcs:disable
			public function __construct(private object $register) {
			}
			public function find($id) {
				if ($id !== 'planninq') {
					throw new \RuntimeException('unknown register ' . $id);
				}
				return $this->register;
			}
			// phpcs:enable
		};
		$schemaMapper = new class($test) {
			// phpcs:disable
			public function __construct(private RetentionCategoryServiceTest $test) {
			}
			public function findBySlug(string $slug): array {
				return match ($slug) {
					'project' => [$this->test->live(99), $this->test->live(10)],
					'task' => [$this->test->live(11)],
					'plannedTimeEntry' => [$this->test->live(12)],
					default => [],
				};
			}
			public function update($schema) {
				$this->test->updates[] = $schema->getId();
				return $schema;
			}
			// phpcs:enable
		};
		$archival = $this->archival;
		$settings = new class($archival) {
			// phpcs:disable
			public function __construct(private array $archival) {
			}
			public function getArchivalSettingsOnly(): array {
				return $this->archival;
			}
			// phpcs:enable
		};
		$objects = new class($test, $rows) {
			// phpcs:disable
			public function __construct(private RetentionCategoryServiceTest $test, private array $rows) {
			}
			public function findAll(array $config = [], bool $_rbac = true, bool $_multitenancy = true): array {
				$this->test->listed = $config['filters'];
				return $this->rows;
			}
			// phpcs:enable
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registerMapper,
				'OCA\\OpenRegister\\Db\\SchemaMapper' => $schemaMapper,
				'OCA\\OpenRegister\\Kernel\\Contracts\\AppSettingsInterface' => $settings,
				'OCA\\OpenRegister\\Service\\ObjectService' => $objects,
				default => throw new \RuntimeException('unexpected service: ' . $id),
			}
		);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn($installed);

		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->config[$key] ?? $default)
		);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->config[$key] = $value;
				return true;
			}
		);

		return new RetentionCategoryService(
			appManager: $appManager,
			container: $container,
			appConfig: $appConfig,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * Selection-list rows: an entity, a serialised array, a second revision of B1 and a row without a category.
	 *
	 * @return array<int,mixed>
	 */
	private static function rows(): array {
		$entity = new class {
			// phpcs:disable
			public function getObject(): array {
				return ['categorie' => 'B1', 'omschrijving' => 'Projectdossier', 'bewaartermijn' => 'P1Y', 'archiefnominatie' => 'vernietigen'];
			}
			// phpcs:enable
		};

		return [
			['categorie' => 'A1', 'omschrijving' => 'Beleid', 'bewaartermijn' => 'P0Y', 'archiefnominatie' => 'bewaren', '@self' => ['id' => 'a']],
			$entity,
			['object' => ['categorie' => 'B1', 'omschrijving' => 'Projectdossier (2020)', 'bewaartermijn' => 'P5Y']],
			['omschrijving' => 'no category'],
			['categorie' => 'B10', 'omschrijving' => 'Uren'],
		];
	}//end rows()

	/**
	 * The categories come from the configured selection list, one per category, sorted.
	 *
	 * @return void
	 */
	public function testTheCategoriesComeFromTheConfiguredSelectionList(): void {
		$categories = $this->service(rows: self::rows())->categories();

		self::assertSame(['register' => 7, 'schema' => 8], $this->listed);
		self::assertSame(['A1', 'B1', 'B10'], array_column($categories, 'category'));
		self::assertSame(
			['category' => 'B1', 'description' => 'Projectdossier', 'period' => 'P1Y', 'action' => 'vernietigen'],
			$categories[1],
			'the first row of a category wins'
		);
	}//end testTheCategoriesComeFromTheConfiguredSelectionList()

	/**
	 * Without a configured selection list, or without OpenRegister, there is nothing to pick.
	 *
	 * @return void
	 */
	public function testNoSelectionListMeansNoCategories(): void {
		$this->archival = ['selectielijstRegister' => null, 'selectielijstSchema' => 8];
		self::assertSame([], $this->service(rows: self::rows())->categories());
		self::assertNull($this->listed, 'the objects are not read');

		$this->archival = ['selectielijstRegister' => 7, 'selectielijstSchema' => 8];
		self::assertSame([], $this->service(rows: self::rows(), installed: false)->categories());
	}//end testNoSelectionListMeansNoCategories()

	/**
	 * Saving stores the pick and writes it into planninq's own schemas only.
	 *
	 * @return void
	 */
	public function testSavingWritesTheCategoryIntoTheLiveSchemas(): void {
		$result = $this->service(rows: self::rows())->save(input: ['project' => 'B1', 'task' => ' B1 ', 'plannedTimeEntry' => 'B10']);

		self::assertSame(['project' => 'B1', 'task' => 'B1', 'plannedTimeEntry' => 'B10'], $result['picks']);
		self::assertSame([], $result['refused']);
		self::assertTrue($result['applied']);
		self::assertSame(['project' => 'B1', 'task' => 'B1', 'plannedTimeEntry' => 'B10'], json_decode($this->config[RetentionCategoryService::CONFIG_KEY], true));

		self::assertSame('B1', $this->schemas[10]->getArchive()['classification']);
		self::assertSame('ander_datumkenmerk', $this->schemas[10]->getArchive()['afleidingswijze'], 'the rest of the block stays');
		self::assertSame('closedAt', $this->schemas[10]->getArchive()['sourceDateProperty']);
		self::assertSame('B1', $this->schemas[11]->getArchive()['classification']);
		self::assertSame('B10', $this->schemas[12]->getArchive()['classification']);
		self::assertSame([10, 11, 12], $this->updates);
		self::assertSame('other-app', $this->schemas[99]->getArchive()['classification'], 'another app\'s project schema is untouched');
	}//end testSavingWritesTheCategoryIntoTheLiveSchemas()

	/**
	 * A category the selection list does not hold is refused and nothing is stored or written.
	 *
	 * @return void
	 */
	public function testAnUnknownCategoryIsRefused(): void {
		$result = $this->service(rows: self::rows())->save(input: ['project' => 'B1', 'task' => 'Z9', 'plannedTimeEntry' => ['B1']]);

		self::assertSame(['task', 'plannedTimeEntry'], $result['refused']);
		self::assertFalse($result['applied']);
		self::assertSame(['project' => '', 'task' => '', 'plannedTimeEntry' => ''], $result['picks']);
		self::assertArrayNotHasKey(RetentionCategoryService::CONFIG_KEY, $this->config);
		self::assertSame([], $this->updates);
		self::assertArrayNotHasKey('classification', $this->schemas[10]->getArchive());
	}//end testAnUnknownCategoryIsRefused()

	/**
	 * A schema left out keeps its pick; an empty value clears it from the schema.
	 *
	 * @return void
	 */
	public function testALeftOutSchemaKeepsItsPickAndAnEmptyOneClearsIt(): void {
		$service = $this->service(rows: self::rows());
		$service->save(input: ['project' => 'B1', 'task' => 'B1', 'plannedTimeEntry' => 'B1']);
		$result = $service->save(input: ['task' => '']);

		self::assertSame(['project' => 'B1', 'task' => '', 'plannedTimeEntry' => 'B1'], $result['picks']);
		self::assertSame('B1', $this->schemas[10]->getArchive()['classification']);
		self::assertArrayNotHasKey('classification', $this->schemas[11]->getArchive());
		self::assertTrue($this->schemas[11]->getArchive()['enabled'], 'clearing the category keeps the block');
	}//end testALeftOutSchemaKeepsItsPickAndAnEmptyOneClearsIt()

	/**
	 * A malformed stored value reads as no pick.
	 *
	 * @return void
	 */
	public function testAMalformedStoredPickReadsAsNone(): void {
		$this->config[RetentionCategoryService::CONFIG_KEY] = '["B1"';
		self::assertSame(['project' => '', 'task' => '', 'plannedTimeEntry' => ''], $this->service()->picks());

		$this->config[RetentionCategoryService::CONFIG_KEY] = '{"project": 5, "task": "B1"}';
		self::assertSame(['project' => '', 'task' => 'B1', 'plannedTimeEntry' => ''], $this->service()->picks());
	}//end testAMalformedStoredPickReadsAsNone()

	/**
	 * The repair step writes the pick back after an import rewrote the schemas.
	 *
	 * @return void
	 */
	public function testTheRepairStepReappliesThePickAfterAnImport(): void {
		$service = $this->service(rows: self::rows());
		$service->save(input: ['project' => 'B1', 'task' => 'B1', 'plannedTimeEntry' => 'B10']);

		// The import rewrites the live schemas from the register file.
		$this->setUp();
		self::assertArrayNotHasKey('classification', $this->schemas[10]->getArchive());

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->never())->method('warning');
		(new ApplyRetentionCategories(retention: $service))->run($output);

		self::assertSame('B1', $this->schemas[10]->getArchive()['classification']);
		self::assertSame('B10', $this->schemas[12]->getArchive()['classification']);
	}//end testTheRepairStepReappliesThePickAfterAnImport()

	/**
	 * Without OpenRegister nothing is written and apply() says so.
	 *
	 * @return void
	 */
	public function testNothingIsWrittenWithoutOpenRegister(): void {
		self::assertFalse($this->service(installed: false)->apply());
		self::assertSame([], $this->updates);
	}//end testNothingIsWrittenWithoutOpenRegister()
}//end class
