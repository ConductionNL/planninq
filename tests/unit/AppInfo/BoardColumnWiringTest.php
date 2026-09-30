<?php

/**
 * Wiring test for the board-column listeners, asserted from the caller
 * (Application::boot's registration), so a listener with green tests and no
 * subscription cannot pass.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Event {
	if (class_exists(ObjectEventSubscription::class) === false) {
		/**
		 * Records subscriptions; same static signature as OpenRegister's
		 * lib/Event/ObjectEventSubscription.php subscribe().
		 */
		class ObjectEventSubscription {
			/** @var array<int,array<string,mixed>> */
			public static array $calls = [];
			public static function subscribe(
				\OCP\EventDispatcher\IEventDispatcher $dispatcher,
				string $event,
				string $listener,
				?array $registers = null,
				?array $schemas = null,
			): void {
				self::$calls[] = ['event' => $event, 'listener' => $listener, 'registers' => $registers, 'schemas' => $schemas];
			}
		}
	}
}

namespace OCA\Planninq\Tests\Unit\AppInfo {

	use OCA\OpenRegister\Event\ObjectEventSubscription;
	use OCA\Planninq\AppInfo\Application;
	use OCP\EventDispatcher\IEventDispatcher;
	use PHPUnit\Framework\TestCase;

	class BoardColumnWiringTest extends TestCase {

		public function testBootSubscribesBothBoardColumnListeners(): void {
			ObjectEventSubscription::$calls = [];
			$app    = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			$method = new \ReflectionMethod(Application::class, 'registerBoardColumnListeners');
			$method->invoke($app, $this->createMock(originalClassName: IEventDispatcher::class));

			$byListener = [];
			foreach (ObjectEventSubscription::$calls as $call) {
				self::assertTrue(class_exists($call['listener']), $call['listener'] . ' must exist');
				self::assertTrue(class_exists($call['event']), $call['event'] . ' must exist');
				$byListener[$call['listener']][] = [substr($call['event'], strrpos($call['event'], '\\') + 1), $call['schemas']];
			}

			self::assertSame(
				[['ObjectCreatingEvent', ['task']], ['ObjectUpdatingEvent', ['task']]],
				$byListener['OCA\\Planninq\\Listener\\TaskCompletionListener']
			);
			self::assertSame(
				[['ObjectCreatingEvent', ['column']], ['ObjectUpdatingEvent', ['column']], ['ObjectDeletingEvent', ['column']]],
				$byListener['OCA\\Planninq\\Listener\\ColumnOwnerGuardListener']
			);
			self::assertSame(
				[['ObjectCreatingEvent', ['task']], ['ObjectUpdatingEvent', ['task']]],
				($byListener['OCA\\Planninq\\Listener\\ColumnAutomationListener'] ?? null),
				'column rules run on task creates and updates (boards-column-automation task 1.2)'
			);

			$boot = (string)file_get_contents(__DIR__ . '/../../../lib/AppInfo/Application.php');
			self::assertStringContainsString('$this->registerBoardColumnListeners(dispatcher: $dispatcher);', $boot, 'boot() calls the registration');
		}//end testBootSubscribesBothBoardColumnListeners()

		/**
		 * The key listener is subscribed live for project and task writes, and boot() calls it.
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-2.2
		 */
		/**
		 * The task reporter guard is subscribed for task creates, updates and deletes,
		 * and before the dependency cleanup, so a refused delete leaves the links alone.
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-5.1
		 */
		public function testBootSubscribesTheTaskReporterGuardBeforeTheDependencyCleanup(): void {
			ObjectEventSubscription::$calls = [];
			$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			(new \ReflectionMethod(Application::class, 'registerTaskGuardListeners'))->invoke($app, $this->createMock(originalClassName: IEventDispatcher::class));

			$calls = [];
			foreach (ObjectEventSubscription::$calls as $call) {
				self::assertTrue(class_exists($call['listener']), $call['listener'] . ' must exist');
				self::assertTrue(class_exists($call['event']), $call['event'] . ' must exist');
				$calls[] = [$call['listener'], substr($call['event'], strrpos($call['event'], '\\') + 1), $call['schemas']];
			}

			$guard = 'OCA\\Planninq\\Listener\\TaskReporterGuardListener';
			self::assertSame(
				[
					[$guard, 'ObjectCreatingEvent', ['task']],
					[$guard, 'ObjectUpdatingEvent', ['task']],
					[$guard, 'ObjectDeletingEvent', ['task']],
				],
				$calls
			);

			$boot    = (string)file_get_contents(__DIR__ . '/../../../lib/AppInfo/Application.php');
			$guardAt = strpos($boot, '$this->registerTaskGuardListeners(dispatcher: $dispatcher);');
			self::assertNotFalse($guardAt, 'boot() calls the registration');
			self::assertLessThan(strpos($boot, "listener: 'OCA\\\\Planninq\\\\Listener\\\\TaskDependencyCleanupListener'"), $guardAt, 'the guard runs before the cleanup');
		}//end testBootSubscribesTheTaskReporterGuardBeforeTheDependencyCleanup()

		public function testBootSubscribesTheWorkItemKeyListener(): void {
			ObjectEventSubscription::$calls = [];
			$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			(new \ReflectionMethod(Application::class, 'registerWorkItemKeyListeners'))->invoke($app, $this->createMock(originalClassName: IEventDispatcher::class));

			$calls = [];
			foreach (ObjectEventSubscription::$calls as $call) {
				self::assertTrue(class_exists($call['listener']), $call['listener'] . ' must exist');
				self::assertTrue(class_exists($call['event']), $call['event'] . ' must exist');
				$calls[] = [$call['listener'], substr($call['event'], strrpos($call['event'], '\\') + 1), $call['schemas']];
			}

			$listener = 'OCA\\Planninq\\Listener\\WorkItemKeyListener';
			$review = 'OCA\\Planninq\\Listener\\ProjectReviewListener';
			self::assertSame(
				[
					[$listener, 'ObjectCreatingEvent', ['project', 'task']],
					[$listener, 'ObjectUpdatingEvent', ['project', 'task']],
					[$review, 'ObjectUpdatingEvent', ['project']],
					[$review, 'ObjectUpdatedEvent', ['project']],
				],
				$calls
			);

			$boot = (string)file_get_contents(__DIR__ . '/../../../lib/AppInfo/Application.php');
			self::assertStringContainsString('$this->registerWorkItemKeyListeners(dispatcher: $dispatcher);', $boot);
		}//end testBootSubscribesTheWorkItemKeyListener()
		/**
		 * Every project-scoped schema is stamped and gated live, not only in the listener's own list.
		 *
		 * risk and projectLogEntry were in ProjectMembershipService::SCOPED_SCHEMAS
		 * but not in this subscription, so on a live instance their members list
		 * stayed empty and members could not read what they wrote.
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.2
		 */
		public function testBootSubscribesTheMembershipAndStatusListenersForEveryScopedSchema(): void {
			ObjectEventSubscription::$calls = [];
			$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			(new \ReflectionMethod(Application::class, 'registerMembershipListeners'))->invoke($app, $this->createMock(originalClassName: IEventDispatcher::class));

			$byListener = [];
			foreach (ObjectEventSubscription::$calls as $call) {
				self::assertTrue(class_exists($call['listener']), $call['listener'] . ' must exist');
				$byListener[$call['listener']][] = [substr($call['event'], strrpos($call['event'], '\\') + 1), $call['schemas']];
			}

			$scoped = \OCA\Planninq\Service\ProjectMembershipService::SCOPED_SCHEMAS;
			self::assertSame(
				[['ObjectCreatingEvent', $scoped], ['ObjectUpdatingEvent', $scoped]],
				$byListener['OCA\\Planninq\\Listener\\ProjectMemberAccessListener']
			);
			self::assertContains('projectStatusReport', $scoped);

			$status = ['project', 'projectStatusReport'];
			self::assertSame(
				[['ObjectCreatingEvent', $status], ['ObjectUpdatingEvent', $status], ['ObjectDeletingEvent', $status]],
				$byListener['OCA\\Planninq\\Listener\\ProjectStatusListener']
			);

			$hierarchy = ['project', 'projectPortfolio'];
			self::assertSame(
				[['ObjectCreatingEvent', $hierarchy], ['ObjectUpdatingEvent', $hierarchy], ['ObjectDeletingEvent', $hierarchy]],
				$byListener['OCA\\Planninq\\Listener\\ProjectHierarchyGuardListener']
			);

			// portfolio-finance task 1.1: finance lines are guarded and stamped on every write.
			$finance = ['financeLine'];
			self::assertSame(
				[['ObjectCreatingEvent', $finance], ['ObjectUpdatingEvent', $finance], ['ObjectDeletingEvent', $finance]],
				$byListener['OCA\\Planninq\\Listener\\FinanceLineListener'] ?? null
			);
			self::assertNotContains('financeLine', $scoped, 'members must not be stamped onto money');
		}//end testBootSubscribesTheMembershipAndStatusListenersForEveryScopedSchema()
	}//end class
}
