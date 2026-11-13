<?php

/**
 * Unit tests for the queued project task numbering job.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\NumberProjectTasks;
use OCA\Planninq\Service\WorkItemKeyService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The job numbers the tasks of the project named in its argument.
 *
 * @covers \OCA\Planninq\BackgroundJob\NumberProjectTasks
 */
class NumberProjectTasksTest extends TestCase {

	/**
	 * Without a project id nothing is numbered.
	 *
	 * @return void
	 */
	public function testNoProjectNumbersNothing(): void {
		$keys = $this->createMock(originalClassName: WorkItemKeyService::class);
		$keys->expects($this->never())->method('numberTasks');
		$job = new NumberProjectTasks(time: $this->createMock(originalClassName: ITimeFactory::class), keys: $keys);

		$this->assertSame(expected: 0, actual: $job->numberProject(argument: null));
		$this->assertSame(expected: 0, actual: $job->numberProject(argument: 'p-1'));
		$this->assertSame(expected: 0, actual: $job->numberProject(argument: ['project' => '']));
	}//end testNoProjectNumbersNothing()

	/**
	 * The project id is passed as a string and the count returned.
	 *
	 * @return void
	 */
	public function testTheProjectIsNumbered(): void {
		$keys = $this->createMock(originalClassName: WorkItemKeyService::class);
		$keys->expects($this->once())->method('numberTasks')->with('7')->willReturn(5);
		$job = new NumberProjectTasks(time: $this->createMock(originalClassName: ITimeFactory::class), keys: $keys);

		$this->assertSame(expected: 5, actual: $job->numberProject(argument: ['project' => 7]));
	}//end testTheProjectIsNumbered()

	/**
	 * The scheduler's run() numbers the project.
	 *
	 * @return void
	 */
	public function testRunNumbersTheProject(): void {
		$keys = $this->createMock(originalClassName: WorkItemKeyService::class);
		$keys->expects($this->once())->method('numberTasks')->with('p-2')->willReturn(1);
		$job = new NumberProjectTasks(time: $this->createMock(originalClassName: ITimeFactory::class), keys: $keys);

		(new ReflectionMethod(NumberProjectTasks::class, 'run'))->invoke($job, ['project' => 'p-2']);
	}//end testRunNumbersTheProject()
}//end class
