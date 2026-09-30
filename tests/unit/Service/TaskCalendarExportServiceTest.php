<?php

/**
 * Tests for TaskCalendarExportService (planning-calendar tasks 2.1, 2.2, 2.4).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-2.4
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';
require_once __DIR__ . '/../Support/CalendarExportFixture.php';

use OCA\Planninq\BackgroundJob\TaskCalendarBackfillJob;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Tests\Unit\Support\CalendarExportFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * The switch, the list, the backfill and the UID write-back.
 */
class TaskCalendarExportServiceTest extends TestCase {

	use CalendarExportFixture;
	use RegisterSchemaValidation;

	private const TASK = ['title' => 'Export to CSV', 'status' => 'open', 'project' => 'p1', 'assignedTo' => 'alice', 'dueDate' => '2026-10-16'];

	/**
	 * The export is off until the user switches it on.
	 *
	 * @return void
	 */
	public function testExportToCaldavDefaultsOff(): void {
		$export = $this->makeExport();
		$this->assertSame([TaskCalendarExportService::SWITCH_KEY => false, 'caldavAvailable' => true], $export->values(userId: 'alice'));
		$this->assertSame(0, $export->exportTask(taskId: 't1', task: self::TASK, users: ['alice']));
		$this->assertSame([], $this->backend->created);
	}//end testExportToCaldavDefaultsOff()

	/**
	 * Without the DAV backend the switch reports the export unavailable and nothing is written.
	 *
	 * @return void
	 */
	public function testUnavailableWithoutTheDavBackend(): void {
		$export = $this->makeExport(on: ['alice'], withDav: false);
		$this->assertFalse($export->values(userId: 'alice')['caldavAvailable']);
		$this->assertSame(0, $export->exportTask(taskId: 't1', task: self::TASK, users: ['alice']));
	}//end testUnavailableWithoutTheDavBackend()

	/**
	 * Switching on queues the backfill once; the job writes every assigned task into a VTODO-only "Planninq" list.
	 *
	 * @return void
	 */
	public function testSwitchOnExportsExistingTasks(): void {
		$export = $this->makeExport(assigned: [
			't1' => self::TASK,
			't2' => ['title' => 'Import from CSV', 'project' => 'p1', 'assignedTo' => 'bob', 'sharedWith' => ['alice']],
			't3' => ['title' => 'Plan demo', 'project' => 'p1', 'assignedTo' => 'bob'],
		]);
		$export->apply(userId: 'alice', data: [TaskCalendarExportService::SWITCH_KEY => true]);
		$export->apply(userId: 'alice', data: [TaskCalendarExportService::SWITCH_KEY => 'true']);

		$this->assertSame([[TaskCalendarBackfillJob::class, ['userId' => 'alice']]], $this->queued);
		$this->assertTrue($export->isOn(userId: 'alice'));

		$job = new TaskCalendarBackfillJob($this->createMock(ITimeFactory::class), $export);
		$this->assertSame(2, $job->backfill(argument: ['userId' => 'alice']));
		$this->assertSame([['planninq', 'task', []]], $this->objects->searches);
		$this->assertNull($this->vtodo(user: 'alice', taskId: 't3'));
		$this->assertSame([['principals/users/alice', 'planninq', ['components' => 'VTODO', '{DAV:}displayname' => 'Planninq']]], $this->backend->created);
		$this->assertStringContainsString('SUMMARY:Export to CSV', (string)$this->vtodo(user: 'alice', taskId: 't1'));
		$this->assertStringContainsString('SUMMARY:Import from CSV', (string)$this->vtodo(user: 'alice', taskId: 't2'));
		$this->assertStringContainsString('CATEGORIES:Project p1', (string)$this->vtodo(user: 'alice', taskId: 't1'));
		$this->assertStringContainsString('URL:https://nc.example/index.php/apps/planninq/projects/p1/tasks/t1', (string)$this->vtodo(user: 'alice', taskId: 't1'));
	}//end testSwitchOnExportsExistingTasks()

	/**
	 * Switching off deletes the list; the backfill of a switched-off user writes nothing.
	 *
	 * @return void
	 */
	public function testSwitchOffRemovesTheList(): void {
		$export = $this->makeExport(on: ['alice'], assigned: ['t1' => self::TASK]);
		$export->exportTask(taskId: 't1', task: self::TASK, users: ['alice']);
		$this->assertNotNull($this->vtodo(user: 'alice', taskId: 't1'));

		$export->apply(userId: 'alice', data: [TaskCalendarExportService::SWITCH_KEY => false]);
		$this->assertSame([], $this->backend->lists);
		$this->assertFalse($export->isOn(userId: 'alice'));
		$this->assertSame(0, $export->backfill(userId: 'alice'));
		$this->assertSame([], $this->queued);
	}//end testSwitchOffRemovesTheList()

	/**
	 * The first export stores the UID once, as a silent system write the task schema accepts.
	 *
	 * @return void
	 */
	public function testFirstExportStoresUid(): void {
		$export = $this->makeExport(on: ['alice', 'bob']);
		$this->assertSame(2, $export->exportTask(taskId: 't1', task: self::TASK + ['sharedWith' => ['bob']], users: ['alice', 'bob']));

		$this->assertCount(1, $this->objects->saved);
		$saved = $this->objects->saved[0];
		$this->assertSame(['planninq', 'task', 't1', true], [$saved['register'], $saved['schema'], $saved['uuid'], $saved['silent']]);
		$this->assertSame('planninq-task-t1@inst', $saved['object']['calendarEventUid']);
		$this->assertSame([], $this->registerSchemaErrors(slug: 'task', payload: array_merge($saved['object'], ['project' => '00000000-0000-4000-8000-0000000000a1'])));
		$this->assertStringContainsString('UID:planninq-task-t1@inst', (string)$this->vtodo(user: 'bob', taskId: 't1'));

		// Stored already: written again without a second write-back, same UID.
		$export->exportTask(taskId: 't1', task: self::TASK + ['calendarEventUid' => 'planninq-task-t1@inst'], users: ['alice']);
		$this->assertCount(1, $this->objects->saved);
	}//end testFirstExportStoresUid()
}//end class
