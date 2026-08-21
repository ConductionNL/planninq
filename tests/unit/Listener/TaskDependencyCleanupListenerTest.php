<?php

/**
 * Tests for TaskDependencyCleanupListener.
 *
 * The cascade these cover is the one gate-57 surfaced: before the listener
 * existed, `DependencyService::removeEdgesForTask()` had no caller, so deleting
 * a task left its dependency edges pointing at nothing.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Listener
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

namespace OCA\Planix\Tests\Unit\Listener;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\Planix\Listener\TaskDependencyCleanupListener;
use OCA\Planix\Listener\TaskScopeResolver;
use OCA\Planix\Service\DependencyService;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planix\Listener\TaskDependencyCleanupListener
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
		// ObjectEntity's getRegister/getSchema/getUuid are magic (__call)
		// accessors, so PHPUnit cannot configure them on a mock — the same
		// reason TaskActivityListenerTest builds a concrete subclass here.
		$object = new class($uuid) extends ObjectEntity {
			// phpcs:disable
			public function __construct(
				private string $u,
			) {
			}
			public function getObject(): array {
				return [];
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

		return new ObjectDeletingEvent($object);
	}//end deletingEvent()

	/**
	 * Deleting a planix task cascades to its dependency edges.
	 *
	 * @return void
	 */
	public function testCascadesForAPlanixTask(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanixTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::once())
			->method('removeEdgesForTask')
			->with('task-uuid')
			->willReturn(2);

		$this->listener($service, $resolver)->handle($this->deletingEvent('task-uuid'));

	}//end testCascadesForAPlanixTask()

	/**
	 * An object from another app is left entirely alone.
	 *
	 * @return void
	 */
	public function testIgnoresObjectsThatAreNotPlanixTasks(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->method('isPlanixTask')->willReturn(false);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$this->listener($service, $resolver)->handle($this->deletingEvent('other-uuid'));

	}//end testIgnoresObjectsThatAreNotPlanixTasks()

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
		$resolver->method('isPlanixTask')->willReturn(true);

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->method('removeEdgesForTask')->willThrowException(new \RuntimeException('boom'));

		$this->listener($service, $resolver)->handle($this->deletingEvent('task-uuid'));
		$this->addToAssertionCount(1);

	}//end testASwallowedFailureDoesNotBlockTheDelete()

	/**
	 * An unrelated event type is ignored without touching the resolver.
	 *
	 * @return void
	 */
	public function testIgnoresUnrelatedEvents(): void {
		$resolver = $this->createMock(originalClassName: TaskScopeResolver::class);
		$resolver->expects(self::never())->method('isPlanixTask');

		$service = $this->createMock(originalClassName: DependencyService::class);
		$service->expects(self::never())->method('removeEdgesForTask');

		$this->listener($service, $resolver)->handle(new Event());

	}//end testIgnoresUnrelatedEvents()

}//end class
