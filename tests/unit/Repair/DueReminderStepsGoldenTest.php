<?php

/**
 * Golden test for the due-reminder repair steps and the reminder window patch.
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

use OCA\Planninq\Repair\MigrateUserPreferences;
use OCA\Planninq\Repair\ReconcileDueReminderOverrides;
use OCA\Planninq\Service\DueReminderWindowService;
use OCA\Planninq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Runs MigrateUserPreferences::run(), ReconcileDueReminderOverrides::run()
 * and DueReminderWindowService::patch() on scripted configs, preference
 * services and schema mappers (values present and missing, lookups and
 * writes that fail, schemas without the rule), and compares every call,
 * output line and log line with the fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Repair\MigrateUserPreferences
 * @covers \OCA\Planninq\Repair\ReconcileDueReminderOverrides
 * @covers \OCA\Planninq\Service\DueReminderWindowService
 */
class DueReminderStepsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/due-reminder-steps.golden.json';

	/**
	 * Every call any double received, in order.
	 *
	 * @var array<int,mixed>
	 */
	private array $log = [];

	/**
	 * An output that records its lines.
	 *
	 * @return IOutput
	 */
	private function recordingOutput(): IOutput {
		$output = $this->createMock(originalClassName: IOutput::class);
		foreach (['info', 'warning'] as $level) {
			$output->method($level)->willReturnCallback(
				function (string $message) use ($level): void {
					$this->log[] = ['output.' . $level, $message];
				}
			);
		}

		return $output;
	}//end recordingOutput()

	/**
	 * A logger that records its lines with the context keys and exception classes.
	 *
	 * @return LoggerInterface
	 */
	private function logger(): LoggerInterface {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message, array $context=[]) use ($level): void {
					$this->log[] = ['logger.' . $level, $message, array_map([$this, 'described'], $context)];
				}
			);
		}

		return $logger;
	}//end logger()

	/**
	 * A log context value, with an object named by its class.
	 *
	 * @param mixed $value The value.
	 *
	 * @return mixed
	 */
	public function described(mixed $value): mixed {
		if (is_object($value) === true) {
			return get_class($value);
		}

		return $value;
	}//end described()

	/**
	 * A config whose user values come from the case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return IConfig
	 */
	private function config(array $case): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUsersForUserValue')->willReturnCallback(
			function (string $app, string $key, string $value) use ($case): array {
				$this->log[] = ['getUsersForUserValue', $app, $key, $value];
				return $case['users'][$value] ?? [];
			}
		);
		$config->method('getUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $default) use ($case): string {
				$this->log[] = ['getUserValue', $user, $app, $key, $default];
				if (in_array($user, $case['readFails'] ?? [], true) === true) {
					throw new RuntimeException('read ' . $user);
				}

				return $case['existing'][$user] ?? $default;
			}
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value) use ($case): void {
				$this->log[] = ['setUserValue', $user, $app, $key, $value];
				if (in_array($user, $case['writeFails'] ?? [], true) === true) {
					throw new RuntimeException('write ' . $user);
				}
			}
		);
		return $config;
	}//end config()

	/**
	 * Preference migration cases.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function migrateCases(): array {
		return [
			'nothing stored'     => ['users' => []],
			'two values'         => ['users' => ['true' => ['anna', 'bram'], 'false' => ['cees']]],
			'already present'    => ['users' => ['true' => ['anna'], 'false' => ['bram']], 'existing' => ['anna' => 'false', 'bram' => 'true']],
			'all present'        => ['users' => ['false' => ['anna']], 'existing' => ['anna' => 'false']],
			'read and write fail' => [
				'users'      => ['true' => ['anna', 'bram', 'cees'], 'false' => ['dirk']],
				'readFails'  => ['bram'],
				'writeFails' => ['cees', 'dirk'],
			],
			'only failures'      => ['users' => ['true' => ['anna']], 'writeFails' => ['anna']],
		];
	}//end migrateCases()

	/**
	 * A settings service whose preference service and writes come from the case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return SettingsService
	 */
	private function settings(array $case): SettingsService {
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('isOpenRegisterAvailable')->willReturn($case['available'] ?? true);
		$log      = &$this->log;
		$service  = match ($case['preferences'] ?? 'service') {
			'none' => null,
			'throws' => 'throws',
			default => new class($case, $log) {
				/**
				 * @param array<string,mixed> $case The case.
				 * @param array<int,mixed>    $log  The shared call log.
				 */
				public function __construct(private array $case, private array &$log) {
				}

				/**
				 * The stored override of a user.
				 *
				 * @param string                                                  $userId  The user.
				 * @param \OCA\OpenRegister\Kernel\Contracts\PreferenceAddress $address The preference address.
				 *
				 * @return array<string,mixed>|null
				 */
				public function overrideAt(string $userId, \OCA\OpenRegister\Kernel\Contracts\PreferenceAddress $address): ?array {
					$this->log[] = ['overrideAt', $userId, $address->schemaSlug, $address->notificationKey];
					if (in_array($userId, $this->case['lookupFails'] ?? [], true) === true) {
						throw new RuntimeException('lookup ' . $userId);
					}

					return $this->case['overrides'][$userId] ?? null;
				}
			},
		};
		$settings->method('getNotificationPreferenceService')->willReturnCallback(
			function () use ($service): ?object {
				if ($service === 'throws') {
					throw new RuntimeException('no preference service');
				}

				return $service;
			}
		);
		$settings->method('writeDueReminderOverride')->willReturnCallback(
			function (string $userId, ?array $override) use ($case): bool {
				$this->log[] = ['writeDueReminderOverride', $userId, $override];
				if (in_array($userId, $case['writeFails'] ?? [], true) === true) {
					throw new RuntimeException('write ' . $userId);
				}

				return in_array($userId, $case['writeRefused'] ?? [], true) === false;
			}
		);
		return $settings;
	}//end settings()

	/**
	 * Override reconciliation cases.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function reconcileCases(): array {
		return [
			'no openregister'   => ['available' => false, 'users' => ['false' => ['anna']]],
			'no opt-outs'       => ['users' => []],
			'seeds all'         => ['users' => ['false' => ['anna', 'bram']]],
			'keeps overrides'   => ['users' => ['false' => ['anna', 'bram', 'cees']], 'overrides' => ['bram' => ['enabled' => true]]],
			'refused and fails' => [
				'users'        => ['false' => ['anna', 'bram', 'cees', 'dirk']],
				'writeRefused' => ['bram'],
				'writeFails'   => ['cees'],
				'lookupFails'  => ['dirk'],
			],
			'no pref service'   => ['users' => ['false' => ['anna']], 'preferences' => 'none'],
			'pref service fails' => ['users' => ['false' => ['anna']], 'preferences' => 'throws'],
		];
	}//end reconcileCases()

	/**
	 * A container whose schema mapper comes from the case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return ContainerInterface
	 */
	private function container(array $case): ContainerInterface {
		$log    = &$this->log;
		$mapper = new class($case, $log) {
			/**
			 * @param array<string,mixed> $case The case.
			 * @param array<int,mixed>    $log  The shared call log.
			 */
			public function __construct(private array $case, private array &$log) {
			}

			/**
			 * The schemas with a slug.
			 *
			 * @param string $slug The slug.
			 *
			 * @return mixed
			 */
			public function findBySlug(string $slug): mixed {
				$this->log[] = ['findBySlug', $slug];
				if (($this->case['find'] ?? '') === 'throws') {
					throw new RuntimeException('find');
				}

				return $this->case['schemas'] ?? [];
			}

			/**
			 * Store a schema.
			 *
			 * @param object $schema The schema.
			 *
			 * @return void
			 */
			public function update(object $schema): void {
				$this->log[] = ['update', $schema->getConfiguration()];
				if (($this->case['update'] ?? '') === 'throws') {
					throw new RuntimeException('update');
				}
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			function (string $id) use ($mapper, $case): object {
				$this->log[] = ['container.get', $id];
				if (($case['container'] ?? '') === 'throws') {
					throw new RuntimeException('no mapper');
				}

				return $mapper;
			}
		);
		return $container;
	}//end container()

	/**
	 * A schema with a configuration.
	 *
	 * @param array<string,mixed>|null $configuration The configuration.
	 *
	 * @return object
	 */
	private function schema(?array $configuration): object {
		return new class($configuration) {
			/**
			 * @param array<string,mixed>|null $configuration The configuration.
			 */
			public function __construct(private ?array $configuration) {
			}

			/**
			 * The configuration.
			 *
			 * @return array<string,mixed>|null
			 */
			public function getConfiguration(): ?array {
				return $this->configuration;
			}

			/**
			 * Replace the configuration.
			 *
			 * @param array<string,mixed> $configuration The configuration.
			 *
			 * @return void
			 */
			public function setConfiguration(array $configuration): void {
				$this->configuration = $configuration;
			}
		};
	}//end schema()

	/**
	 * Window patch cases.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function patchCases(): array {
		$filter = ['dueDate' => ['operator' => 'withinNext', 'value' => 'PT24H']];
		$rule   = ['x-openregister-notifications' => ['taskDueSoon' => ['trigger' => ['filter' => $filter]], 'other' => 1]];
		return [
			'not installed'    => ['installed' => false],
			'no mapper'        => ['container' => 'throws'],
			'find throws'      => ['find' => 'throws'],
			'no schemas'       => ['schemas' => []],
			'not an array'     => ['schemas' => 'x'],
			'null config'      => ['schemas' => [$this->schema(configuration: null)]],
			'rule missing'     => ['schemas' => [$this->schema(configuration: ['x-openregister-notifications' => ['taskDueSoon' => ['trigger' => []]]])]],
			'patched'          => ['schemas' => [$this->schema(configuration: $rule), $this->schema(configuration: null)]],
			'update throws'    => ['schemas' => [$this->schema(configuration: $rule)], 'update' => 'throws'],
		];
	}//end patchCases()

	/**
	 * Every step's calls and lines match the fixture.
	 *
	 * @return void
	 */
	public function testStepsMatchTheFixture(): void {
		$answers = [];
		foreach ($this->migrateCases() as $name => $case) {
			$this->log = [];
			(new MigrateUserPreferences(config: $this->config(case: $case), logger: $this->logger()))->run(output: $this->recordingOutput());
			$answers['migrate ' . $name] = $this->log;
		}

		foreach ($this->reconcileCases() as $name => $case) {
			$this->log = [];
			$step      = new ReconcileDueReminderOverrides(
				config: $this->config(case: $case),
				settingsService: $this->settings(case: $case),
				logger: $this->logger()
			);
			$step->run(output: $this->recordingOutput());
			$answers['reconcile ' . $name] = $this->log;
		}

		foreach ($this->patchCases() as $name => $case) {
			foreach ([24, 72] as $hours) {
				$this->log  = [];
				$appManager = $this->createMock(originalClassName: IAppManager::class);
				$appManager->method('isInstalled')->willReturn($case['installed'] ?? true);
				(new DueReminderWindowService(appManager: $appManager, container: $this->container(case: $case), logger: $this->logger()))->patch(hours: $hours);
				$answers["patch $name $hours"] = $this->log;
			}
		}

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $answers);
	}//end testStepsMatchTheFixture()
	/**
	 * The reconcile step names what it reconciles.
	 *
	 * @return void
	 */
	public function testReconcileStepName(): void {
		$step = new ReconcileDueReminderOverrides(
			config: $this->config(case: []),
			settingsService: $this->settings(case: []),
			logger: $this->logger()
		);

		self::assertStringContainsString('reminder', $step->getName());
	}//end testReconcileStepName()

}//end class
