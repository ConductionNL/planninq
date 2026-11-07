<?php

/**
 * Golden test for the task to VTODO builder.
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
use DateTimeZone;
use OCA\Planninq\Service\TaskVtodoBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Builds seeded tasks (every status and priority, dates in several shapes,
 * text with separators, line breaks and multi-byte characters past the fold
 * width, non-scalar values) with seeded metadata, and compares the VCALENDAR
 * text with the fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Service\TaskVtodoBuilder
 */
class TaskVtodoBuilderGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/task-vtodo-builder.golden.json';

	private const TEXTS = [
		'',
		'Plain',
		'Plan, review; ship \\ done',
		"Line one\r\nLine two\rLine three\nLine four",
		'Ëén lange regel met veel tekens die over de vouwbreedte gaat, ook met € en ✓ erin, en nog wat meer woorden erachter',
		'   padded   ',
	];

	/**
	 * Stands in for the value 12.5, which DateTimeImmutable reads as 12:05 today.
	 */
	private const TODAY_AT_12_05 = '(12.5 read as 12:05 today, UTC)';

	private const VALUES = [null, '', '2026-10-05', '2026-10-05T14:30:00+02:00', '2026-13-45', 'not a date', 20261005, 12.5, true, ['nested']];

	/**
	 * The recorded calendar texts equal the fixture.
	 *
	 * @return void
	 */
	public function testBuildMatchesTheGoldenRecording(): void {
		$builder = new TaskVtodoBuilder();
		$cases   = [];
		for ($seed = 1; $seed <= 60; $seed++) {
			mt_srand($seed);
			$task = [];
			foreach (['title', 'description'] as $field) {
				if (mt_rand(0, 5) > 0) {
					$task[$field] = self::TEXTS[mt_rand(0, (count(self::TEXTS) - 1))];
				}
			}

			$task['status']   = ['open', 'blocked', 'in_progress', 'done', 'cancelled', 'unknown', ''][mt_rand(0, 6)];
			$task['priority'] = ['urgent', 'high', 'normal', 'low', 'none', ''][mt_rand(0, 5)];
			foreach (['dueDate', 'startDate', 'completedAt', 'percentComplete'] as $field) {
				if (mt_rand(0, 2) > 0) {
					$task[$field] = self::VALUES[mt_rand(0, (count(self::VALUES) - 1))];
				}
			}

			if (mt_rand(0, 3) === 0) {
				$task['percentComplete'] = [-20, 0, 55, 100, 140, '70'][mt_rand(0, 5)];
			}

			$meta    = [
				'uid'          => 'planninq-task-' . $seed . (mt_rand(0, 3) === 0 ? ';x,y' : '') . '@inst',
				'projectTitle' => self::TEXTS[mt_rand(0, (count(self::TEXTS) - 1))],
				'url'          => (mt_rand(0, 2) === 0 ? '' : 'https://nc.example/apps/planninq/projects/p' . $seed . '/tasks/t' . str_repeat('x', mt_rand(0, 80))),
				'managedNote'  => (mt_rand(0, 1) === 0 ? 'Managed by Planninq.' : 'Beheerd door Planninq, wijzigingen hier worden overschreven; ✓'),
				'stamp'        => '20260930T120000Z',
			];
			$cases[] = ['seed' => $seed, 'ics' => $this->buildIcs(builder: $builder, task: $task, meta: $meta)];
		}//end for

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		$json = str_replace(search: $this->todayAt(time: '12.5'), replace: self::TODAY_AT_12_05, subject: $json);
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			if (is_dir(dirname(self::FIXTURE)) === false) {
				mkdir(dirname(self::FIXTURE));
			}

			file_put_contents(self::FIXTURE, $json);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testBuildMatchesTheGoldenRecording()

	/**
	 * A time of day on today's date, in the builder's UTC format.
	 *
	 * @param string $time The time as the builder receives it.
	 *
	 * @return string
	 */
	private function todayAt(string $time): string {
		return (new DateTimeImmutable($time))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}//end todayAt()

	/**
	 * Call TaskVtodoBuilder::build() for a task and its metadata.
	 *
	 * @param TaskVtodoBuilder     $builder The builder.
	 * @param array<string,mixed>  $task    The task.
	 * @param array<string,string> $meta    uid, projectTitle, url, managedNote and stamp.
	 *
	 * @return string
	 */
	private function buildIcs(TaskVtodoBuilder $builder, array $task, array $meta): string {
		return $builder->build(task: $task, meta: $meta);
	}//end buildIcs()
}//end class
