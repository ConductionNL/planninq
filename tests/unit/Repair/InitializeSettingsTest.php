<?php

/**
 * Tests for the InitializeSettings repair step: what it prints and logs when
 * OpenRegister is missing, when the register imports, when the import reports
 * an issue and when it throws.
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

use OCA\Planninq\Repair\InitializeSettings;
use OCA\Planninq\Service\RegisterImportService;
use OCA\Planninq\Service\SettingsService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Pins the progress lines and logs of the register initialization.
 */
class InitializeSettingsTest extends TestCase {

	/**
	 * Every outcome prints and logs the recorded lines.
	 *
	 * @param bool                      $available Whether OpenRegister is available.
	 * @param array<string,mixed>|null  $result    What the import answers; null to throw.
	 * @param array<int,array<int,mixed>> $expected The output and log lines, in order.
	 *
	 * @return void
	 *
	 * @dataProvider outcomes
	 */
	public function testEveryOutcome(bool $available, ?array $result, array $expected): void {
		$lines  = [];
		$output = $this->createMock(originalClassName: IOutput::class);
		foreach (['info', 'warning'] as $level) {
			$output->method($level)->willReturnCallback(
				static function (string $message) use ($level, &$lines): void {
					$lines[] = ['output.'.$level, $message];
				}
			);
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context=[]) use ($level, &$lines): void {
					$lines[] = ['log.'.$level, $message, $context];
				}
			);
		}

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('isOpenRegisterAvailable')->willReturn($available);
		$import = $this->createMock(originalClassName: RegisterImportService::class);
		if ($result === null) {
			$import->method('load')->willThrowException(new RuntimeException('database gone'));
		} else {
			$import->method('load')->willReturn($result);
		}

		(new InitializeSettings(settingsService: $settings, registerImport: $import, logger: $logger))->run(output: $output);

		self::assertSame(expected: $expected, actual: $lines);
	}//end testEveryOutcome()

	/**
	 * The outcomes.
	 *
	 * @return array<string,array{0:bool,1:array<string,mixed>|null,2:array<int,array<int,mixed>>}>
	 */
	public static function outcomes(): array {
		$start = ['output.info', 'Initializing Planninq configuration...'];
		return [
			'no OpenRegister'      => [
				false,
				['success' => true],
				[
					$start,
					['output.warning', 'OpenRegister is not installed or enabled. Skipping auto-configuration.'],
					['log.warning', 'Planninq: OpenRegister not available, skipping register initialization', []],
				],
			],
			'imported'             => [
				true,
				['success' => true, 'version' => '1.4.0'],
				[$start, ['output.info', 'Planninq configuration imported successfully (version: 1.4.0)']],
			],
			'imported, no version' => [
				true,
				['success' => true],
				[$start, ['output.info', 'Planninq configuration imported successfully (version: unknown)']],
			],
			'an issue'             => [
				true,
				['success' => false, 'message' => 'schema missing'],
				[$start, ['output.warning', 'Planninq configuration import issue: schema missing']],
			],
			'an unnamed issue'     => [true, ['success' => false], [$start, ['output.warning', 'Planninq configuration import issue: unknown error']]],
			'the import throws'    => [
				true,
				null,
				[
					$start,
					['output.warning', 'Could not auto-configure Planninq: database gone'],
					['log.error', 'Planninq initialization failed', ['exception' => 'database gone']],
				],
			],
		];
	}//end outcomes()
	public function testGetNameDescribesTheStep(): void {
		$step = new InitializeSettings(
			settingsService: $this->createMock(originalClassName: SettingsService::class),
			registerImport: $this->createMock(originalClassName: RegisterImportService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		self::assertStringContainsString('register and schemas', $step->getName());
	}//end testGetNameDescribesTheStep()

}//end class
