<?php

/**
 * Tests for TaskCalendarExportListener (planning-calendar task 2.3), over the
 * real TaskCalendarExportService and TaskScopeResolver.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/CalendarExportFixture.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Listener\TaskCalendarExportListener;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Tests\Unit\Support\CalendarExportFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Task events keep the Planninq lists in step.
 */
class TaskCalendarExportListenerTest extends TestCase {

	use CalendarExportFixture;

	private const TASK = ['title' => 'Export to CSV', 'status' => 'open', 'project' => 'p1', 'assignedTo' => 'alice', 'dueDate' => '2026-10-16'];

	private TaskCalendarExportService $export;

	/**
	 * The listener over the real service, with the given users switched on.
	 *
	 * @param array<int,string> $on The users.
	 *
	 * @return TaskCalendarExportListener
	 */
	private function listener(array $on = ['alice', 'bob']): TaskCalendarExportListener {
		$this->export = $this->makeExport(on: $on);

		return new TaskCalendarExportListener($this->makeScope(), $this->export, $this->createMock(LoggerInterface::class));
	}//end listener()

	/**
	 * A planninq task object (register 1, schema 2).
	 *
	 * @param array<string,mixed> $data   The data.
	 * @param string              $schema The schema id.
	 *
	 * @return ObjectEntity
	 */
	private function task(array $data, string $schema = '2'): ObjectEntity {
		return new class($data, $schema) extends ObjectEntity {
			// phpcs:disable
			public function __construct(private array $d, private string $s) {}
			public function getObject(): array { return $this->d; }
			public function getRegister(): string { return '1'; }
			public function getSchema(): string { return $this->s; }
			public function getUuid(): string { return 't1'; }
			// phpcs:enable
		};
	}//end task()

	/**
	 * Reassigning moves the VTODO from the old assignee's list to the new one's.
	 *
	 * @return void
	 */
	public function testReassignmentMovesTheVtodo(): void {
		$listener = $this->listener();
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK)));
		$this->assertNotNull($this->vtodo(user: 'alice', taskId: 't1'));

		$moved = ['assignedTo' => 'bob'] + self::TASK;
		$listener->handle(new ObjectUpdatedEvent($this->task($moved), $this->task(self::TASK)));
		$this->assertNull($this->vtodo(user: 'alice', taskId: 't1'));
		$this->assertStringContainsString('SUMMARY:Export to CSV', (string)$this->vtodo(user: 'bob', taskId: 't1'));
	}//end testReassignmentMovesTheVtodo()

	/**
	 * Deleting the task removes its VTODO from every list.
	 *
	 * @return void
	 */
	public function testDeleteRemovesTheVtodo(): void {
		$listener = $this->listener();
		$shared   = self::TASK + ['sharedWith' => ['bob']];
		$listener->handle(new ObjectCreatedEvent($this->task($shared)));
		$this->assertNotNull($this->vtodo(user: 'bob', taskId: 't1'));

		$listener->handle(new ObjectDeletedEvent($this->task($shared)));
		$this->assertNull($this->vtodo(user: 'alice', taskId: 't1'));
		$this->assertNull($this->vtodo(user: 'bob', taskId: 't1'));
	}//end testDeleteRemovesTheVtodo()

	/**
	 * An update rewrites the whole VTODO, so an edit made in the Tasks app is replaced.
	 *
	 * @return void
	 */
	public function testUpdateRewritesTheWholeVtodo(): void {
		$listener = $this->listener();
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK)));
		$list = $this->backend->lists['principals/users/alice'];
		$this->backend->objects[$list]['planninq-task-t1.ics'] = "BEGIN:VCALENDAR\r\nSUMMARY:Edited in Tasks\r\nEND:VCALENDAR\r\n";

		$done = ['title' => 'Export to CSV and XLSX', 'status' => 'done'] + self::TASK;
		$listener->handle(new ObjectUpdatedEvent($this->task($done), $this->task(self::TASK)));
		$ics = (string)$this->vtodo(user: 'alice', taskId: 't1');
		$this->assertStringContainsString('SUMMARY:Export to CSV and XLSX', $ics);
		$this->assertStringContainsString('STATUS:COMPLETED', $ics);
		$this->assertStringNotContainsString('Edited in Tasks', $ics);
	}//end testUpdateRewritesTheWholeVtodo()

	/**
	 * Nobody switched on: nothing is written, no list is made; not a task: ignored.
	 *
	 * @return void
	 */
	public function testNoExportWhenSwitchedOff(): void {
		$listener = $this->listener(on: []);
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK)));
		$this->assertSame([], $this->backend->created);
		$this->assertSame([], $this->objects->saved);

		$listener = $this->listener();
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK, schema: '9')));
		$this->assertSame([], $this->backend->created);
	}//end testNoExportWhenSwitchedOff()

	/**
	 * The first export stores the UID on the task.
	 *
	 * @return void
	 */
	public function testFirstExportStoresUid(): void {
		$listener = $this->listener();
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK)));
		$this->assertCount(1, $this->objects->saved);
		$this->assertSame('planninq-task-t1@inst', $this->objects->saved[0]['object']['calendarEventUid']);
	}//end testFirstExportStoresUid()

	/**
	 * An update that changes only calendarEventUid (the write-back) writes nothing.
	 *
	 * @return void
	 */
	public function testUidOnlyUpdateIsIgnored(): void {
		$listener = $this->listener();
		$withUid  = self::TASK + ['calendarEventUid' => 'planninq-task-t1@inst'];
		$listener->handle(new ObjectUpdatedEvent($this->task(array_reverse($withUid, true)), $this->task(self::TASK)));
		$this->assertSame([], $this->backend->created);
		$this->assertSame([], $this->objects->saved);
	}//end testUidOnlyUpdateIsIgnored()

	/**
	 * A failing CalDAV write is logged and never thrown into OpenRegister's save.
	 *
	 * @return void
	 */
	public function testExportFailureDoesNotThrow(): void {
		$listener = $this->listener();
		$this->backend->fail = true;
		$listener->handle(new ObjectCreatedEvent($this->task(self::TASK)));
		$this->assertSame([], $this->objects->saved);
	}//end testExportFailureDoesNotThrow()
}//end class
