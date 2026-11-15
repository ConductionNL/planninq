<?php

/**
 * Tests for the planninq:workflow:resync command.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Command
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

namespace OCA\Planninq\Tests\Unit\Command;

use OCA\Planninq\Command\ResyncWorkflow;
use OCA\Planninq\Service\WorkflowColumnSync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command syncs the named workflow, prints the totals and exits 1 when a project failed.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
#[CoversClass(ResyncWorkflow::class)]
final class ResyncWorkflowTest extends TestCase {

	/**
	 * The command's name, description and required workflow argument.
	 *
	 * @return void
	 */
	public function testItIsRegisteredWithARequiredWorkflowArgument(): void {
		$command = new ResyncWorkflow(sync: $this->createMock(WorkflowColumnSync::class));

		$this->assertSame('planninq:workflow:resync', $command->getName());
		$this->assertSame('Bring the columns of every project on a workflow in step with it again.', $command->getDescription());
		$argument = $command->getDefinition()->getArgument('workflow');
		$this->assertTrue($argument->isRequired());
		$this->assertSame('The workflow uuid.', $argument->getDescription());
	}//end testItIsRegisteredWithARequiredWorkflowArgument()

	/**
	 * A sync where every project updated exits 0 with the totals.
	 *
	 * @return void
	 */
	public function testASuccessfulSyncPrintsTheTotals(): void {
		$sync = $this->createMock(WorkflowColumnSync::class);
		$sync->expects($this->once())->method('syncWorkflow')->with('wf-1')
			->willReturn(['projects' => 3, 'failed' => 0, 'moved' => 4]);
		$tester = new CommandTester(new ResyncWorkflow(sync: $sync));

		$this->assertSame(0, $tester->execute(['workflow' => 'wf-1']));
		$this->assertSame(
			'Updated 3 project(s), 0 failed, 4 task(s) moved out of removed columns.' . PHP_EOL,
			$tester->getDisplay()
		);
	}//end testASuccessfulSyncPrintsTheTotals()

	/**
	 * A project that failed makes the command exit 1.
	 *
	 * @return void
	 */
	public function testAFailedProjectExitsOne(): void {
		$sync = $this->createMock(WorkflowColumnSync::class);
		$sync->method('syncWorkflow')->willReturn(['projects' => 1, 'failed' => 2, 'moved' => 0]);
		$tester = new CommandTester(new ResyncWorkflow(sync: $sync));

		$this->assertSame(1, $tester->execute(['workflow' => 'wf-2']));
		$this->assertSame(
			'Updated 1 project(s), 2 failed, 0 task(s) moved out of removed columns.' . PHP_EOL,
			$tester->getDisplay()
		);
	}//end testAFailedProjectExitsOne()

	/**
	 * Without a workflow argument the command refuses before syncing anything.
	 *
	 * @return void
	 */
	public function testAMissingWorkflowIsRefused(): void {
		$sync = $this->createMock(WorkflowColumnSync::class);
		$sync->expects($this->never())->method('syncWorkflow');
		$tester = new CommandTester(new ResyncWorkflow(sync: $sync));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Not enough arguments (missing: "workflow").');
		$tester->execute([]);
	}//end testAMissingWorkflowIsRefused()
}//end class
