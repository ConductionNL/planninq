<?php

/**
 * Unit tests for the queued task calendar backfill job.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\TaskCalendarBackfillJob;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The job backfills the calendar of the user named in its argument.
 *
 * @covers \OCA\Planninq\BackgroundJob\TaskCalendarBackfillJob
 */
class TaskCalendarBackfillJobTest extends TestCase {

	/**
	 * A missing or non-array argument backfills with an empty user id, which the service refuses.
	 *
	 * @return void
	 */
	public function testNoUserIsPassedAsEmpty(): void {
		$export = $this->createMock(originalClassName: TaskCalendarExportService::class);
		$export->expects($this->exactly(count: 2))->method('backfill')->with('')->willReturn(0);
		$job = new TaskCalendarBackfillJob(time: $this->createMock(originalClassName: ITimeFactory::class), export: $export);

		$this->assertSame(expected: 0, actual: $job->backfill(argument: null));
		$this->assertSame(expected: 0, actual: $job->backfill(argument: ['other' => 'x']));
	}//end testNoUserIsPassedAsEmpty()

	/**
	 * The scheduler's run() backfills the named user.
	 *
	 * @return void
	 */
	public function testRunBackfillsTheUser(): void {
		$export = $this->createMock(originalClassName: TaskCalendarExportService::class);
		$export->expects($this->once())->method('backfill')->with('alice')->willReturn(3);
		$job = new TaskCalendarBackfillJob(time: $this->createMock(originalClassName: ITimeFactory::class), export: $export);

		(new ReflectionMethod(TaskCalendarBackfillJob::class, 'run'))->invoke($job, ['userId' => 'alice']);
	}//end testRunBackfillsTheUser()
}//end class
