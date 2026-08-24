<?php

/**
 * Tests for the planix -> planninq per-user preference migration.
 *
 * The behaviour under test is a silent one: without this step a user who had
 * turned due reminders OFF gets them back on, because the preference is stored
 * under the old app id and the read falls through to its `'true'` default.
 * Nothing errors, nothing logs — so the only way this stays fixed is a test
 * that pins it.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Repair
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/app-metadata/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

use OCA\Planninq\Repair\MigrateUserPreferences;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/specs/app-metadata/spec.md
 */
class MigrateUserPreferencesTest extends TestCase {

	private IConfig&MockObject $config;

	private LoggerInterface&MockObject $logger;

	private IOutput&MockObject $output;

	private MigrateUserPreferences $step;

	/**
	 * Set up the mocks shared by every case.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->output = $this->createMock(IOutput::class);
		$this->step = new MigrateUserPreferences($this->config, $this->logger);
	}//end setUp()

	/**
	 * An opted-out user keeps their opt-out across the rename.
	 *
	 * This is the regression the step exists for.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testCopiesAnOptOutToTheNewAppId(): void {
		$this->config->method('getUsersForUserValue')
			->willReturnCallback(
				static function (string $app, string $key, string $value): array {
					return ($value === 'false') ? ['alice'] : [];
				}
			);
		$this->config->method('getUserValue')->willReturn('');

		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', 'planninq', 'notify_due_reminder', 'false');

		$this->step->run($this->output);
	}//end testCopiesAnOptOutToTheNewAppId()

	/**
	 * A preference already set under the new app id is never overwritten.
	 *
	 * Someone who changed the setting after the rename must not have that
	 * choice reverted by a later repair run.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testDoesNotClobberAValueAlreadySetUnderTheNewAppId(): void {
		$this->config->method('getUsersForUserValue')
			->willReturnCallback(
				static function (string $app, string $key, string $value): array {
					return ($value === 'false') ? ['alice'] : [];
				}
			);
		$this->config->method('getUserValue')->willReturn('true');

		$this->config->expects($this->never())->method('setUserValue');

		$this->step->run($this->output);
	}//end testDoesNotClobberAValueAlreadySetUnderTheNewAppId()

	/**
	 * A fresh install with nothing stored under the old id is a no-op.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testIsANoOpWhenNothingWasStoredUnderTheOldAppId(): void {
		$this->config->method('getUsersForUserValue')->willReturn([]);

		$this->config->expects($this->never())->method('setUserValue');
		$this->output->expects($this->once())
			->method('info')
			->with($this->stringContains('nothing to do'));

		$this->step->run($this->output);
	}//end testIsANoOpWhenNothingWasStoredUnderTheOldAppId()

	/**
	 * One unwritable preference is logged, and the rest still migrate.
	 *
	 * A repair step that throws aborts the install, which is a far worse
	 * outcome than one preference falling back to its default.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testLogsAndContinuesWhenOneUserCannotBeMigrated(): void {
		$this->config->method('getUsersForUserValue')
			->willReturnCallback(
				static function (string $app, string $key, string $value): array {
					return ($value === 'false') ? ['alice', 'bob'] : [];
				}
			);
		$this->config->method('getUserValue')->willReturn('');

		$this->config->method('setUserValue')
			->willReturnCallback(
				static function (string $userId): void {
					if ($userId === 'alice') {
						throw new \RuntimeException('storage unavailable');
					}
				}
			);

		$this->logger->expects($this->once())->method('warning');

		$this->step->run($this->output);
	}//end testLogsAndContinuesWhenOneUserCannotBeMigrated()

}//end class
