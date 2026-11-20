<?php

/**
 * Golden test for the timetable period grid check.
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

use OCA\Planninq\Service\TimetableGridService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Records normaliseGrid() for a fixed set of submitted grids.
 *
 * @covers \OCA\Planninq\Service\TimetableGridService
 */
class TimetableGridGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/timetable-grid.golden.json';

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testTheGridCheckMatchesTheGoldenRecording(): void {
		$p    = static fn (mixed $start, mixed $end): array => ['start' => $start, 'end' => $end];
		$two  = [$p('08:30', '09:20'), $p('09:20', '10:10')];
		$grid = static fn (mixed $days, mixed $periods): string => (string)json_encode(['days' => $days, 'periods' => $periods]);
		$raws = [
			'default' => TimetableGridService::DEFAULT_GRID,
			'plain' => $grid(['mon', 'tue'], $two),
			'days-reordered' => $grid(['fri', 'mon', 'wed'], $two),
			'days-keyed' => $grid(['a' => 'sun', 'b' => 'sat'], $two),
			'days-empty' => $grid([], $two),
			'days-string' => $grid('mon', $two),
			'days-unknown' => $grid(['mon', 'Mon'], $two),
			'days-twice' => $grid(['mon', 'mon'], $two),
			'days-int' => $grid([1, 'mon'], $two),
			'days-bool' => $grid([true], $two),
			'days-nested' => $grid([['mon']], $two),
			'days-missing' => (string)json_encode(['periods' => $two]),
			'periods-missing' => (string)json_encode(['days' => ['mon']]),
			'periods-empty' => $grid(['mon'], []),
			'periods-string' => $grid(['mon'], '08:30'),
			'periods-keyed' => $grid(['mon'], ['x' => $p('08:00', '09:00'), 'y' => $p('09:00', '10:00')]),
			'period-not-array' => $grid(['mon'], [$p('08:00', '09:00'), 'later']),
			'period-start-missing' => $grid(['mon'], [['end' => '09:00']]),
			'period-end-bad' => $grid(['mon'], [$p('08:00', '24:00')]),
			'period-trimmed' => $grid(['mon'], [$p(' 08:00 ', '09:00 ')]),
			'period-single-digit' => $grid(['mon'], [$p('8:00', '09:00')]),
			'period-zero-length' => $grid(['mon'], [$p('09:00', '09:00')]),
			'period-reversed' => $grid(['mon'], [$p('10:00', '09:00')]),
			'periods-overlap' => $grid(['mon'], [$p('08:00', '09:00'), $p('08:59', '09:30')]),
			'periods-touch' => $grid(['mon'], [$p('08:00', '09:00'), $p('09:00', '09:30')]),
			'periods-gap' => $grid(['mon'], [$p('08:00', '09:00'), $p('10:00', '10:30')]),
			'period-midnight' => $grid(['mon'], [$p('00:00', '00:30'), $p('23:00', '23:59')]),
			'period-int' => $grid(['mon'], [$p(800, 900)]),
			'not-json' => '{days',
			'json-list' => '["mon"]',
			'json-number' => '7',
		];

		$service  = new TimetableGridService(appConfig: $this->createMock(originalClassName: IAppConfig::class));
		$recorded = [];
		foreach ($raws as $name => $raw) {
			$recorded[$name] = $service->normaliseGrid(raw: $raw);
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $recorded);
	}//end testTheGridCheckMatchesTheGoldenRecording()
}//end class
