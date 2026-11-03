<?php

/**
 * Golden test for the flow history window.
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

use DateTimeImmutable;
use OCA\Planninq\Service\FlowHistoryService;
use OCA\Planninq\Service\FlowReplay;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Records window() for a fixed set of requested bounds at a fixed now.
 *
 * @covers \OCA\Planninq\Service\FlowHistoryService
 * @uses   \OCA\Planninq\Service\FlowReplay
 */
class FlowWindowGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/flow-window.golden.json';

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testTheWindowMatchesTheGoldenRecording(): void {
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-11-03T15:30:00Z'));
		$service = new FlowHistoryService(
			container: $this->createMock(originalClassName: ContainerInterface::class),
			cacheFactory: $this->createMock(originalClassName: ICacheFactory::class),
			timeFactory: $time,
			replay: new FlowReplay(),
			logger: new NullLogger()
		);

		$bounds = [
			[null, null], ['', ''], ['2026-10-01', null], [null, '2026-10-31'], ['2026-10-01', '2026-10-31'],
			['2026-10-31', '2026-10-01'], ['2026-10-01', '2026-10-01'], ['2026-05-08', '2026-11-03'], ['2026-05-07', '2026-11-03'],
			['2026-02-30', null], [null, '2026-13-01'], ['2026-1-01', null], [null, '2026-10-01T00:00'], ['x', 'y'],
			['2024-02-29', '2024-03-01'], [null, '2027-01-15'], ['2026-12-01', null], ['', '2026-06-01'],
		];

		$recorded = [];
		foreach ($bounds as [$from, $to]) {
			$window = $service->window(from: $from, to: $to);
			$recorded[var_export($from, true).' '.var_export($to, true)] = $window === null ? null : [$window[0]->format(DATE_ATOM), $window[1]->format(DATE_ATOM)];
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $recorded);
	}//end testTheWindowMatchesTheGoldenRecording()
}//end class
