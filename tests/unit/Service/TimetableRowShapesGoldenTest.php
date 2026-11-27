<?php

/**
 * Golden test for the read shape of a stored lesson and the period slot of a lesson.
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

use OCA\Planninq\Service\TimetableScenarioImporter;
use OCA\Planninq\Service\TimetableSessionRows;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Projects stored rows (arrays with and without `@self`, entities, rows
 * without an id, missing and non-text fields, every status) onto the read
 * shape, and finds the period slot of lessons (on, between and across period
 * times, unreadable times, other days), and compares every answer with the
 * fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Service\TimetableSessionRows
 * @covers \OCA\Planninq\Service\TimetableScenarioImporter
 */
class TimetableRowShapesGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/timetable-row-shapes.golden.json';

	/**
	 * Stored rows by name.
	 *
	 * @return array<string,mixed>
	 */
	private function rows(): array {
		$full = [
			'externalRef'      => 'x1',
			'sourceSystem'     => 'sis',
			'subject'          => 'Maths',
			'title'            => '',
			'startsAt'         => '2026-10-05T08:30:00+02:00',
			'endsAt'           => '2026-10-05T09:20:00+02:00',
			'groupReference'   => '3A',
			'teacherUserId'    => 'noor',
			'roomReference'    => 7,
			'roomLabel'        => '',
			'courseId'         => ['x'],
		];
		$rows = ['null' => null, 'text' => 'abc', 'no id' => ['subject' => 'x'], 'empty id' => ['id' => '']];
		foreach (['scheduled', 'cancelled', 'draft', 'other'] as $status) {
			$rows['array ' . $status] = array_merge($full, ['id' => 'a1', 'status' => $status]);
		}

		$rows['array none'] = array_merge($full, ['id' => 'a1']);

		$rows['self id']    = array_merge($full, ['@self' => ['id' => 's1'], 'id' => 'a2', 'title' => 'Own title']);
		$rows['bare']       = ['id' => 'b1'];
		$rows['entity']     = new class($full) {
			/**
			 * @param array<string,mixed> $data The object data.
			 */
			public function __construct(private array $data) {
			}

			/**
			 * The uuid.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				return 'e1';
			}

			/**
			 * The object data.
			 *
			 * @return array<string,mixed>
			 */
			public function getObject(): array {
				return array_merge($this->data, ['status' => 'draft']);
			}
		};
		$rows['entity no data'] = new class {
			/**
			 * The uuid.
			 *
			 * @return string
			 */
			public function getUuid(): string {
				return 'e2';
			}
		};
		return $rows;
	}//end rows()

	/**
	 * Lessons by name, as stored start and end.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function lessons(): array {
		return [
			'first period'    => ['startsAt' => '2026-10-05T08:30:00', 'endsAt' => '2026-10-05T09:20:00'],
			'two periods'     => ['startsAt' => '2026-10-05T08:30:00', 'endsAt' => '2026-10-05T10:10:00'],
			'three periods'   => ['startsAt' => '2026-10-06T08:30:00', 'endsAt' => '2026-10-06T11:00:00'],
			'off start'       => ['startsAt' => '2026-10-05T08:45:00', 'endsAt' => '2026-10-05T10:10:00'],
			'short'           => ['startsAt' => '2026-10-05T08:30:00', 'endsAt' => '2026-10-05T09:00:00'],
			'second period'   => ['startsAt' => '2026-10-07T09:20:00', 'endsAt' => '2026-10-07T10:10:00'],
			'other day'       => ['startsAt' => '2026-10-10T08:30:00', 'endsAt' => '2026-10-10T09:20:00'],
			'bad start'       => ['startsAt' => 'garbage', 'endsAt' => '2026-10-05T09:20:00'],
			'bad end'         => ['startsAt' => '2026-10-05T08:30:00', 'endsAt' => 'nope'],
			'missing'         => [],
			'end before'      => ['startsAt' => '2026-10-05T09:20:00', 'endsAt' => '2026-10-05T08:30:00'],
		];
	}//end lessons()

	/**
	 * Every read shape and slot matches the fixture.
	 *
	 * @return void
	 */
	public function testShapesMatchTheFixture(): void {
		$zone = date_default_timezone_get();
		date_default_timezone_set('Europe/Amsterdam');
		$answers   = [];
		$sessions  = new TimetableSessionRows();
		foreach ($this->rows() as $name => $row) {
			$answers['shape ' . $name] = $sessions->toReadShape(row: $row);
		}

		$periods = [];
		foreach (['mon', 'tue', 'wed', 'thu', 'fri'] as $day) {
			foreach ([[1, '08:30', '09:20'], [2, '09:20', '10:10'], [3, '10:10', '11:00'], [4, '11:15', '12:05']] as [$number, $start, $end]) {
				$periods[] = ['day' => $day, 'number' => $number, 'start' => $start, 'end' => $end];
			}
		}

		$reflection = new ReflectionClass(TimetableScenarioImporter::class);
		$importer   = $reflection->newInstanceWithoutConstructor();
		$slotOf     = $reflection->getMethod('slotOf');
		foreach ($this->lessons() as $name => $lesson) {
			$answers['slot ' . $name]          = $slotOf->invoke($importer, $lesson, $periods);
			$gapped = array_values(array_filter($periods, static fn (array $p): bool => $p['number'] !== 2));
			$answers['slot ' . $name . ' gap'] = $slotOf->invoke($importer, $lesson, $gapped);
		}

		date_default_timezone_set($zone);
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $answers);
	}//end testShapesMatchTheFixture()
}//end class
