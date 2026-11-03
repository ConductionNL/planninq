<?php

/**
 * Golden test for the dependency graph.
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

use OCA\Planninq\Service\DependencyGraph;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds seeded edge lists (typed, untyped, related, half-empty, numeric ids,
 * existing cycles) and records cyclePath() for every task pair and
 * deriveBlockedTaskIds() for seeded statuses.
 *
 * @covers \OCA\Planninq\Service\DependencyGraph
 */
class DependencyGraphGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/dependency-graph.golden.json';

	private const STATUSES = ['todo', 'in_progress', 'done', 'cancelled', 'review'];

	/**
	 * Every answer matches the recording.
	 *
	 * @return void
	 */
	public function testTheGraphMatchesTheGoldenRecording(): void {
		$graph    = new DependencyGraph();
		$recorded = [];
		for ($seed = 1; $seed <= 30; $seed++) {
			$recorded['seed-'.$seed] = $this->graphCase(graph: $graph, seed: $seed);
		}

		$json = json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($recorded as $name => $answer) {
			self::assertSame(expected: $expected[$name], actual: $answer, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($recorded));
	}//end testTheGraphMatchesTheGoldenRecording()

	/**
	 * One seeded graph and its answers.
	 *
	 * @param DependencyGraph $graph The graph.
	 * @param int             $seed  The seed.
	 *
	 * @return array<string,mixed>
	 */
	private function graphCase(DependencyGraph $graph, int $seed): array {
		$random = new Randomizer(new Mt19937($seed));
		$count  = $random->getInt(2, 7);
		$tasks  = [];
		for ($n = 1; $n <= $count; $n++) {
			$tasks[] = ($random->getInt(0, 3) === 0 ? (string)(10 + $n) : 't'.$n);
		}

		$edges = [];
		for ($e = 0; $e < $random->getInt(0, 12); $e++) {
			$edge = ['blocker' => $tasks[$random->getInt(0, ($count - 1))], 'blocked' => $tasks[$random->getInt(0, ($count - 1))]];
			$type = $random->getInt(0, 5);
			if ($type === 1) {
				$edge['type'] = 'blocks';
			} else if ($type === 2) {
				$edge['type'] = 'related';
			} else if ($type === 3) {
				$edge['blocker'] = '';
			} else if ($type === 4) {
				unset($edge['blocked']);
			}

			$edges[] = $edge;
		}

		$statuses = [];
		foreach ($tasks as $task) {
			if ($random->getInt(0, 4) > 0) {
				$statuses[$task] = self::STATUSES[$random->getInt(0, 4)];
			}
		}

		$paths = [];
		foreach ($tasks as $blocker) {
			foreach ($tasks as $blocked) {
				$paths[$blocker.'>'.$blocked] = $graph->cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked);
			}
		}

		return [
			'edges' => $edges,
			'statuses' => $statuses,
			'paths' => $paths,
			'blocked' => $graph->deriveBlockedTaskIds(edges: $edges, statusById: $statuses),
		];
	}//end graphCase()
}//end class
