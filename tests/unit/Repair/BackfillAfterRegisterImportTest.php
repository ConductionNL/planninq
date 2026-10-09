<?php

/**
 * The members back-fill on an upgraded instance (live pass P1, 2 Oct).
 *
 * During the 0.2.22 to 0.2.23 `occ upgrade` the step warned "Schema slug
 * "projectLogEntry" is not carried by register "planninq" (id 21), which
 * carries 8 schema(s)" and wrote nothing, although InitializeSettings had just
 * imported the 21-schema register in the same request. OpenRegister's
 * RegisterMapper keeps a request-scoped find() cache keyed by slug and flags;
 * the import updated the register through another entity, so the back-fill's
 * system read (`_rbac: false, _multitenancy: false`) got the pre-import copy.
 *
 * This runs the real InitializeSettings, RegisterImportService,
 * BackfillProjectMembers and ProjectMembershipService, in the order info.xml
 * lists them, over a fake OpenRegister whose RegisterMapper caches the way the
 * real one does.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Repair
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

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Repair\BackfillProjectMembers;
use OCA\Planninq\Repair\InitializeSettings;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Repair\BackfillProjectMembers
 * @covers \OCA\Planninq\Service\RegisterImportService
 * @uses \OCA\Planninq\Repair\InitializeSettings
 * @uses \OCA\Planninq\Service\ProjectMembershipService
 */
class BackfillAfterRegisterImportTest extends TestCase {

	/**
	 * The schemas the register carried before the upgrade (0.2.22: eight).
	 */
	private const OLD_SCHEMAS = ['project', 'task', 'column', 'projectPhase', 'plannedTimeEntry', 'label', 'comment', 'milestone'];

	/**
	 * The fake OpenRegister register store with its request cache.
	 *
	 * @var object
	 */
	private object $registers;

	/**
	 * The fake OpenRegister object store.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objects;

	/**
	 * Warnings the steps printed.
	 *
	 * @var array<int,string>
	 */
	private array $warnings = [];

	/**
	 * An instance upgraded from 0.2.22: legacy rows without members lists.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->registers = new class(self::OLD_SCHEMAS) {
			// phpcs:disable
			/** @var array<int,string> What the database holds. */
			public array $stored;
			/** @var array<string,object> OpenRegister's request-scoped find() cache. */
			private array $findCache = [];
			public function __construct(array $schemas) {
				$this->stored = $schemas;
			}
			public function find(string|int $id, bool $_rbac = true, bool $_multitenancy = true): object {
				$key = strtolower((string)$id) . ':' . (int)$_rbac . ':' . (int)$_multitenancy;
				if (isset($this->findCache[$key]) === false) {
					$this->findCache[$key] = new class(21, 'planninq', $this->stored) {
						public function __construct(private int $id, private string $slug, private array $schemas) {
						}
						public function getId(): int {
							return $this->id;
						}
						public function getSlug(): string {
							return $this->slug;
						}
						public function getSchemas(): array {
							return $this->schemas;
						}
					};
				}
				return $this->findCache[$key];
			}
			public function clearFindCache(int $registerId): void {
				foreach ($this->findCache as $key => $register) {
					if ($register->getId() === $registerId) {
						unset($this->findCache[$key]);
					}
				}
			}
			// phpcs:enable
		};

		$registers = $this->registers;
		$this->objects = new class($registers) extends InMemoryObjectService {
			// phpcs:disable
			public function __construct(private object $registers) {
			}
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): array|int {
				$register = $this->registers->find(id: $registerSlug, _rbac: $_rbac, _multitenancy: $_multitenancy);
				if (in_array($schemaSlug, $register->getSchemas(), true) === false) {
					throw new \RuntimeException(sprintf('Schema slug "%s" is not carried by register "%s" (id %d), which carries %d schema(s).', $schemaSlug, $registerSlug, $register->getId(), count($register->getSchemas())));
				}
				return parent::searchObjectsBySlug($registerSlug, $schemaSlug, $filters, $_rbac, $_multitenancy);
			}
			// phpcs:enable
		};

		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob'], 'owner' => 'alice']);
		$this->objects->seed('task', 'ta', ['title' => 'TA', 'status' => 'open', 'project' => 'proj-a']);
		$this->objects->seed('column', 'ca', ['title' => 'Todo', 'project' => 'proj-a', 'order' => 0]);

	}//end setUp()

	/**
	 * The container planninq resolves OpenRegister through.
	 *
	 * @return ContainerInterface
	 */
	private function container(): ContainerInterface {
		$registers = $this->registers;
		// The import: OpenRegister reads the register with the user's flags and
		// saves the new schema list, which leaves the system-flag cache entry
		// read earlier in the request (MigrateRegisterSlug) untouched.
		$configuration = new class($registers) {
			// phpcs:disable
			public function __construct(private object $registers) {
			}
			public function importFromApp(string $appId, array $data, string $version, bool $force = false): array {
				$this->registers->find(id: 'planninq');
				$this->registers->stored = array_keys($data['components']['schemas'] ?? []);
				return ['version' => $version];
			}
			// phpcs:enable
		};

		$objects = $this->objects;
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => match ($id) {
				'OCA\\OpenRegister\\Db\\RegisterMapper' => $registers,
				'OCA\\OpenRegister\\Service\\ObjectService' => $objects,
				'OCA\\OpenRegister\\Service\\ConfigurationService' => $configuration,
				default => throw new \RuntimeException('unexpected service: ' . $id),
			}
		);

		return $container;
	}//end container()

	/**
	 * Run the post-migration steps from InitializeSettings on, in info.xml order.
	 *
	 * @return void
	 */
	private function upgrade(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->method('warning')->willReturnCallback(function (string $message): void {
			$this->warnings[] = $message;
		});

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('isOpenRegisterAvailable')->willReturn(true);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$container = $this->container();

		// What MigrateRegisterSlug does before the import: a system read of the register.
		$this->registers->find(id: 'planninq', _rbac: false, _multitenancy: false);

		$steps = [
			InitializeSettings::class => new InitializeSettings(
				settingsService: $settings,
				registerImport: new RegisterImportService(settingsService: $settings, container: $container, logger: $logger),
				logger: $logger
			),
			BackfillProjectMembers::class => new BackfillProjectMembers(
				membership: new ProjectMembershipService(container: $container, appManager: $this->appManager(), logger: $logger),
				logger: $logger
			),
		];

		$order = $this->postMigrationOrder();
		uksort($steps, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));
		foreach ($steps as $step) {
			$step->run($output);
		}
	}//end upgrade()

	/**
	 * An app manager that has OpenRegister installed.
	 *
	 * @return IAppManager
	 */
	private function appManager(): IAppManager {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		return $appManager;
	}//end appManager()

	/**
	 * The repair steps under one hook of appinfo/info.xml, in order.
	 *
	 * @param string $hook `post-migration` or `install`.
	 *
	 * @return array<int,string>
	 */
	private function postMigrationOrder(string $hook = 'post-migration'): array {
		// Nextcloud disables libxml's external entity loader, which makes simplexml_load_file() fail.
		$xml = simplexml_load_string((string)file_get_contents(__DIR__ . '/../../../appinfo/info.xml'));
		$steps = [];
		foreach ($xml->{'repair-steps'}->{$hook}->step as $step) {
			$steps[] = (string)$step;
		}
		return $steps;
	}//end postMigrationOrder()

	/**
	 * The back-fill runs after the import that adds the schemas it reads, in both hooks.
	 *
	 * @return void
	 */
	public function testTheBackfillIsListedAfterTheRegisterImport(): void {
		foreach (['post-migration', 'install'] as $hook) {
			$order = $this->postMigrationOrder(hook: $hook);
			self::assertLessThan(
				array_search(BackfillProjectMembers::class, $order, true),
				array_search(InitializeSettings::class, $order, true),
				$hook
			);
		}
	}//end testTheBackfillIsListedAfterTheRegisterImport()

	/**
	 * On an upgraded instance the back-fill reads the freshly imported register and fills the lists.
	 *
	 * @return void
	 */
	public function testTheBackfillReadsTheRegisterTheImportJustWrote(): void {
		$this->upgrade();

		self::assertSame([], $this->warnings);
		self::assertSame(['alice', 'bob'], ($this->objects->rows['task']['ta']['members'] ?? null));
		self::assertSame(['alice', 'bob'], ($this->objects->rows['column']['ca']['members'] ?? null));
	}//end testTheBackfillReadsTheRegisterTheImportJustWrote()
}//end class
