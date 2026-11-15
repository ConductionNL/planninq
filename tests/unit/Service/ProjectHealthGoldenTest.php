<?php

/**
 * Golden test for the project health read from status reports.
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

use OCA\Planninq\Service\ProjectHealthService;
use OCA\Planninq\Service\ProjectMembershipService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Records healthFromReport() for seeded reports and newestReport() for seeded
 * stored reports with and without the report being saved.
 *
 * @covers \OCA\Planninq\Service\ProjectHealthService
 */
class ProjectHealthGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/project-health.golden.json';

	private const VALUES = ['onTrack', 'atRisk', 'offTrack', 'unknown', '', null, 2];

	private const DATES = ['2026-09-01', '2026-09-01T10:00:00Z', '2026-10-15', '2026-08-31T23:59:00+02:00', '', null, 20260901];

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testHealthAndNewestReportMatchTheGoldenRecording(): void {
		$recorded = ['health' => ['null' => $this->service(rows: [])->healthFromReport(report: null)], 'newest' => []];
		for ($seed = 1; $seed <= 40; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$recorded['health']['seed-'.$seed] = $this->service(rows: [])->healthFromReport(report: $this->report(random: $random));
		}

		for ($seed = 1; $seed <= 25; $seed++) {
			$random = new Randomizer(new Mt19937(1000 + $seed));
			$rows   = [];
			for ($n = 1; $n <= $random->getInt(0, 4); $n++) {
				$rows[] = ['id' => 'r'.$n, 'data' => ['title' => 'report '.$n, 'reportDate' => self::DATES[$random->getInt(0, 6)]]];
			}

			$saving = null;
			if ($random->getInt(0, 2) > 0) {
				$saving = ['title' => 'saving', 'reportDate' => self::DATES[$random->getInt(0, 6)]];
			}

			$recorded['newest']['seed-'.$seed] = $this->service(rows: $rows)->newestReport(projectId: 'p-1', reportId: 'r'.$random->getInt(1, 3), saving: $saving);
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: json_decode($json, true));
	}//end testHealthAndNewestReportMatchTheGoldenRecording()

	/**
	 * A seeded report with some aspects set.
	 *
	 * @param Randomizer $random The random source.
	 *
	 * @return array<string,mixed>
	 */
	private function report(Randomizer $random): array {
		$report = [];
		foreach (ProjectHealthService::ASPECTS as $aspect) {
			if ($random->getInt(0, 3) > 0) {
				$report['status'.$aspect] = self::VALUES[$random->getInt(0, 6)];
			}
		}

		if ($random->getInt(0, 3) > 0) {
			$report['reportDate'] = self::DATES[$random->getInt(0, 6)];
		}

		return $report;
	}//end report()

	/**
	 * The service over stored report rows.
	 *
	 * @param array<int,array<string,mixed>> $rows The stored rows.
	 *
	 * @return ProjectHealthService
	 */
	private function service(array $rows): ProjectHealthService {
		$membership = $this->createMock(originalClassName: ProjectMembershipService::class);
		$membership->method('rows')->willReturn($rows);

		return new ProjectHealthService(membership: $membership, container: $this->createMock(originalClassName: ContainerInterface::class), logger: new NullLogger());
	}//end service()
}//end class
