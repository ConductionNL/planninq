<?php

/**
 * Edge cases of the workflow column sync listener.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Listener
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

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Listener\WorkflowColumnSyncListener;
use OCA\Planninq\Service\WorkflowColumnSync;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A created workflow syncs, a failing sync is logged not thrown, and events the
 * listener does not handle leave everything alone.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/specs/shared-workflow/spec.md#requirement-an-admin-defines-workflows-that-projects-share
 */
#[CoversClass(WorkflowColumnSyncListener::class)]
final class WorkflowColumnSyncListenerEdgeTest extends TestCase {

	/**
	 * The sync double.
	 *
	 * @var WorkflowColumnSync&MockObject
	 */
	private WorkflowColumnSync $sync;

	/**
	 * The logger double.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface $logger;

	/**
	 * Build the listener around doubles; schema '7' is the workflow, '8' the project.
	 *
	 * @return WorkflowColumnSyncListener
	 */
	private function listener(): WorkflowColumnSyncListener {
		$this->sync   = $this->createMock(WorkflowColumnSync::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$resolver     = $this->createMock(TaskScopeResolver::class);
		$resolver->method('planninqSchemaSlug')->willReturnCallback(
			static fn (string $registerId, string $schemaId): string => match ($schemaId) {
				'7' => 'workflow',
				'8' => 'project',
				default => 'task',
			}
		);

		return new WorkflowColumnSyncListener(sync: $this->sync, scopeResolver: $resolver, logger: $this->logger);
	}//end listener()

	/**
	 * A newly created workflow always syncs its projects.
	 *
	 * @return void
	 */
	public function testACreatedWorkflowSyncs(): void {
		$listener = $this->listener();
		$this->sync->expects($this->once())->method('syncWorkflow')->with('wf-new')
			->willReturn(['projects' => 0, 'failed' => 0, 'moved' => 0]);
		$this->sync->expects($this->never())->method('syncProjectOnto');

		$listener->handle(new ObjectCreatedEvent(InMemoryObjectService::entity(uuid: 'wf-new', data: ['columns' => []], register: '1', schema: '7')));
	}//end testACreatedWorkflowSyncs()

	/**
	 * A created project on a workflow is synced onto it.
	 *
	 * @return void
	 */
	public function testACreatedProjectIsSyncedOntoItsWorkflow(): void {
		$listener = $this->listener();
		$this->sync->expects($this->never())->method('syncWorkflow');
		$this->sync->expects($this->once())->method('syncProjectOnto')->with('p-new', 'wf-1')->willReturn(3);

		$listener->handle(new ObjectCreatedEvent(InMemoryObjectService::entity(uuid: 'p-new', data: ['workflow' => 'wf-1'], register: '1', schema: '8')));
	}//end testACreatedProjectIsSyncedOntoItsWorkflow()

	/**
	 * A sync that throws is logged with its message and does not escape the listener.
	 *
	 * @return void
	 */
	public function testAFailingSyncIsLoggedNotThrown(): void {
		$listener = $this->listener();
		$this->sync->method('syncWorkflow')->willThrowException(new \RuntimeException('register offline'));
		$this->logger->expects($this->once())->method('error')
			->with('Planninq: could not sync workflow columns', ['exception' => 'register offline']);

		$listener->handle(new ObjectCreatedEvent(InMemoryObjectService::entity(uuid: 'wf-new', data: [], register: '1', schema: '7')));
	}//end testAFailingSyncIsLoggedNotThrown()

	/**
	 * An event the listener does not handle reaches neither the sync nor the logger.
	 *
	 * @return void
	 */
	public function testAnUnhandledEventIsIgnored(): void {
		$listener = $this->listener();
		$this->sync->expects($this->never())->method($this->anything());
		$this->logger->expects($this->never())->method($this->anything());

		$listener->handle(new Event());
	}//end testAnUnhandledEventIsIgnored()

	/**
	 * Deleting something that is not a workflow is not checked and not vetoed.
	 *
	 * @return void
	 */
	public function testDeletingANonWorkflowIsNotChecked(): void {
		$listener = $this->listener();
		$this->sync->expects($this->never())->method('projectsOn');
		$event = new ObjectDeletingEvent(InMemoryObjectService::entity(uuid: 'p-1', data: [], register: '1', schema: '8'));

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
		$this->assertSame([], $event->getErrors());
	}//end testDeletingANonWorkflowIsNotChecked()
}//end class
