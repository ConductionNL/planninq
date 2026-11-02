<?php

/**
 * Golden test for MigrateAppConfigKeys::run(): per scenario (old keys, values
 * under both app ids, a write that fails), every config read and write, log
 * call and output line in order, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Repair
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

namespace OCA\Planninq\Tests\Unit\Repair;

use OCA\Planninq\Repair\MigrateAppConfigKeys;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Repair\MigrateAppConfigKeys
 */
final class MigrateAppConfigKeysGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/migrateAppConfigKeys.golden.json';

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testRunMatchesTheRecording(): void {
		$mixed = [
			'planix'   => ['a' => '1', 'b' => '', 'c' => '3', 'enabled' => 'yes', 'types' => 'x', 'installed_version' => '0.1', 'd' => '4', 'e' => '5'],
			'planninq' => ['c' => 'kept', 'd' => ''],
		];
		$scenarios = [
			'no old keys'      => [[], [], null],
			'keys unreadable'  => [null, [], null],
			'mixed'            => [['a', 'b', 'c', 'enabled', 'types', 'installed_version', 'd', 'e'], $mixed, null],
			'mixed, e fails'   => [['a', 'b', 'c', 'enabled', 'types', 'installed_version', 'd', 'e'], $mixed, 'e'],
			'only reserved'    => [['enabled', 'types'], $mixed, null],
			'unknown key'      => [['zzz'], $mixed, null],
			'all fail'         => [['a', 'e'], $mixed, '*'],
		];
		$actual    = [];
		foreach ($scenarios as $name => $setup) {
			$actual[$name] = $this->record(setup: $setup);
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testRunMatchesTheRecording()

	/**
	 * Run the step once and record every call, in order.
	 *
	 * @param array<int,mixed> $setup The old keys (null: unreadable), the stored values, the key whose write fails.
	 *
	 * @return array<int,mixed>
	 */
	private function record(array $setup): array {
		[$keys, $values, $failing] = $setup;
		$calls  = [];
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getKeys')->willReturnCallback(
			static function (string $app) use (&$calls, $keys): array {
				$calls[] = ['getKeys', $app];
				if ($keys === null) {
					throw new \RuntimeException('no keys');
				}

				return $keys;
			}
		);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$calls, $values): string {
				$calls[] = ['get', $app, $key];
				return ($values[$app][$key] ?? $default);
			}
		);
		$config->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$calls, $failing): bool {
				$calls[] = ['set', $app, $key, $value];
				if ($failing === '*' || $failing === $key) {
					throw new \RuntimeException('write failed');
				}

				return true;
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			static function (string $message, array $context = []) use (&$calls): void {
				$calls[] = ['log.warning', $message, $context];
			}
		);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->method('info')->willReturnCallback(
			static function (string $message) use (&$calls): void {
				$calls[] = ['output.info', $message];
			}
		);

		(new MigrateAppConfigKeys(appConfig: $config, logger: $logger))->run($output);
		return $calls;
	}//end record()
}//end class
