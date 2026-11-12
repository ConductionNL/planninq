<?php

/**
 * Golden test for the object-event subscriptions Application registers in
 * boot(): per event, the listeners in subscription order with their register
 * and schema filters, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
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
 *
 * @spec exclude Golden test of the listener registration in Application::boot(); each listener's spec covers what it does.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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

			/**
			 * Record one subscription.
			 *
			 * @param \OCP\EventDispatcher\IEventDispatcher $dispatcher The dispatcher.
			 * @param string $event The event class.
			 * @param string $listener The listener class.
			 * @param array<int,string>|null $registers The register filter.
			 * @param array<int,string>|null $schemas The schema filter.
			 *
			 * @return void
			 */
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

	/**
	 * @coversNothing
	 */
	class ObjectListenerTableGoldenTest extends TestCase {

		private const FIXTURE = __DIR__ . '/../../fixtures/objectListenerTable.golden.json';

		/**
		 * The four registration steps of boot(), each on its own and all four in
		 * boot's order, recorded per event.
		 *
		 * @return void
		 */
		public function testSubscriptionsMatchTheRecording(): void {
			$steps = ['registerTaskGuardListeners', 'registerMembershipListeners', 'registerBoardColumnListeners', 'registerWorkItemKeyListeners'];
			$actual = [];
			foreach ($steps as $step) {
				$actual[$step] = $this->record(steps: [$step]);
			}

			$actual['boot order'] = $this->record(steps: $steps);
			$actual['boot()']     = $this->recordBoot();

			$json = json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";
			if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
				file_put_contents(self::FIXTURE, $json);
			}

			self::assertSame(expected: file_get_contents(self::FIXTURE), actual: $json);
		}//end testSubscriptionsMatchTheRecording()

		/**
		 * Run registration steps on a fresh record and group what they subscribed by event.
		 *
		 * @param array<int,string> $steps The private Application methods to run, in order.
		 *
		 * @return array<string,array<int,array<string,mixed>>>
		 */
		private function record(array $steps): array {
			$this->resetRecord();
			$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
			foreach ($steps as $step) {
				(new \ReflectionMethod(Application::class, $step))->invoke($app, $this->createMock(originalClassName: IEventDispatcher::class));
			}

			return $this->byEvent();
		}//end record()

		/**
		 * Empty the subscription record.
		 *
		 * @return void
		 */
		private function resetRecord(): void {
			if (property_exists(ObjectEventSubscription::class, 'calls') === true) {
				ObjectEventSubscription::$calls = [];
				return;
			}

			ObjectEventSubscription::reset();
		}//end resetRecord()

		/**
		 * The recorded subscriptions grouped by event.
		 *
		 * @return array<string,array<int,array<string,mixed>>>
		 */
		private function byEvent(): array {
			$byEvent = [];
			foreach ($this->rows() as $row) {
				$byEvent[$row['event']][] = ['listener' => $row['listener'], 'registers' => $row['registers'], 'schemas' => $row['schemas']];
			}

			ksort($byEvent);
			return $byEvent;
		}//end byEvent()

		/**
		 * Run boot() on a fresh record and group what it subscribed by event.
		 *
		 * @return array<string,array<int,array<string,mixed>>>
		 */
		private function recordBoot(): array {
			$this->resetRecord();
			// The declared return type differs per Nextcloud version.
			$serverType = (new \ReflectionMethod(\OCP\AppFramework\Bootstrap\IBootContext::class, 'getServerContainer'))->getReturnType();
			$server     = $this->createMock(originalClassName: (string)$serverType);
			$server->method('get')->willReturn($this->createMock(originalClassName: IEventDispatcher::class));
			$context = $this->createMock(originalClassName: \OCP\AppFramework\Bootstrap\IBootContext::class);
			$context->method('getServerContainer')->willReturn($server);
			(new \ReflectionClass(Application::class))->newInstanceWithoutConstructor()->boot($context);
			return $this->byEvent();
		}//end recordBoot()

		/**
		 * The recorded subscriptions, on the stub or on OpenRegister's own registry.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		private function rows(): array {
			if (property_exists(ObjectEventSubscription::class, 'calls') === true) {
				return ObjectEventSubscription::$calls;
			}

			$rows = [];
			foreach (array_keys(ObjectEventSubscription::subscribedListeners()) as $event) {
				foreach (ObjectEventSubscription::subscriptionsFor($event) as $entry) {
					$rows[] = ['event' => $event, 'listener' => $entry['listener'], 'registers' => $entry['registers'], 'schemas' => $entry['schemas']];
				}
			}

			return $rows;
		}//end rows()
	}//end class
}
