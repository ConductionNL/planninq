<?php

/**
 * Unit tests for the planninq:workflow:resync command.
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Command;

use OCA\Planninq\Command\WorkflowResync;
use OCA\Planninq\Service\WorkflowSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \OCA\Planninq\Command\WorkflowResync
 */
class WorkflowResyncTest extends TestCase {

	/**
	 * The command resyncs the named workflow and exits 0 when every project is in step.
	 */
	public function testResyncsTheWorkflow(): void {
		$sync = $this->createMock(originalClassName: WorkflowSyncService::class);
		$sync->expects(self::once())->method('syncWorkflow')->with(workflowId: 'wf-1')->willReturn(['synced' => 3, 'failed' => []]);

		$tester = new CommandTester(new WorkflowResync(sync: $sync));
		$status = $tester->execute(['workflow' => 'wf-1']);

		self::assertSame(0, $status);
		self::assertStringContainsString('Projects in step: 3', $tester->getDisplay());
	}//end testResyncsTheWorkflow()

	/**
	 * A project that fails is named with its reason and the exit code is 1.
	 */
	public function testNamesTheProjectsThatFailed(): void {
		$sync = $this->createMock(originalClassName: WorkflowSyncService::class);
		$sync->method('syncWorkflow')->willReturn(['synced' => 1, 'failed' => ['p-9' => 'boom']]);

		$tester = new CommandTester(new WorkflowResync(sync: $sync));

		self::assertSame(1, $tester->execute(['workflow' => 'wf-1']));
		self::assertStringContainsString('Project p-9 failed: boom', $tester->getDisplay());
	}//end testNamesTheProjectsThatFailed()
}//end class
