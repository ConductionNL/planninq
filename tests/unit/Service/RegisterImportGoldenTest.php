<?php

/**
 * Golden test for RegisterImportService::load() and reload(): per scenario
 * (OpenRegister there or not, the importer's reply or failure, the register
 * cache refresh), the result and every call the service made, in order,
 * compared with a recorded fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite
 * the fixture.
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

use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\SettingsService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\RegisterImportService
 */
final class RegisterImportGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/registerImport.golden.json';

	/**
	 * Every scenario, through load() and reload(), matches the recording.
	 *
	 * @return void
	 */
	public function testImportMatchesTheRecording(): void {
		$scenarios = [
			'no OpenRegister'      => [false, 'reply', ['version' => '1.0.0'], 'mapper'],
			'imported'             => [true, 'reply', ['version' => '1.0.0', 'schemas' => [1]], 'mapper'],
			'imported, no version' => [true, 'reply', ['registers' => [1]], 'mapper'],
			'empty reply'          => [true, 'reply', [], 'mapper'],
			'importer throws'      => [true, 'throw', [], 'mapper'],
			'no importer'          => [true, 'missing', [], 'mapper'],
			'mapper without cache' => [true, 'reply', ['version' => '2'], 'plain mapper'],
			'mapper throws'        => [true, 'reply', ['version' => '2'], 'mapper throws'],
			'no mapper'            => [true, 'reply', ['version' => '2'], 'missing'],
		];
		$actual    = [];
		foreach ($scenarios as $name => $setup) {
			foreach (['load', 'reload'] as $method) {
				$actual[$name][$method] = $this->record(method: $method, setup: $setup);
			}
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testImportMatchesTheRecording()

	/**
	 * Run one import and record every call, in order.
	 *
	 * @param string           $method 'load' or 'reload'.
	 * @param array<int,mixed> $setup  OpenRegister there, importer mode, importer reply, mapper mode.
	 *
	 * @return array<int,mixed>
	 */
	private function record(string $method, array $setup): array {
		[$orThere, $importerMode, $reply, $mapperMode] = $setup;
		$calls    = [];
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('isOpenRegisterAvailable')->willReturn($orThere);

		$services  = [
			'OCA\OpenRegister\Service\ConfigurationService' => $this->importer(mode: $importerMode, reply: $reply, calls: $calls),
			'OCA\OpenRegister\Db\RegisterMapper'            => $this->mapper(mode: $mapperMode, calls: $calls),
		];
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($services, &$calls): object {
				$calls[] = ['get', $id];
				if ($services[$id] === null) {
					throw new \RuntimeException('no ' . $id);
				}

				return $services[$id];
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $message, $context];
				}
			);
		}

		$calls[] = ['result', (new RegisterImportService(settingsService: $settings, container: $container, logger: $logger))->$method()];
		return $calls;
	}//end record()

	/**
	 * The ConfigurationService stand-in.
	 *
	 * @param string              $mode  'reply', 'throw' or 'missing'.
	 * @param array<string,mixed> $reply What importFromApp() returns.
	 * @param array<int,mixed>    $calls Receives the calls.
	 *
	 * @return object|null
	 */
	private function importer(string $mode, array $reply, array &$calls): ?object {
		if ($mode === 'missing') {
			return null;
		}

		return new class($mode, $reply, $calls) {
			private string $mode;

			/** @var array<string,mixed> */
			private array $reply;

			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param string              $mode  'reply' or 'throw'.
			 * @param array<string,mixed> $reply The reply.
			 * @param array<int,mixed>    $calls Receives the calls.
			 */
			public function __construct(string $mode, array $reply, array &$calls) {
				$this->mode  = $mode;
				$this->reply = $reply;
				$this->calls = &$calls;
			}

			/**
			 * @param string              $appId   The app.
			 * @param array<string,mixed> $data    The register definition.
			 * @param string              $version The declared version.
			 * @param bool                $force   Whether to force.
			 *
			 * @return array<string,mixed>
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): array {
				$this->calls[] = ['importFromApp', $appId, $data['x-openregister']['app'] ?? null, count($data), $version, $force];
				if ($this->mode === 'throw') {
					throw new \RuntimeException('import broke');
				}

				return $this->reply;
			}
		};
	}//end importer()

	/**
	 * The RegisterMapper stand-in.
	 *
	 * @param string           $mode  'mapper', 'plain mapper', 'mapper throws' or 'missing'.
	 * @param array<int,mixed> $calls Receives the calls.
	 *
	 * @return object|null
	 */
	private function mapper(string $mode, array &$calls): ?object {
		if ($mode === 'missing') {
			return null;
		}

		if ($mode === 'plain mapper') {
			return new \stdClass();
		}

		return new class($mode === 'mapper throws', $calls) {
			private bool $throws;

			/** @var array<int,mixed> */
			private array $calls;

			/**
			 * @param bool             $throws Whether find() throws.
			 * @param array<int,mixed> $calls  Receives the calls.
			 */
			public function __construct(bool $throws, array &$calls) {
				$this->throws = $throws;
				$this->calls  = &$calls;
			}

			/**
			 * @param mixed ...$args The named arguments.
			 *
			 * @return object
			 */
			public function find(mixed ...$args): object {
				$this->calls[] = ['find', $args];
				if ($this->throws === true) {
					throw new \RuntimeException('no register');
				}

				return new class {
					/**
					 * @return int
					 */
					public function getId(): int {
						return 42;
					}
				};
			}

			/**
			 * @param int $registerId The register.
			 *
			 * @return void
			 */
			public function clearFindCache(int $registerId): void {
				$this->calls[] = ['clearFindCache', $registerId];
			}
		};
	}//end mapper()
}//end class
