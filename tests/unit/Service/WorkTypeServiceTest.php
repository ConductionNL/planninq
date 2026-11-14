<?php

/**
 * Unit tests for WorkTypeService.
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

use OCA\Planninq\BackgroundJob\WorkTypeRenameJob;
use OCA\Planninq\Service\OpenRegisterObjects;
use OCA\Planninq\Service\WorkTypeService;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Queuing a rename of a work type from the submitted admin settings.
 */
#[CoversClass(WorkTypeService::class)]
final class WorkTypeServiceTest extends TestCase {

	/**
	 * The service over a job list.
	 *
	 * @param IJobList $jobs The job list.
	 *
	 * @return WorkTypeService
	 */
	private function service(IJobList $jobs): WorkTypeService {
		return new WorkTypeService(
			jobs: $jobs,
			objects: $this->createMock(originalClassName: OpenRegisterObjects::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * A valid rename is queued once with the trimmed names.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function testAValidRenameIsQueued(): void {
		$jobs = $this->createMock(originalClassName: IJobList::class);
		$jobs->expects($this->exactly(count: 2))->method('add')->with(WorkTypeRenameJob::class, ['from' => 'Meeting', 'to' => 'Overleg']);
		$service = $this->service(jobs: $jobs);

		self::assertTrue(condition: $service->queueRename(data: ['work_type_rename' => ['from' => ' Meeting', 'to' => 'Overleg ']]));
		self::assertTrue(condition: $service->queueRename(data: ['work_type_rename' => '{"from":"Meeting","to":"Overleg"}']));
	}//end testAValidRenameIsQueued()

	/**
	 * No rename, or a malformed one, queues nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
	 */
	public function testNoValidRenameQueuesNothing(): void {
		$jobs = $this->createMock(originalClassName: IJobList::class);
		$jobs->expects($this->never())->method('add');
		$service = $this->service(jobs: $jobs);

		$submitted = [
			[],
			['work_type_rename' => null],
			['work_type_rename' => 'x'],
			['work_type_rename' => ['from' => 'A', 'to' => 'A']],
			['work_type_rename' => ['from' => 'A', 'to' => 3]],
		];
		foreach ($submitted as $data) {
			self::assertFalse(condition: $service->queueRename(data: $data));
		}
	}//end testNoValidRenameQueuesNothing()
}//end class
