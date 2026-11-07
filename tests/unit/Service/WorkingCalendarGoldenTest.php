<?php

/**
 * Golden test for the working calendar checks.
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

use OCA\Planninq\Service\WorkingCalendarService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Records the normalised weekdays and non-working days for a fixed set of
 * submitted values, and what save() writes and logs for them.
 *
 * @covers \OCA\Planninq\Service\WorkingCalendarService
 */
class WorkingCalendarGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/working-calendar.golden.json';

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testTheCalendarChecksMatchTheGoldenRecording(): void {
		$weekdays = ['[1,2,3,4,5]', '[5,4,4,1]', [7, 1], '[]', '[0,1]', '[1,8]', '[1,"2"]', '[1.0]', '{"a":1}', [1 => 2], 'nope', 3, null, '[7]', '[1,2,3,4,5,6,7,1]'];
		$days     = [
			'[]',
			'[{"date":"2026-12-25","name":" Christmas "},{"date":"2026-01-01"}]',
			[['date' => '2026-05-05', 'name' => 'Liberation Day'], ['date' => '2026-05-05', 'name' => 'twice']],
			'[{"date":"2026-02-30"}]',
			'[{"date":"2026-2-3"}]',
			'[{"date":"2026-02-03x"}]',
			'[{"date":20260203}]',
			'[{"name":"no date"}]',
			'["2026-02-03"]',
			'[{"date":"2026-02-03","name":7}]',
			[['date' => '2026-02-03', 'name' => str_repeat('é', 100)]],
			[['date' => '2026-02-03', 'name' => str_repeat('é', 101)]],
			'{"x":{"date":"2026-02-03"}}',
			'not json',
			null,
			[['date' => '2024-02-29', 'name' => 'leap'], ['date' => '2025-02-29']],
		];

		$recorded = ['weekdays' => [], 'days' => [], 'save' => []];
		$service  = $this->service(log: $log, writes: $writes);
		foreach ($weekdays as $n => $raw) {
			$recorded['weekdays'][$n] = $service->normaliseWeekdays(raw: $raw);
		}

		foreach ($days as $n => $raw) {
			$recorded['days'][$n] = $service->normaliseDays(raw: $raw);
		}

		$saves = [
			[],
			['working_weekdays' => '[2,1]'],
			['non_working_days' => '[{"date":"2026-12-25"}]', 'other' => 'x'],
			['working_weekdays' => '[9]', 'non_working_days' => '[{"date":"2026-12-25"}]'],
			['working_weekdays' => '[1]', 'non_working_days' => 'bad'],
			['working_weekdays' => null, 'non_working_days' => null],
		];
		foreach ($saves as $n => $data) {
			$service = $this->service(log: $log, writes: $writes);
			$service->save(data: $data);
			$recorded['save'][$n] = ['writes' => $writes, 'log' => $log];
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: json_decode($json, true));
	}//end testTheCalendarChecksMatchTheGoldenRecording()

	/**
	 * The service over a recording config and logger.
	 *
	 * @param array<int,mixed>|null $log    Filled with the warnings.
	 * @param array<int,mixed>|null $writes Filled with the config writes.
	 *
	 * @return WorkingCalendarService
	 */
	private function service(?array &$log, ?array &$writes): WorkingCalendarService {
		$log    = [];
		$writes = [];
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('setValueString')->willReturnCallback(static function (string $app, string $key, string $value) use (&$writes): bool {
			$writes[] = [$app, $key, $value];
			return true;
		});
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(static function (string $message, array $context=[]) use (&$log): void {
			$log[] = [$message, $context];
		});

		return new WorkingCalendarService(appConfig: $config, logger: $logger);
	}//end service()
}//end class
