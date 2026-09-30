<?php

/**
 * Planninq Timetable CSV Parser
 *
 * Reads the two sheets an admin uploads when no app supplies the activities:
 * rooms (reference, capacity, type) and activities (group, subject, teacher,
 * lessons per week, lesson length, room type). Each refusal names its line.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

/**
 * Parses the rooms and activities sheets into the rows the input builder reads.
 *
 * A result is {rows, errors}; an error is {line, field, code, message}. Codes:
 * `header`, `empty`, `whole-number`, `unknown-room-type`. The header check
 * ignores case and surrounding spaces; a comma or a semicolon separates columns.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
 */
class TimetableCsvParser {

	/**
	 * The rooms sheet columns, in order.
	 *
	 * @var array<int,string>
	 */
	public const ROOM_COLUMNS = ['reference', 'capacity', 'type'];

	/**
	 * The activities sheet columns, in order.
	 *
	 * @var array<int,string>
	 */
	public const ACTIVITY_COLUMNS = ['group', 'subject', 'teacher', 'lessons per week', 'lesson length', 'room type'];

	/**
	 * The row key each activities column is stored under.
	 *
	 * @var array<string,string>
	 */
	private const ACTIVITY_KEYS = [
		'group'            => 'group',
		'subject'          => 'subject',
		'teacher'          => 'teacher',
		'lessons per week' => 'lessonsPerWeek',
		'lesson length'    => 'lessonLength',
		'room type'        => 'roomType',
	];

	/**
	 * Parse the rooms sheet.
	 *
	 * @param string $csv The sheet as text.
	 *
	 * @return array{rows:array<int,array{reference:string,capacity:int,type:string}>,errors:array<int,array{line:int,field:string,code:string,message:string}>}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function parseRooms(string $csv): array {
		$rows   = [];
		$errors = [];
		foreach ($this->records(csv: $csv, columns: self::ROOM_COLUMNS, errors: $errors) as $line => $cells) {
			$capacity = $this->wholeNumber(value: $cells['capacity'], line: $line, field: 'capacity', errors: $errors);
			if ($this->filled(cells: $cells, line: $line, errors: $errors) === false || $capacity === null) {
				continue;
			}

			$rows[] = ['reference' => $cells['reference'], 'capacity' => $capacity, 'type' => $cells['type']];
		}

		return ['rows' => $rows, 'errors' => $errors];
	}//end parseRooms()

	/**
	 * Parse the activities sheet; every room type must be one of the rooms' types.
	 *
	 * @param string            $csv       The sheet as text.
	 * @param array<int,string> $roomTypes The room types the rooms sheet holds.
	 *
	 * @return array{rows:array<int,array<string,string|int>>,errors:array<int,array{line:int,field:string,code:string,message:string}>}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	public function parseActivities(string $csv, array $roomTypes): array {
		$rows   = [];
		$errors = [];
		foreach ($this->records(csv: $csv, columns: self::ACTIVITY_COLUMNS, errors: $errors) as $line => $cells) {
			$lessons = $this->wholeNumber(value: $cells['lessons per week'], line: $line, field: 'lessons per week', errors: $errors);
			$length  = $this->wholeNumber(value: $cells['lesson length'], line: $line, field: 'lesson length', errors: $errors);
			$filled  = $this->filled(cells: $cells, line: $line, errors: $errors);
			$known   = $this->knownRoomType(type: $cells['room type'], roomTypes: $roomTypes, line: $line, errors: $errors);
			if ($filled === false || $known === false || $lessons === null || $length === null) {
				continue;
			}

			$row = [];
			foreach (self::ACTIVITY_KEYS as $column => $key) {
				$row[$key] = $cells[$column];
			}

			$row['lessonsPerWeek'] = $lessons;
			$row['lessonLength']   = $length;
			$rows[] = $row;
		}

		return ['rows' => $rows, 'errors' => $errors];
	}//end parseActivities()

	/**
	 * The data lines by line number, cells keyed by column; a wrong header adds one error and yields nothing.
	 *
	 * @param string                                                                $csv     The sheet.
	 * @param array<int,string>                                                     $columns The expected columns.
	 * @param array<int,array{line:int,field:string,code:string,message:string}> $errors  Errors, appended to.
	 *
	 * @return array<int,array<string,string>>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function records(string $csv, array $columns, array &$errors): array {
		$lines = preg_split('/\r\n|\r|\n/', trim($csv));
		if ($lines === false) {
			$lines = [];
		}

		$head  = array_map(static fn (string $cell): string => mb_strtolower(trim($cell)), $this->cells(line: (string)($lines[0] ?? '')));
		if ($head !== $columns) {
			$errors[] = $this->error(line: 1, field: '', code: 'header', message: 'The first line must be: '.implode(', ', $columns).'.');
			return [];
		}

		$records = [];
		foreach (array_slice($lines, 1, null, true) as $index => $line) {
			if (trim($line) === '') {
				continue;
			}

			$cells = array_pad(array_map('trim', $this->cells(line: $line)), count($columns), '');
			$records[$index + 1] = array_combine($columns, array_slice($cells, 0, count($columns)));
		}

		return $records;
	}//end records()

	/**
	 * The cells of one line, split on the separator the line uses.
	 *
	 * @param string $line One line.
	 *
	 * @return array<int,string>
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function cells(string $line): array {
		$separator = ',';
		if (substr_count($line, ';') > substr_count($line, ',')) {
			$separator = ';';
		}

		return array_map('strval', str_getcsv($line, $separator, '"', ''));
	}//end cells()

	/**
	 * Whether no cell is empty; each empty cell adds an error.
	 *
	 * @param array<string,string>                                                  $cells  The line's cells.
	 * @param int                                                                   $line   The line number.
	 * @param array<int,array{line:int,field:string,code:string,message:string}> $errors Errors, appended to.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function filled(array $cells, int $line, array &$errors): bool {
		$filled = true;
		foreach ($cells as $field => $value) {
			if ($value === '') {
				$errors[] = $this->error(line: $line, field: $field, code: 'empty', message: "Line {$line}: {$field} is empty.");
				$filled   = false;
			}
		}

		return $filled;
	}//end filled()

	/**
	 * A whole number of 1 or more, or null with an error; an empty cell is left to filled().
	 *
	 * @param string                                                                $value  The cell.
	 * @param int                                                                   $line   The line number.
	 * @param string                                                                $field  The column.
	 * @param array<int,array{line:int,field:string,code:string,message:string}> $errors Errors, appended to.
	 *
	 * @return int|null
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function wholeNumber(string $value, int $line, string $field, array &$errors): ?int {
		if (preg_match('/^\d+$/', $value) === 1 && (int)$value >= 1) {
			return (int)$value;
		}

		if ($value !== '') {
			$errors[] = $this->error(line: $line, field: $field, code: 'whole-number', message: "Line {$line}: {$field} must be a whole number of 1 or more.");
		}

		return null;
	}//end wholeNumber()

	/**
	 * Whether a room type is one of the rooms' types; an unknown one adds an error.
	 *
	 * @param string                                                                $type      The cell.
	 * @param array<int,string>                                                     $roomTypes The known types.
	 * @param int                                                                   $line      The line number.
	 * @param array<int,array{line:int,field:string,code:string,message:string}> $errors    Errors, appended to.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function knownRoomType(string $type, array $roomTypes, int $line, array &$errors): bool {
		if ($type === '' || in_array($type, $roomTypes, true) === true) {
			return true;
		}

		$message  = "Line {$line}: room type {$type} is not on the rooms sheet.";
		$errors[] = $this->error(line: $line, field: 'room type', code: 'unknown-room-type', message: $message);
		return false;
	}//end knownRoomType()

	/**
	 * One refusal.
	 *
	 * @param int    $line    The line number.
	 * @param string $field   The column, empty for the header.
	 * @param string $code    The refusal code.
	 * @param string $message A readable refusal.
	 *
	 * @return array{line:int,field:string,code:string,message:string}
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-2.2
	 */
	private function error(int $line, string $field, string $code, string $message): array {
		return ['line' => $line, 'field' => $field, 'code' => $code, 'message' => $message];
	}//end error()
}//end class
