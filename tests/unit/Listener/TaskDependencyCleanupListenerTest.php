<?php

/**
 * Tests for TaskDependencyCleanupListener.
 *
 * The cascade these cover is the one gate-57 surfaced: before the listener
 * existed, `DependencyService::removeEdgesForTask()` had no caller, so deleting
 * a task left its dependency edges pointing at nothing.
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

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\TaskDependencyCleanupListener;
use OCA\Planninq\Listener\TaskScopeResolver;
use OCA\Planninq\Service\DependencyService;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Listener\TaskDependencyCleanupListener
 */
class TaskDependencyCleanupListenerTest extends TestCase {

	/**
	 * Build the listener with the supplied doubles.
	 *
	 * @param DependencyService $service Dependency writes.
	 * @param TaskScopeResolver $resolver Scope resolver.
	 *
	 * @return TaskDependencyCleanupListener
	 */
	private function listener(DependencyService $service, TaskScopeResolver $resolver): TaskDependencyCleanupListener {
		return new TaskDependencyCleanupListener(
			dependencyService: $service,
			scopeResolver: $resolver,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

	}//end listener()

	/**
	 * Build a pre-delete event carrying a task-shaped object.
	 *
	 * @param string $uuid The object UUID.
	 *
	 * @return ObjectDeletingEvent
	 */
	private function deletingEvent(string $uuid): ObjectDeletingEvent {
		return new ObjectDeletingEvent($this->task($uuid, []));
	}//end deletingEvent()

	/**
	 * A pre-update event for a task whose data goes from one shape to another.
	 *
	 * @param array<string, mixed> $before The stored task data.
	 * @param array<string, mixed> $after  The task data being written.
	 *
	 * @return ObjectUpdatingEvent
	 */
	private function updatingEvent(array $before, array $after): ObjectUpdatingEvent {
		return new ObjectUpdatingEvent($this->task('task-uuid', $after), $this->task('task-uuid', $before));
	}//end updatingEvent()

	/**
	 * A task-shaped object.
	 *
	 * @param string               $uuid The object UUID.
	 * @param array<string, mixed> $data The object data.
	 *
	 * @return ObjectEntity
	 */
	private function task(string $uuid, array $data): ObjectEntity {
		// ObjectEntity's getRegister/getSchema/getUuid are magic (__call)
		// accessors, so PHPUnit cannot configure them on a mock — the same
		// reason TaskActivityListenerTest builds a concrete subclass here.
		return new class($uuid, $data) extends ObjectEntity {
			// phpcs:disable
			public function __construct(
				private string $u,
				private array $d,
			) {
			}
			public function getObject(): array {
				return $this->d;
			}
			public function getRegister(): ?string {
				return '1';
			}
			public function getSchema(): ?string {
				return '2';
			}
			public function getUuid(): ?string {
				return $this->u;
			}
			// phpcs:enable
		};
	}//end task()

	/**
	 * Deleting a Planninq task cascades to its dependency edges.
	 *
	 * @return void
	 */
	public function testCascadesForAPlanninqTask(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::once())
			->method('removeEdgesForTask')
			->with('task-uuid')
			->willReturn(2);

		$this->listener($service, $resolver)->handle($this->deletingEvent('task-uuid'));

	}//end testCascadesForAPlanninqTask()

	/**
	 * A delete an earlier guard refused keeps the task, so its edges stay too.
	 *
	 * @return void
	 */
	public function testARefusedDeleteKeepsTheEdges(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$event = $this->deletingEvent('task-uuid');
		$event->setErrors(['code' => 'planninq-not-the-reporter']);
		$event->stopPropagation();
		$this->listener($service, $resolver)->handle($event);
	}//end testARefusedDeleteKeepsTheEdges()

	/**
	 * An object from another app is left entirely alone.
	 *
	 * @return void
	 */
	public function testIgnoresObjectsThatAreNotPlanninqTasks(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(false);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$this->listener($service, $resolver)->handle($this->deletingEvent('other-uuid'));

	}//end testIgnoresObjectsThatAreNotPlanninqTasks()

	/**
	 * A cleanup failure must not propagate: the user's delete still proceeds.
	 *
	 * This is the deliberate half of the design — an exception escaping a
	 * `*ing` listener would abort the delete the user asked for.
	 *
	 * @return void
	 */
	public function testASwallowedFailureDoesNotBlockTheDelete(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->method('removeEdgesForTask')->willThrowException(new \RuntimeException('boom'));

		$this->listener($service, $resolver)->handle($this->deletingEvent('task-uuid'));
		$this->addToAssertionCount(1);

	}//end testASwallowedFailureDoesNotBlockTheDelete()

	/**
	 * Moving a task to another project removes its edges in the same write.
	 *
	 * @return void
	 */
	public function testAProjectChangeRemovesTheEdges(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->with(registerId: '1', schemaId: '2')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::exactly(2))
			->method('removeEdgesForTask')
			->with('task-uuid')
			->willReturn(1);

		$listener = $this->listener($service, $resolver);
		$listener->handle($this->updatingEvent(['project' => 'vergunningen', 'title' => 'A'], ['project' => 'handhaving', 'title' => 'A']));
		$listener->handle($this->updatingEvent(['project' => ['id' => 'vergunningen']], ['project' => 'handhaving']));

	}//end testAProjectChangeRemovesTheEdges()

	/**
	 * An update that keeps the project keeps the edges, however the project is written.
	 *
	 * @return void
	 */
	public function testAnUpdateWithinTheProjectKeepsTheEdges(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$listener = $this->listener($service, $resolver);
		$listener->handle($this->updatingEvent(['project' => 'vergunningen', 'title' => 'A'], ['project' => 'vergunningen', 'title' => 'B']));
		$listener->handle($this->updatingEvent(['project' => ['id' => 'vergunningen']], ['project' => 'vergunningen']));
		$listener->handle(new ObjectUpdatingEvent($this->task('task-uuid', ['project' => 'handhaving'])));

	}//end testAnUpdateWithinTheProjectKeepsTheEdges()

	/**
	 * A move an earlier guard refused keeps the task where it is, and its edges.
	 *
	 * @return void
	 */
	public function testARefusedMoveKeepsTheEdges(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$event = $this->updatingEvent(['project' => 'vergunningen'], ['project' => 'handhaving']);
		$event->stopPropagation();
		$this->listener($service, $resolver)->handle($event);

	}//end testARefusedMoveKeepsTheEdges()

	/**
	 * A moved object from another app is left alone.
	 *
	 * @return void
	 */
	public function testAMovedObjectThatIsNotAPlanninqTaskIsIgnored(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanninqTask')->willReturn(false);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$this->listener($service, $resolver)->handle($this->updatingEvent(['project' => 'a'], ['project' => 'b']));

	}//end testAMovedObjectThatIsNotAPlanninqTaskIsIgnored()

	/**
	 * An unrelated event type is ignored without touching the resolver.
	 *
	 * @return void
	 */
	public function testIgnoresUnrelatedEvents(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->expects(self::never())->method('isPlanninqTask');

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$this->listener($service, $resolver)->handle(new Event());

	}//end testIgnoresUnrelatedEvents()

}//end class
