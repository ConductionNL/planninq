<?php

/**
 * Tests for WorkingCalendarService (planning-timeline-editing task 1.1).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\WorkingCalendarService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The working weekdays and non-working days.
 */
class WorkingCalendarServiceTest extends TestCase {

	/**
	 * Stored app values.
	 *
	 * @var array<string,string>
	 */
	private array $stored = [];

	/**
	 * Log lines.
	 *
	 * @var array<int,string>
	 */
	private array $warnings = [];

	/**
	 * The service over an in-memory app config.
	 *
	 * @return WorkingCalendarService
	 */
	private function service(): WorkingCalendarService {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(fn ($app, $key, $default = '') => $this->stored[$key] ?? $default);
		$config->method('setValueString')->willReturnCallback(function ($app, $key, $value): bool {
			$this->stored[$key] = $value;
			return true;
		});
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(function ($message): void {
			$this->warnings[] = (string)$message;
		});

		return new WorkingCalendarService($config, $logger);
	}//end service()

	/**
	 * A value that is not a list of dated entries is refused, logged, and leaves the stored value.
	 *
	 * @return void
	 */
	public function testNonWorkingDaysRejectsMalformedValue(): void {
		$service = $this->service();
		$service->save(data: ['non_working_days' => '[{"date":"2026-12-25","name":"Christmas Day"}]']);
		$before = $this->stored['non_working_days'];

		foreach (['{"date":"2026-12-25"}', '[{"date":"2026-02-30","name":"x"}]', '[{"name":"no date"}]', '["2026-12-25"]', 'nonsense', '[{"date":"25-12-2026","name":"x"}]'] as $bad) {
			$service->save(data: ['non_working_days' => $bad]);
			$this->assertSame($before, $this->stored['non_working_days'], $bad);
		}

		$this->assertCount(6, $this->warnings);
		$this->assertStringContainsString('non_working_days', $this->warnings[0]);

		$service->save(data: ['working_weekdays' => '[1,2,8]']);
		$service->save(data: ['working_weekdays' => '[]']);
		$this->assertArrayNotHasKey('working_weekdays', $this->stored);
		$service->save(data: ['working_weekdays' => '[5,4,3,2,1,1]']);
		$this->assertSame('[1,2,3,4,5]', $this->stored['working_weekdays']);
	}//end testNonWorkingDaysRejectsMalformedValue()

	/**
	 * Both keys come back with their defaults, and a saved holiday list is sorted by date.
	 *
	 * @return void
	 */
	public function testWorkingCalendarReadableByMember(): void {
		$service = $this->service();
		$this->assertSame(['working_weekdays' => '[1,2,3,4,5]', 'non_working_days' => '[]'], $service->settings());

		$service->save(data: ['non_working_days' => [['date' => '2026-12-26', 'name' => 'Boxing Day'], ['date' => '2026-12-25', 'name' => 'Christmas Day']]]);
		$this->assertSame(
			'[{"date":"2026-12-25","name":"Christmas Day"},{"date":"2026-12-26","name":"Boxing Day"}]',
			$service->settings()['non_working_days']
		);
	}//end testWorkingCalendarReadableByMember()
}//end class
