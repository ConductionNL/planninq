<?php

/**
 * Golden test for RenameTimeEntrySchemaSlug::run(): per scenario (the two slug
 * lookups and the rename, each answering or failing), every output line, log
 * entry and statement in order, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Repair
 *
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://planninq.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

use OCA\Planninq\Repair\RenameTimeEntrySchemaSlug;
use OCP\DB\Exception;
use OCP\DB\IResult;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Repair\RenameTimeEntrySchemaSlug
 */
final class RenameTimeEntrySchemaSlugGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/renameTimeEntrySchemaSlug.golden.json';

	/**
	 * Scenarios: ids for the old slug, ids for the new slug (null = the lookup
	 * fails), and whether the rename fails.
	 */
	private const SCENARIOS = [
		'old unreadable'      => [null, [], false],
		'new unreadable'      => [[495], null, false],
		'both unreadable'     => [null, null, false],
		'neither slug'        => [[], [], false],
		'only new'            => [[], [495], false],
		'both slugs'          => [[495], [512], false],
		'both, old twice'     => [[495, 496], [512], false],
		'old twice'           => [[495, 496], [], false],
		'old alone'           => [[495], [], false],
		'old alone as string' => [['495'], [], false],
		'rename fails'        => [[495], [], true],
	];

	/**
	 * Every scenario matches the recording.
	 *
	 * @return void
	 */
	public function testRunMatchesTheRecording(): void {
		$actual = [];
		foreach (self::SCENARIOS as $name => [$old, $new, $renameFails]) {
			$actual[$name] = $this->record(lookups: [$old, $new], renameFails: $renameFails);
		}

		$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
	}//end testRunMatchesTheRecording()

	/**
	 * Run the step once and record every call it makes, in order.
	 *
	 * @param array<int,array<int,mixed>|null> $lookups     The answers to the two lookups, null to fail.
	 * @param bool                             $renameFails Whether the rename statement fails.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	private function record(array $lookups, bool $renameFails): array {
		$calls = [];
		$db    = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('executeQuery')->willReturnCallback(
			function (string $sql, array $params = []) use (&$calls, &$lookups): IResult {
				$calls[] = ['query', $sql, $params];
				$ids     = array_shift($lookups);
				if ($ids === null) {
					throw new Exception('lookup failed');
				}

				$result = $this->createMock(originalClassName: IResult::class);
				$result->method('fetchAll')->willReturn($ids);
				return $result;
			}
		);
		$db->method('executeStatement')->willReturnCallback(
			static function (string $sql, array $params = []) use (&$calls, $renameFails): int {
				$calls[] = ['statement', $sql, $params];
				if ($renameFails === true) {
					throw new Exception('rename failed');
				}

				return 1;
			}
		);

		$step = new RenameTimeEntrySchemaSlug($db, $this->recorder(className: LoggerInterface::class, prefix: 'log', calls: $calls));
		$step->run($this->recorder(className: IOutput::class, prefix: 'output', calls: $calls));
		return $calls;
	}//end record()

	/**
	 * A mock whose message methods append to the call list.
	 *
	 * @param class-string            $className The interface to mock.
	 * @param string                  $prefix    The label of its calls.
	 * @param array<int,array<mixed>> $calls     Receives the calls.
	 *
	 * @return mixed
	 */
	private function recorder(string $className, string $prefix, array &$calls): mixed {
		$mock = $this->createMock(originalClassName: $className);
		foreach (['info', 'warning', 'error', 'debug'] as $level) {
			if (method_exists($className, $level) === false) {
				continue;
			}

			$mock->method($level)->willReturnCallback(
				static function (...$args) use (&$calls, $prefix, $level): void {
					$calls[] = [$prefix . '.' . $level, ...$args];
				}
			);
		}

		return $mock;
	}//end recorder()
}//end class
