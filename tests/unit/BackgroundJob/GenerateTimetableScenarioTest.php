<?php

/**
 * Unit tests for the queued timetable scenario generation job.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\GenerateTimetableScenario;
use OCA\Planninq\Service\TimetableGenerationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Each run does one generation step and re-queues itself while work is left.
 *
 * @covers \OCA\Planninq\BackgroundJob\GenerateTimetableScenario
 */
class GenerateTimetableScenarioTest extends TestCase {

	/**
	 * A job over the given doubles.
	 *
	 * @param TimetableGenerationService $generation The generation service.
	 * @param IJobList                   $jobList    The job list.
	 *
	 * @return GenerateTimetableScenario The job.
	 */
	private function job(TimetableGenerationService $generation, IJobList $jobList): GenerateTimetableScenario {
		return new GenerateTimetableScenario(time: $this->createMock(originalClassName: ITimeFactory::class), generation: $generation, jobList: $jobList);
	}//end job()

	/**
	 * A missing, empty or non-array argument does nothing and queues nothing.
	 *
	 * @return void
	 */
	public function testNoScenarioDoesNothing(): void {
		$generation = $this->createMock(originalClassName: TimetableGenerationService::class);
		$generation->expects($this->never())->method('step');
		$jobList = $this->createMock(originalClassName: IJobList::class);
		$jobList->expects($this->never())->method('add');
		$job = $this->job(generation: $generation, jobList: $jobList);

		$this->assertFalse(condition: $job->runStep(argument: null));
		$this->assertFalse(condition: $job->runStep(argument: 's-1'));
		$this->assertFalse(condition: $job->runStep(argument: []));
		$this->assertFalse(condition: $job->runStep(argument: ['scenario' => '']));
	}//end testNoScenarioDoesNothing()

	/**
	 * A finished scenario is not queued again.
	 *
	 * @return void
	 */
	public function testAFinishedScenarioIsNotRequeued(): void {
		$generation = $this->createMock(originalClassName: TimetableGenerationService::class);
		$generation->expects($this->once())->method('step')->with('s-9')->willReturn(false);
		$jobList = $this->createMock(originalClassName: IJobList::class);
		$jobList->expects($this->never())->method('add');

		$this->assertFalse(condition: $this->job(generation: $generation, jobList: $jobList)->runStep(argument: ['scenario' => 's-9']));
	}//end testAFinishedScenarioIsNotRequeued()

	/**
	 * The scheduler's run() does the step and re-queues with a string id.
	 *
	 * @return void
	 */
	public function testRunDoesAStepAndRequeues(): void {
		$generation = $this->createMock(originalClassName: TimetableGenerationService::class);
		$generation->expects($this->once())->method('step')->with('42')->willReturn(true);
		$jobList = $this->createMock(originalClassName: IJobList::class);
		$jobList->expects($this->once())->method('add')->with(GenerateTimetableScenario::class, ['scenario' => '42']);

		$run = new ReflectionMethod(GenerateTimetableScenario::class, 'run');
		$run->invoke($this->job(generation: $generation, jobList: $jobList), ['scenario' => 42]);
	}//end testRunDoesAStepAndRequeues()
}//end class
