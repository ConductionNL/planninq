<?php

/**
 * Planninq TaskVtodoBuilder
 *
 * Turns a planninq task into the iCalendar VTODO that the one-way export
 * writes to a user's "Planninq" task list (planning-calendar). Pure: no
 * Nextcloud services, so every mapping is tested on its own.
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
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Builds the VTODO text for a task.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
 */
class TaskVtodoBuilder {

	/**
	 * Planninq status to VTODO STATUS.
	 */
	private const STATUS = [
		'open'        => 'NEEDS-ACTION',
		'blocked'     => 'NEEDS-ACTION',
		'in_progress' => 'IN-PROCESS',
		'done'        => 'COMPLETED',
		'cancelled'   => 'CANCELLED',
	];

	/**
	 * Planninq priority to VTODO PRIORITY (1 highest, 9 lowest).
	 */
	private const PRIORITY = [
		'urgent' => 1,
		'high'   => 3,
		'normal' => 5,
		'low'    => 9,
	];

	/**
	 * The VCALENDAR text holding the task's VTODO.
	 *
	 * @param array<string,mixed> $task         The task data.
	 * @param string              $uid          The VTODO UID.
	 * @param string              $projectTitle The project's title, or ''.
	 * @param string              $url          The task's address, or ''.
	 * @param string              $managedNote  The line that says planninq overwrites edits.
	 * @param string              $stamp        DTSTAMP as a UTC date-time (Ymd\THis\Z).
	 *
	 * @return string The iCalendar text, CRLF line ends, folded at 75 octets.
	 *
	 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
	 */
	public function build(array $task, string $uid, string $projectTitle, string $url, string $managedNote, string $stamp): string {
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Conduction//Planninq//EN',
			'BEGIN:VTODO',
			'UID:' . $this->escape(value: $uid),
			'DTSTAMP:' . $stamp,
			'SUMMARY:' . $this->escape(value: $this->text(value: ($task['title'] ?? ''))),
			'DESCRIPTION:' . $this->escape(value: $this->description(task: $task, managedNote: $managedNote)),
			'STATUS:' . (self::STATUS[$this->text(value: ($task['status'] ?? ''))] ?? 'NEEDS-ACTION'),
		];

		$priority = $this->text(value: ($task['priority'] ?? ''));
		if (isset(self::PRIORITY[$priority]) === true) {
			$lines[] = 'PRIORITY:' . self::PRIORITY[$priority];
		}

		$lines = array_merge($lines, $this->dates(task: $task));

		if ($projectTitle !== '') {
			$lines[] = 'CATEGORIES:' . $this->escape(value: $projectTitle);
		}

		if ($url !== '') {
			$lines[] = 'URL:' . $url;
		}

		$lines[] = 'END:VTODO';
		$lines[] = 'END:VCALENDAR';

		return implode("\r\n", array_map(fn (string $line): string => $this->fold(line: $line), $lines)) . "\r\n";
	}//end build()

	/**
	 * DUE, DTSTART, PERCENT-COMPLETE and COMPLETED, each only when the task has it.
	 *
	 * @param array<string,mixed> $task The task data.
	 *
	 * @return array<int,string> The property lines.
	 */
	private function dates(array $task): array {
		$lines = [];
		$due   = $this->day(value: ($task['dueDate'] ?? null));
		if ($due !== '') {
			$lines[] = 'DUE;VALUE=DATE:' . $due;
		}

		$start = $this->day(value: ($task['startDate'] ?? null));
		if ($start !== '') {
			$lines[] = 'DTSTART;VALUE=DATE:' . $start;
		}

		if (is_numeric($task['percentComplete'] ?? null) === true) {
			$lines[] = 'PERCENT-COMPLETE:' . max(0, min(100, (int)$task['percentComplete']));
		}

		$completed = $this->utc(value: ($task['completedAt'] ?? null));
		if ($completed !== '') {
			$lines[] = 'COMPLETED:' . $completed;
		}

		return $lines;
	}//end dates()

	/**
	 * The description followed by the managed note.
	 *
	 * @param array<string,mixed> $task        The task data.
	 * @param string              $managedNote The note.
	 *
	 * @return string The text.
	 */
	private function description(array $task, string $managedNote): string {
		$text = trim($this->text(value: ($task['description'] ?? '')));
		if ($text === '') {
			return $managedNote;
		}

		return $text . "\n\n" . $managedNote;
	}//end description()

	/**
	 * The leading date of an ISO date or date-time as it is written, as Ymd.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The date, or '' when there is none.
	 */
	private function day(mixed $value): string {
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $this->text(value: $value), $match) !== 1) {
			return '';
		}

		return $match[1] . $match[2] . $match[3];
	}//end day()

	/**
	 * A date-time in UTC as Ymd\THis\Z.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The UTC date-time, or '' when it does not parse.
	 */
	private function utc(mixed $value): string {
		$text = $this->text(value: $value);
		if ($text === '') {
			return '';
		}

		try {
			$moment = new DateTimeImmutable($text);
		} catch (\Exception $e) {
			return '';
		}

		return $moment->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}//end utc()

	/**
	 * A scalar as a string; anything else as ''.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The text.
	 */
	private function text(mixed $value): string {
		if (is_scalar($value) === true) {
			return (string)$value;
		}

		return '';
	}//end text()

	/**
	 * Escape a TEXT value (RFC 5545 section 3.3.11).
	 *
	 * @param string $value The value.
	 *
	 * @return string The escaped value.
	 */
	private function escape(string $value): string {
		$value = str_replace(["\r\n", "\r"], "\n", $value);

		return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $value);
	}//end escape()

	/**
	 * Fold a content line at 75 octets without splitting a character.
	 *
	 * @param string $line The line.
	 *
	 * @return string The folded line.
	 */
	private function fold(string $line): string {
		$parts = [];
		$limit = 75;
		while (strlen($line) > $limit) {
			$cut = $limit;
			while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
				$cut--;
			}

			$parts[] = substr($line, 0, $cut);
			$line    = ' ' . substr($line, $cut);
			$limit   = 75;
		}

		$parts[] = $line;

		return implode("\r\n", $parts);
	}//end fold()
}//end class
