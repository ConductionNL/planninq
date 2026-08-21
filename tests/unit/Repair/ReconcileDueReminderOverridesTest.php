<?php

/**
 * Unit tests for ReconcileDueReminderOverrides repair step.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Repair
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Repair;

use OCA\Planix\Repair\ReconcileDueReminderOverrides;
use OCA\Planix\Service\SettingsService;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the reconciliation repair step seeds overrides idempotently.
 */
class ReconcileDueReminderOverridesTest extends TestCase {

	/**
	 * Mock IConfig.
	 *
	 * @var IConfig&MockObject
	 */
	private IConfig&MockObject $config;

	/**
	 * Mock SettingsService.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settingsService;

	/**
	 * Mock LoggerInterface.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

	}//end setUp()

	/**
	 * Build the repair step under test.
	 *
	 * @return ReconcileDueReminderOverrides
	 */
	private function step(): ReconcileDueReminderOverrides {
		return new ReconcileDueReminderOverrides(
			config: $this->config,
			settingsService: $this->settingsService,
			logger: $this->logger,
		);

	}//end step()

	/**
	 * When OpenRegister is unavailable the step is a no-op (warns, no writes).
	 *
	 * @return void
	 */
	public function testSkipsWhenOpenRegisterUnavailable(): void {
		$this->settingsService->method('isOpenRegisterAvailable')->willReturn(false);
		$this->settingsService->expects($this->never())->method('writeDueReminderOverride');

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		$this->step()->run($output);

	}//end testSkipsWhenOpenRegisterUnavailable()

	/**
	 * Seeds {"enabled": false} for every opted-out user without an override.
	 *
	 * @return void
	 */
	public function testSeedsOverridesForOptedOutUsers(): void {
		$this->settingsService->method('isOpenRegisterAvailable')->willReturn(true);

		$this->config->method('getUsersForUserValue')
			->with('planix', 'notify_due_reminder', 'false')
			->willReturn(['alice', 'bob']);

		// No existing overrides → both get seeded.
		$prefService = new TestPrefSpy();
		$this->settingsService->method('getNotificationPreferenceService')->willReturn($prefService);

		$seeded = [];
		$this->settingsService->method('writeDueReminderOverride')
			->willReturnCallback(
				function (string $userId, ?array $override) use (&$seeded): bool {
					$seeded[$userId] = $override;
					return true;
				}
			);

		$output = $this->createMock(originalClassName: IOutput::class);
		$this->step()->run($output);

		self::assertCount(expectedCount: 2, haystack: $seeded);
		self::assertSame(expected: ['enabled' => false], actual: $seeded['alice']);
		self::assertSame(expected: ['enabled' => false], actual: $seeded['bob']);

	}//end testSeedsOverridesForOptedOutUsers()

	/**
	 * Idempotent: a user who already has an override is NOT clobbered.
	 *
	 * @return void
	 */
	public function testDoesNotClobberExistingOverride(): void {
		$this->settingsService->method('isOpenRegisterAvailable')->willReturn(true);

		$this->config->method('getUsersForUserValue')->willReturn(['carol']);

		// carol already has an explicit override.
		$prefService = new TestPrefSpy(['carol' => ['enabled' => true]]);
		$this->settingsService->method('getNotificationPreferenceService')->willReturn($prefService);

		$this->settingsService->expects($this->never())->method('writeDueReminderOverride');

		$output = $this->createMock(originalClassName: IOutput::class);
		$this->step()->run($output);

	}//end testDoesNotClobberExistingOverride()

	/**
	 * No opted-out users → nothing seeded, info logged.
	 *
	 * @return void
	 */
	public function testNoOptOutsIsNoop(): void {
		$this->settingsService->method('isOpenRegisterAvailable')->willReturn(true);
		$this->config->method('getUsersForUserValue')->willReturn([]);
		$this->settingsService->expects($this->never())->method('writeDueReminderOverride');

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info');

		$this->step()->run($output);

	}//end testNoOptOutsIsNoop()
}//end class

/**
 * Preference-service test double with seeded existing overrides.
 */
class TestPrefSpy {

	/**
	 * Pre-seeded overrides keyed by user UID.
	 *
	 * @var array<string,array<string,mixed>|null>
	 */
	private array $overrides;

	/**
	 * Constructor.
	 *
	 * @param array<string,array<string,mixed>|null> $overrides Seed overrides.
	 */
	public function __construct(array $overrides = []) {
		$this->overrides = $overrides;

	}//end __construct()

	/**
	 * Return the seeded override for a user, or null.
	 *
	 * @param string $userId The user UID.
	 * @param string $schemaSlug The schema slug.
	 * @param string $notificationKey The notification key.
	 *
	 * @return array<string,mixed>|null
	 */
	public function getOverride(string $userId, string $schemaSlug, string $notificationKey): ?array {
		return ($this->overrides[$userId] ?? null);
	}//end getOverride()
}//end class
