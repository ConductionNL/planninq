<?php

/**
 * Tests for ProjectPolicySchemaService: the reviewers of project requests are written into the live project schema.
 *
 * The live schema starts as the register file's own `project` fragment (lifecycle
 * in the configuration block, authorization as declared), so the patch is
 * tested against what the import really leaves behind; a second `project`
 * schema of another app's register must stay untouched.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Repair\ApplyProjectPolicy;
use OCA\Planninq\Service\ProjectPolicySchemaService;
use OCA\Planninq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ProjectPolicySchemaServiceTest extends TestCase {

	/**
	 * The live schemas by id: planninq's project (10) and another app's project (99).
	 *
	 * @var array<int,object>
	 */
	private array $schemas = [];

	/**
	 * Schema ids written back.
	 *
	 * @var array<int,int>
	 */
	public array $updates = [];

	protected function setUp(): void {
		parent::setUp();
		$register = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
		$project  = $register['components']['schemas']['project'];
		$this->schemas = [
			10 => $this->schema(id: 10, configuration: ['x-openregister-lifecycle' => $project['x-openregister-lifecycle']], authorization: $project['authorization']),
			99 => $this->schema(id: 99, configuration: [], authorization: ['read' => ['admin']]),
		];
	}//end setUp()

	private function schema(int $id, array $configuration, array $authorization): object {
		return new class($id, $configuration, $authorization) {
			// phpcs:disable
			public function __construct(public int $id, public array $configuration, public array $authorization) {
			}
			public function getId(): int {
				return $this->id;
			}
			public function getConfiguration(): array {
				return $this->configuration;
			}
			public function setConfiguration(array $configuration): void {
				$this->configuration = $configuration;
			}
			public function getAuthorization(): array {
				return $this->authorization;
			}
			public function setAuthorization(array $authorization): void {
				$this->authorization = $authorization;
			}
			// phpcs:enable
		};
	}//end schema()

	private function service(): ProjectPolicySchemaService {
		$test     = $this;
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
			public function __construct(private ProjectPolicySchemaServiceTest $test) {
			}
			public function findBySlug(string $slug): array {
				return $slug === 'project' ? [$this->test->live(99), $this->test->live(10)] : [];
			}
			public function update($schema) {
				$this->test->updates[] = $schema->getId();
				return $schema;
			}
			// phpcs:enable
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registerMapper,
				'OCA\\OpenRegister\\Db\\SchemaMapper' => $schemaMapper,
				default => throw new \RuntimeException('unexpected service: ' . $id),
			}
		);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new ProjectPolicySchemaService(appManager: $appManager, container: $container, logger: $this->createMock(originalClassName: LoggerInterface::class));
	}//end service()

	/**
	 * The live schema with the given id.
	 *
	 * @param int $id The schema id.
	 *
	 * @return object
	 */
	public function live(int $id): object {
		return $this->schemas[$id];
	}//end live()

	/**
	 * Saving the policy makes the chosen groups reviewers: they may approve and reject, and read and update requests.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function testSavingWritesTheReviewerGroupsIntoTheLiveSchema(): void {
		self::assertTrue($this->service()->apply(groups: ['projectleiders', 'staf']));

		$live = $this->schemas[10];
		$transitions = $live->getConfiguration()['x-openregister-lifecycle']['transitions'];
		self::assertSame(['admin', 'projectleiders', 'staf'], $transitions['approve']['authorization']);
		self::assertSame(['admin', 'projectleiders', 'staf'], $transitions['reject']['authorization']);
		self::assertArrayNotHasKey('authorization', $transitions['restore'], 'archive and restore stay with the update rule');

		foreach (['read', 'update'] as $operation) {
			self::assertContains(['group' => 'projectleiders', 'match' => ['status' => 'requested']], $live->getAuthorization()[$operation]);
			self::assertContains(['group' => 'staf', 'match' => ['status' => 'requested']], $live->getAuthorization()[$operation]);
		}

		self::assertSame([10], $this->updates, 'only planninq\'s project schema is written');
		self::assertSame(['read' => ['admin']], $this->schemas[99]->getAuthorization());
	}//end testSavingWritesTheReviewerGroupsIntoTheLiveSchema()

	/**
	 * A second save replaces the reviewer rules instead of adding to them, and an empty list leaves admins only.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function testASecondSaveReplacesTheReviewers(): void {
		$service = $this->service();
		$service->apply(groups: ['projectleiders', 'staf']);
		$service->apply(groups: ['staf']);

		$live = $this->schemas[10];
		self::assertSame(['admin', 'staf'], $live->getConfiguration()['x-openregister-lifecycle']['transitions']['approve']['authorization']);
		$reviewerRules = array_values(array_filter($live->getAuthorization()['read'], static fn ($rule): bool => is_array($rule) && ($rule['match'] ?? null) === ['status' => 'requested']));
		self::assertSame([['group' => 'staf', 'match' => ['status' => 'requested']]], $reviewerRules);

		$service->apply(groups: []);
		self::assertSame(['admin'], $live->getConfiguration()['x-openregister-lifecycle']['transitions']['reject']['authorization']);
		self::assertCount(3, $live->getAuthorization()['read'], 'the three rules of the register file, nothing more');
	}//end testASecondSaveReplacesTheReviewers()

	/**
	 * Scenario risk "A register import resets the live patch": the repair step puts the reviewers back.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function testTheRepairStepReappliesThePatchAfterAnImport(): void {
		$service = $this->service();
		$service->apply(groups: ['projectleiders']);

		// The import rewrites the live schema from the register file.
		$this->setUp();

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('reviewerGroups')->willReturn(['projectleiders']);
		(new ApplyProjectPolicy(settings: $settings, policySchema: $service))->run($this->createMock(originalClassName: IOutput::class));

		self::assertSame(['admin', 'projectleiders'], $this->schemas[10]->getConfiguration()['x-openregister-lifecycle']['transitions']['approve']['authorization']);
	}//end testTheRepairStepReappliesThePatchAfterAnImport()

	/**
	 * Without the lifecycle block on the live schema nothing is written.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.2
	 */
	public function testNothingIsWrittenWithoutTheLifecycleBlock(): void {
		$this->schemas[10] = $this->schema(id: 10, configuration: [], authorization: []);
		self::assertFalse($this->service()->apply(groups: ['staf']));
		self::assertSame([], $this->updates);
	}//end testNothingIsWrittenWithoutTheLifecycleBlock()
}//end class
