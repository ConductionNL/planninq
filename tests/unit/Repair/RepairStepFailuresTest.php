<?php

/**
 * The board-column and members repair steps report a failure as a warning
 * with its cause, log it, and never throw; each names what it does.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Repair
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

use OCA\Planninq\Repair\AssignBoardColumns;
use OCA\Planninq\Repair\BackfillProjectMembers;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Repair\AssignBoardColumns
 * @covers \OCA\Planninq\Repair\BackfillProjectMembers
 */
class RepairStepFailuresTest extends TestCase {

	/**
	 * An output that records its warnings and refuses info lines.
	 *
	 * @param array<int, string> $warnings Where warnings go.
	 *
	 * @return IOutput
	 */
	private function recordingOutput(array &$warnings): IOutput {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->never())->method('info');
		$output->method('warning')->willReturnCallback(static function (string $message) use (&$warnings): void {
			$warnings[] = $message;
		});

		return $output;
	}//end recordingOutput()

	/**
	 * A membership service that is available and whose full sync fails.
	 *
	 * @return ProjectMembershipService
	 */
	private function membership(): ProjectMembershipService {
		$membership = $this->createMock(originalClassName: ProjectMembershipService::class);
		$membership->method('isAvailable')->willReturn(true);
		$membership->method('syncAll')->willThrowException(new RuntimeException('register locked'));

		return $membership;
	}//end membership()

	/**
	 * A failed column placement warns with its cause and is logged as an error.
	 *
	 * @return void
	 */
	public function testAFailedColumnPlacementWarnsAndLogs(): void {
		$columns = $this->createMock(originalClassName: BoardColumnService::class);
		$columns->method('assignAll')->willThrowException(new RuntimeException('register locked'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			'Planninq: board column placement failed; tasks without a column stay off the board until it runs again',
			['exception' => 'register locked']
		);
		$warnings = [];
		$step     = new AssignBoardColumns(columns: $columns, membership: $this->membership(), logger: $logger);

		$step->run(output: $this->recordingOutput(warnings: $warnings));

		$this->assertSame(expected: ['Could not place Planninq tasks in board columns: register locked'], actual: $warnings);
		$this->assertSame(
			expected: 'Put every Planninq task that has no board column into the column matching its status',
			actual: $step->getName()
		);
	}//end testAFailedColumnPlacementWarnsAndLogs()

	/**
	 * A failed members back-fill warns with its cause and is logged as an error.
	 *
	 * @return void
	 */
	public function testAFailedMembersBackfillWarnsAndLogs(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			'Planninq: members back-fill failed; members may not see existing tasks until it runs again',
			['exception' => 'register locked']
		);
		$warnings = [];
		$step     = new BackfillProjectMembers(membership: $this->membership(), logger: $logger);

		$step->run(output: $this->recordingOutput(warnings: $warnings));

		$this->assertSame(expected: ['Could not fill the Planninq members lists: register locked'], actual: $warnings);
		$this->assertSame(
			expected: 'Fill the members list of Planninq tasks, columns, phases and time entries from their project',
			actual: $step->getName()
		);
	}//end testAFailedMembersBackfillWarnsAndLogs()
}//end class
