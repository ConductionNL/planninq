<?php

/**
 * Tests for the planix -> planninq app-config migration.
 *
 * Nextcloud namespaces IAppConfig by app id, so renaming <id> makes every
 * stored value unreachable rather than moving it. This step copies them, and
 * these tests pin the three properties that make it safe to run on a live
 * instance: it is exhaustive, it never clobbers, and it never aborts an
 * install.
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

use OCA\Planninq\Repair\MigrateAppConfigKeys;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/specs/app-metadata/spec.md
 */
class MigrateAppConfigKeysTest extends TestCase {

	private IAppConfig&MockObject $appConfig;

	private LoggerInterface&MockObject $logger;

	private IOutput&MockObject $output;

	private MigrateAppConfigKeys $step;

	/**
	 * Set up the mocks shared by every case.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->output = $this->createMock(IOutput::class);
		$this->step = new MigrateAppConfigKeys($this->appConfig, $this->logger);
	}//end setUp()

	/**
	 * A stored value is copied to the new app id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testCopiesAStoredValueToTheNewAppId(): void {
		$this->appConfig->method('getKeys')->willReturn(['solr_url']);
		$this->appConfig->method('getValueString')
			->willReturnCallback(
				static function (string $app): string {
					return ($app === 'planix') ? 'https://solr.example' : '';
				}
			);

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('planninq', 'solr_url', 'https://solr.example');

		$this->step->run($this->output);
	}//end testCopiesAStoredValueToTheNewAppId()

	/**
	 * Reserved keys are never copied.
	 *
	 * `enabled`, `installed_version` and `types` are Nextcloud's own rows for
	 * the app; copying them across would describe the new app with the old
	 * app's install state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testSkipsReservedKeys(): void {
		$this->appConfig->method('getKeys')
			->willReturn(['enabled', 'installed_version', 'types']);
		$this->appConfig->method('getValueString')->willReturn('something');

		$this->appConfig->expects($this->never())->method('setValueString');

		$this->step->run($this->output);
	}//end testSkipsReservedKeys()

	/**
	 * A value already present under the new app id is never overwritten.
	 *
	 * An admin who changed a setting after the rename must keep their change
	 * when the step runs again.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testDoesNotClobberAnExistingNewValue(): void {
		$this->appConfig->method('getKeys')->willReturn(['solr_url']);
		$this->appConfig->method('getValueString')->willReturn('already-set');

		$this->appConfig->expects($this->never())->method('setValueString');

		$this->step->run($this->output);
	}//end testDoesNotClobberAnExistingNewValue()

	/**
	 * A fresh instance that never had planix installed is a no-op.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testIsANoOpOnAFreshInstance(): void {
		$this->appConfig->method('getKeys')->willReturn([]);

		$this->appConfig->expects($this->never())->method('setValueString');
		$this->output->expects($this->once())
			->method('info')
			->with($this->stringContains('nothing to do'));

		$this->step->run($this->output);
	}//end testIsANoOpOnAFreshInstance()

	/**
	 * An unreadable config store is logged, not thrown.
	 *
	 * A repair step that throws aborts the install. Falling back to defaults
	 * and letting the admin re-enter a setting is the better failure.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-metadata/spec.md
	 */
	public function testLogsAndContinuesWhenTheKeysCannotBeRead(): void {
		$this->appConfig->method('getKeys')
			->willThrowException(new \RuntimeException('config unavailable'));

		$this->logger->expects($this->once())->method('warning');
		$this->appConfig->expects($this->never())->method('setValueString');

		$this->step->run($this->output);
	}//end testLogsAndContinuesWhenTheKeysCannotBeRead()

}//end class
