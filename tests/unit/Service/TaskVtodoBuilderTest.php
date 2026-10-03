<?php

/**
 * Tests for TaskVtodoBuilder (planning-calendar task 2.2).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\TaskVtodoBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The VTODO a task becomes.
 */
class TaskVtodoBuilderTest extends TestCase {

	/**
	 * Unfold an iCalendar text into its property lines.
	 *
	 * @param string $ics The text.
	 *
	 * @return array<int,string>
	 */
	private function lines(string $ics): array {
		return explode("\r\n", str_replace("\r\n ", '', rtrim($ics, "\r\n")));
	}//end lines()

	/**
	 * Every property and every status and priority mapping.
	 *
	 * @return void
	 */
	public function testTaskMapsToVtodo(): void {
		$builder = new TaskVtodoBuilder();
		$task    = [
			'title'           => 'Export to CSV',
			'description'     => 'Both formats; see the note, too',
			'status'          => 'done',
			'priority'        => 'urgent',
			'dueDate'         => '2026-10-16',
			'startDate'       => '2026-10-12T00:00:00+02:00',
			'percentComplete' => 100,
			'completedAt'     => '2026-10-15T14:30:00+02:00',
		];
		$lines = $this->lines($builder->build(task: $task, uid: 'planninq-task-t1@inst', projectTitle: 'Vergunningen', url: 'https://nc.example/apps/planninq/projects/p1/tasks/t1', managedNote: 'Managed by Planninq.', stamp: '20260930T120000Z'));

		$this->assertSame('BEGIN:VCALENDAR', $lines[0]);
		$this->assertContains('BEGIN:VTODO', $lines);
		$this->assertContains('UID:planninq-task-t1@inst', $lines);
		$this->assertContains('DTSTAMP:20260930T120000Z', $lines);
		$this->assertContains('SUMMARY:Export to CSV', $lines);
		$this->assertContains('DUE;VALUE=DATE:20261016', $lines);
		$this->assertContains('DTSTART;VALUE=DATE:20261012', $lines);
		$this->assertContains('STATUS:COMPLETED', $lines);
		$this->assertContains('PRIORITY:1', $lines);
		$this->assertContains('PERCENT-COMPLETE:100', $lines);
		$this->assertContains('COMPLETED:20261015T123000Z', $lines);
		$this->assertContains('CATEGORIES:Vergunningen', $lines);
		$this->assertContains('END:VCALENDAR', $lines);

		$map = [
			['open', 'NEEDS-ACTION'], ['blocked', 'NEEDS-ACTION'], ['in_progress', 'IN-PROCESS'],
			['done', 'COMPLETED'], ['cancelled', 'CANCELLED'],
		];
		foreach ($map as [$status, $expected]) {
			$ics = $builder->build(task: ['title' => 'x', 'status' => $status], uid: 'u', projectTitle: '', url: '', managedNote: 'n', stamp: '20260930T120000Z');
			$this->assertContains('STATUS:' . $expected, $this->lines($ics), $status);
		}

		foreach ([['urgent', 1], ['high', 3], ['normal', 5], ['low', 9]] as [$priority, $expected]) {
			$ics = $builder->build(task: ['title' => 'x', 'priority' => $priority], uid: 'u', projectTitle: '', url: '', managedNote: 'n', stamp: '20260930T120000Z');
			$this->assertContains('PRIORITY:' . $expected, $this->lines($ics), $priority);
		}

		// No dates, no completion time, no project: the properties are left out.
		$bare = $this->lines($builder->build(task: ['title' => 'x', 'status' => 'open'], uid: 'u', projectTitle: '', url: '', managedNote: 'n', stamp: '20260930T120000Z'));
		foreach (['DUE', 'DTSTART', 'COMPLETED', 'CATEGORIES', 'URL'] as $absent) {
			$this->assertSame([], preg_grep('/^' . $absent . '[;:]/', $bare), $absent);
		}
	}//end testTaskMapsToVtodo()

	/**
	 * The description ends with the managed note, the URL links back, and text is escaped and folded.
	 *
	 * @return void
	 */
	public function testManagedNoteAndUrlArePresent(): void {
		$builder = new TaskVtodoBuilder();
		$note    = 'Managed by Planninq. Changes made here are replaced by the next change in Planninq.';
		$ics     = $builder->build(
			task: ['title' => 'Plan, review; ship', 'description' => "Line one\nLine two"],
			uid: 'u',
			projectTitle: 'A, B',
			url: 'https://nc.example/apps/planninq/projects/p1/tasks/t1',
			managedNote: $note,
			stamp: '20260930T120000Z'
		);

		foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
			$this->assertLessThanOrEqual(75, strlen($line), $line);
		}

		$lines = $this->lines($ics);
		$this->assertContains('SUMMARY:Plan\, review\; ship', $lines);
		$this->assertContains('DESCRIPTION:Line one\nLine two\n\n' . $note, $lines);
		$this->assertContains('URL:https://nc.example/apps/planninq/projects/p1/tasks/t1', $lines);
		$this->assertContains('CATEGORIES:A\, B', $lines);

		$only = $this->lines($builder->build(task: ['title' => 'x'], uid: 'u', projectTitle: '', url: '', managedNote: $note, stamp: '20260930T120000Z'));
		$this->assertContains('DESCRIPTION:' . $note, $only);
	}//end testManagedNoteAndUrlArePresent()
}//end class
