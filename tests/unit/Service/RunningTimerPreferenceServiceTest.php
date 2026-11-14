<?php

/**
 * Unit tests for RunningTimerPreferenceService.
 *
 * @category Test
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

use OCA\Planninq\Service\RunningTimerPreferenceService;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A person's running timer is stored, read back, cleared and validated.
 */
#[CoversClass(RunningTimerPreferenceService::class)]
final class RunningTimerPreferenceServiceTest extends TestCase {

	/**
	 * Stored user values, by user and key.
	 *
	 * @var array<string,string>
	 */
	private array $values = [];

	/**
	 * The service over an in-memory IConfig.
	 *
	 * @return RunningTimerPreferenceService
	 */
	private function service(): RunningTimerPreferenceService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, mixed $default = ''): string {
				if (array_key_exists($user . '/' . $key, $this->values) === true) {
					return $this->values[$user . '/' . $key];
				}

				return (string)$default;
			}
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value): void {
				$this->values[$user . '/' . $key] = $value;
			}
		);
		$config->method('deleteUserValue')->willReturnCallback(
			function (string $user, string $app, string $key): void {
				unset($this->values[$user . '/' . $key]);
			}
		);

		return new RunningTimerPreferenceService(config: $config);
	}//end service()

	/**
	 * No stored value means no timer.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function testNoTimerByDefault(): void {
		self::assertNull(actual: $this->service()->get(userId: 'anna'));
	}//end testNoTimerByDefault()

	/**
	 * A timer is stored per person, its start normalised, and read back from a JSON string too.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function testATimerIsStoredPerPerson(): void {
		$service = $this->service();

		self::assertTrue(condition: $service->save(userId: 'anna', value: ['task' => ' t-1 ', 'startedAt' => '2026-11-14T09:30:00Z']));
		self::assertTrue(condition: $service->save(userId: 'ben', value: '{"task":"t-2","startedAt":"2026-11-14T10:00:00+01:00"}'));

		self::assertSame(expected: ['task' => 't-1', 'startedAt' => '2026-11-14T09:30:00+00:00'], actual: $service->get(userId: 'anna'));
		self::assertSame(expected: ['task' => 't-2', 'startedAt' => '2026-11-14T10:00:00+01:00'], actual: $service->get(userId: 'ben'));
	}//end testATimerIsStoredPerPerson()

	/**
	 * Null and an empty string clear the timer.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function testNullOrEmptyClearsTheTimer(): void {
		$service = $this->service();
		$service->save(userId: 'anna', value: ['task' => 't-1', 'startedAt' => '2026-11-14T09:30:00Z']);

		self::assertTrue(condition: $service->save(userId: 'anna', value: null));
		self::assertNull(actual: $service->get(userId: 'anna'));

		$service->save(userId: 'anna', value: ['task' => 't-1', 'startedAt' => '2026-11-14T09:30:00Z']);
		self::assertTrue(condition: $service->save(userId: 'anna', value: ''));
		self::assertSame(expected: [], actual: $this->values);
	}//end testNullOrEmptyClearsTheTimer()

	/**
	 * A malformed timer is refused and the stored one is kept.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function testAMalformedTimerIsRefused(): void {
		$service = $this->service();
		$service->save(userId: 'anna', value: ['task' => 't-1', 'startedAt' => '2026-11-14T09:30:00Z']);

		$malformed = [
			'not json',
			['task' => 't-2'],
			['startedAt' => '2026-11-14T09:30:00Z'],
			['task' => '', 'startedAt' => '2026-11-14T09:30:00Z'],
			['task' => 'a/b', 'startedAt' => '2026-11-14T09:30:00Z'],
			['task' => str_repeat('a', 65), 'startedAt' => '2026-11-14T09:30:00Z'],
			['task' => 't-2', 'startedAt' => 'yesterday'],
			['task' => 't-2', 'startedAt' => '2026-11-14 09:30'],
			['task' => 't-2', 'startedAt' => '2026-11-14T09:30:00'],
			['task' => 7, 'startedAt' => '2026-11-14T09:30:00Z'],
		];
		foreach ($malformed as $value) {
			self::assertFalse(condition: $service->save(userId: 'anna', value: $value), message: var_export($value, true));
		}

		self::assertSame(expected: ['task' => 't-1', 'startedAt' => '2026-11-14T09:30:00+00:00'], actual: $service->get(userId: 'anna'));
	}//end testAMalformedTimerIsRefused()

	/**
	 * A stored value that no longer validates reads as no timer.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.1
	 */
	public function testAStoredBadValueReadsAsNoTimer(): void {
		$this->values['anna/' . RunningTimerPreferenceService::KEY] = '{"task":"t-1"}';

		self::assertNull(actual: $this->service()->get(userId: 'anna'));
	}//end testAStoredBadValueReadsAsNoTimer()
}//end class
