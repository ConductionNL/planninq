<?php

/**
 * Golden test for the Microsoft Project plan mapper.
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

use OCA\Planninq\Service\MsProjectPlanMapper;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Maps seeded plans (outline levels from 0 to 5, summaries, milestones,
 * missing dates, notes and names, repeated UIDs, links of every type with and
 * without lag, to phases and to unknown tasks) and compares the result with
 * the fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Service\MsProjectPlanMapper
 * @covers \OCA\Planninq\Service\MsProjectOutlineWalk
 */
class MsProjectPlanMapperGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/msproject-plan-mapper.golden.json';

	private const NAMES = ['', '  ', 'Foundation', 'Walls > roof', 'Ëén taak'];

	private const DATES = [null, '2027-03-01', '2027-04-15'];

	private const NOTES = [null, '', 'Check with the client', "Two\nlines"];

	/**
	 * The mapped plans equal the fixture.
	 *
	 * @return void
	 */
	public function testMapMatchesTheGoldenRecording(): void {
		$mapper = new MsProjectPlanMapper();
		$cases  = [];
		for ($seed = 1; $seed <= 80; $seed++) {
			$plan    = $this->plan(random: new Randomizer(new Mt19937($seed)), seed: $seed);
			$cases[] = ['seed' => $seed, 'mapped' => $mapper->map(plan: $plan)];
		}

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($cases as $index => $case) {
			self::assertSame(expected: ($expected[$index] ?? null), actual: $case, message: 'seed ' . $case['seed']);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testMapMatchesTheGoldenRecording()

	/**
	 * A seeded plan in the parser's shape.
	 *
	 * @param Randomizer $random The seeded randomizer.
	 * @param int        $seed   The seed, used in the plan name.
	 *
	 * @return array{name:string,saveVersion:string,tasks:list<array<string,mixed>>}
	 */
	private function plan(Randomizer $random, int $seed): array {
		$count = $random->getInt(0, 14);
		$tasks = [];
		for ($index = 0; $index < $count; $index++) {
			$tasks[] = $this->task(random: $random, count: $count);
		}

		return ['name' => 'plan-' . $seed . '.xml', 'saveVersion' => (string)$random->getInt(12, 14), 'tasks' => $tasks];
	}//end plan()

	/**
	 * One seeded task.
	 *
	 * @param Randomizer $random The seeded randomizer.
	 * @param int        $count  How many tasks the plan has.
	 *
	 * @return array<string,mixed>
	 */
	private function task(Randomizer $random, int $count): array {
		$links = [];
		for ($link = $random->getInt(0, 3); $link > 0; $link--) {
			$links[] = ['uid' => (string)$random->getInt(1, ($count + 2)), 'type' => $random->getInt(0, 3), 'lag' => [0, 0, 480, -60][$random->getInt(0, 3)]];
		}

		return [
			'uid'          => (string)$random->getInt(1, ($count + 1)),
			'name'         => self::NAMES[$random->getInt(0, 4)],
			'level'        => [1, 1, 2, 2, 3, 4, 5, 0][$random->getInt(0, 7)],
			'summary'      => $random->getInt(0, 2) === 0,
			'milestone'    => $random->getInt(0, 4) === 0,
			'start'        => self::DATES[$random->getInt(0, 2)],
			'finish'       => self::DATES[$random->getInt(0, 2)],
			'minutes'      => [null, 0, 480, 2400][$random->getInt(0, 3)],
			'percent'      => [0, 0, 30, 99, 100][$random->getInt(0, 4)],
			'notes'        => self::NOTES[$random->getInt(0, 3)],
			'predecessors' => $links,
			'hasResource'  => $random->getInt(0, 3) === 0,
		];
	}//end task()
}//end class
