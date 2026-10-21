<?php

/**
 * Tests for the rooms and activities sheets (timetabling-generator task 2.2).
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

use OCA\Planninq\Service\TimetableCsvParser;
use PHPUnit\Framework\TestCase;

/**
 * Header check, whole numbers and room types, each refusal with its line.
 */
class TimetableCsvParserTest extends TestCase {

	/**
	 * A good sheet: header case and spaces ignored, semicolons accepted, blank lines skipped.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testAGoodSheetBecomesRows(): void {
		$parser = new TimetableCsvParser();
		$rooms  = $parser->parseRooms(csv: "Reference,Capacity,Type\nr-101,30,classroom\n\nlab-1,16,lab\n");
		self::assertSame(expected: [], actual: $rooms['errors']);
		self::assertSame(expected: [['reference' => 'r-101', 'capacity' => 30, 'type' => 'classroom'], ['reference' => 'lab-1', 'capacity' => 16, 'type' => 'lab']], actual: $rooms['rows']);

		$activities = $parser->parseActivities(csv: " Group ; Subject;Teacher;Lessons per week;Lesson length;Room type\r\n3A;Chemistry;noor;2;2;lab\r\n", roomTypes: ['classroom', 'lab']);
		self::assertSame(expected: [], actual: $activities['errors']);
		self::assertSame(
			expected: [['group' => '3A', 'subject' => 'Chemistry', 'teacher' => 'noor', 'lessonsPerWeek' => 2, 'lessonLength' => 2, 'roomType' => 'lab']],
			actual: $activities['rows']
		);
	}//end testAGoodSheetBecomesRows()

	/**
	 * A sheet whose first line is not the header is refused as a whole.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testAWrongHeaderIsRefused(): void {
		$result = (new TimetableCsvParser())->parseActivities(csv: "group,subject,teacher,hours\n3A,English,klaas,3\n", roomTypes: ['classroom']);

		self::assertSame(expected: [], actual: $result['rows']);
		self::assertCount(expectedCount: 1, haystack: $result['errors']);
		self::assertSame(expected: 1, actual: $result['errors'][0]['line']);
		self::assertSame(expected: 'header', actual: $result['errors'][0]['code']);
		self::assertSame(expected: 'The first line must be: group, subject, teacher, lessons per week, lesson length, room type.', actual: $result['errors'][0]['message']);
	}//end testAWrongHeaderIsRefused()

	/**
	 * Lessons per week and lesson length must be whole numbers of 1 or more; an unknown room type is refused; each names its line.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testEachRefusalNamesItsLine(): void {
		$csv = implode(
			"\n",
			[
				'group,subject,teacher,lessons per week,lesson length,room type',
				'3A,English,klaas,3,1,classroom',
				'3A,Maths,noor,three,1,classroom',
				'3B,Maths,noor,2,1.5,classroom',
				'3B,Music,piet,0,1,classroom',
				'3B,Swimming,piet,1,2,pool',
				'3C,,piet,1,1,classroom',
			]
		);
		$result = (new TimetableCsvParser())->parseActivities(csv: $csv, roomTypes: ['classroom']);

		self::assertSame(expected: ['3A:English'], actual: array_map(static fn (array $row): string => $row['group'].':'.$row['subject'], $result['rows']));
		self::assertSame(
			expected: [
				[3, 'lessons per week', 'whole-number'],
				[4, 'lesson length', 'whole-number'],
				[5, 'lessons per week', 'whole-number'],
				[6, 'room type', 'unknown-room-type'],
				[7, 'subject', 'empty'],
			],
			actual: array_map(static fn (array $error): array => [$error['line'], $error['field'], $error['code']], $result['errors'])
		);
		self::assertSame(expected: 'Line 6: room type pool is not on the rooms sheet.', actual: $result['errors'][3]['message']);
	}//end testEachRefusalNamesItsLine()

	/**
	 * A room capacity must be a whole number too.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function testARoomCapacityMustBeAWholeNumber(): void {
		$result = (new TimetableCsvParser())->parseRooms(csv: "reference,capacity,type\nr-101,thirty,classroom\n");

		self::assertSame(expected: [], actual: $result['rows']);
		self::assertSame(expected: 'Line 2: capacity must be a whole number of 1 or more.', actual: $result['errors'][0]['message']);
	}//end testARoomCapacityMustBeAWholeNumber()
}//end class
