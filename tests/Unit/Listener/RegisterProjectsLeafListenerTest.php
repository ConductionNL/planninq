<?php

namespace Unit\Listener;

use OCA\OpenRegister\Event\RegisterLeafProvidersEvent;
use OCA\OpenRegister\Service\Integration\LeafDescriptor;
use OCA\Planninq\Listener\RegisterProjectsLeafListener;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * ADR-066 — the server half of the `planninq-projects` leaf.
 *
 * The two halves of a leaf are bound by a shared id and a set of fields that
 * must match exactly. scripts/check-integration-parity.js compares them by
 * reading both files, so what is left for a unit test is the behaviour that
 * static comparison cannot see: that the descriptor is actually contributed,
 * that it is contributed with no provider (render-and-read only, ADR-066
 * decision 2), and that a failure in here costs only this leaf.
 */
class RegisterProjectsLeafListenerTest extends TestCase {
	private IL10N $l10n;
	private LoggerInterface $logger;

	/**
	 * Every warning the listener logged during a test.
	 *
	 * @var array<int, string>
	 */
	private array $warnings = [];

	protected function setUp(): void {
		$this->warnings = [];
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->logger->method('warning')->willReturnCallback(
			function (string|\Stringable $message): void {
				$this->warnings[] = (string) $message;
			}
		);
	}

	/**
	 * What the listener said when it decided not to register its leaf.
	 *
	 * @return string The recorded warnings, or a note that there were none.
	 */
	private function swallowed(): string {
		if ($this->warnings === []) {
			return 'The listener logged no warning, so it did not throw: the leaf was never contributed.';
		}

		return 'The listener swallowed: ' . implode(' | ', $this->warnings);
	}

	private function listener(): RegisterProjectsLeafListener {
		return new RegisterProjectsLeafListener($this->l10n, $this->logger);
	}

	public function testContributesExactlyOneLeaf(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		// The listener swallows a Throwable so one bad leaf cannot take the
		// catalogue down, and with a plain mock logger that swallow is also
		// what hides the reason: on 2026-09-18 this line read "actual size 0
		// matches expected size 1" in all six PHPUnit cells for three days
		// while the reason, an Error naming a constant, was in a warning
		// nobody captured. The recorded warning is the message now.
		$this->assertCount(1, $event->getLeaves(), $this->swallowed());
	}

	public function testTheDescriptorCarriesTheSharedId(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$descriptor = $event->getLeaves()[0]['descriptor'];
		$this->assertSame('planninq-projects', $descriptor->getId());
		$this->assertSame(RegisterProjectsLeafListener::LEAF_ID, $descriptor->getId());
	}

	/**
	 * ADR-066 decision 2: a leaf is render-and-read, so it exposes no provider.
	 *
	 * A provider here would be an app-local store OpenRegister calls into, which
	 * is exactly the cross-app command channel the ADR keeps closed.
	 */
	public function testContributesNoProvider(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$this->assertNull($event->getLeaves()[0]['provider']);
		$this->assertSame(
			[LeafDescriptor::KIND_RENDER_SURFACE],
			$event->getLeaves()[0]['descriptor']->getKinds()
		);
	}

	/**
	 * The leaf cannot render where planninq is absent, which is the whole reason
	 * it exists rather than a consuming app querying planninq's register.
	 */
	public function testTheDescriptorRequiresPlanninq(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$this->assertSame('planninq', $event->getLeaves()[0]['descriptor']->getRequiredApp());
	}

	/**
	 * A Vue 3 leaf under a possibly Vue 2.7 host renders blank unless both halves
	 * agree on the mount hand-off, and the failure is silent.
	 */
	public function testTheDescriptorDeclaresTheMountRenderMode(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$this->assertSame(
			LeafDescriptor::RENDER_MODE_MOUNT,
			$event->getLeaves()[0]['descriptor']->getRenderMode()
		);
	}

	/**
	 * AD-18: this marker is what turns shillinq's `projectId` from a bare uuid
	 * into the project itself.
	 *
	 * It MUST be the integration id. PropertyReferenceTypeValidator resolves a
	 * property's marker through IntegrationRegistry::isValidIntegrationId() and
	 * throws on a miss, so a loose semantic word like 'project' is a schema that
	 * fails to import the day that validator is wired into the import path.
	 */
	public function testTheReferenceTypeIsTheIntegrationId(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$descriptor = $event->getLeaves()[0]['descriptor'];
		$this->assertSame('planninq-projects', $descriptor->getReferenceType());
		$this->assertSame($descriptor->getId(), $descriptor->getReferenceType());
	}

	/**
	 * Written out on both halves rather than left to a default — a set the gate
	 * can compare against the JS half.
	 */
	public function testTheDescriptorWritesItsSurfacesOut(): void {
		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$this->assertSame(
			['user-dashboard', 'app-dashboard', 'detail-page', 'single-entity'],
			$event->getLeaves()[0]['descriptor']->getSurfaces()
		);
	}

	/**
	 * The listener is subscribed by event NAME, so nothing stops another event
	 * reaching it. It must not assume the type it was registered for.
	 */
	public function testIgnoresAnEventItDoesNotUnderstand(): void {
		$this->logger->expects($this->never())->method('warning');

		$this->listener()->handle(new Event());

		$this->addToAssertionCount(1);
	}

	/**
	 * openregister#3956 added the load strategy; planninq#625 declared it.
	 */
	public function testTheDescriptorClaimsTheSharedEntry(): void {
		$this->requireLoadStrategyAwareOpenRegister();

		$event = new RegisterLeafProvidersEvent();

		$this->listener()->handle($event);

		$this->assertSame(
			LeafDescriptor::LOADS_VIA_SHARED_ENTRY,
			$event->getLeaves()[0]['descriptor']->getLoadStrategy(),
			$this->swallowed()
		);
	}

	/**
	 * 🔴 An OpenRegister that predates the load strategy still gets the leaf.
	 *
	 * Planninq does not choose which OpenRegister it is installed beside, and a
	 * named argument that side does not declare is an `Error` the listener's own
	 * catch then swallows. The leaf would be absent with nothing saying so, and
	 * on 2026-09-18 that reddened all six PHPUnit cells on development.
	 *
	 * The capability check is the seam, because two versions of one class
	 * cannot both be loaded to be compared directly.
	 */
	public function testAnOpenRegisterWithoutTheLoadStrategyStillGetsTheLeaf(): void {
		$this->requireLoadStrategyAwareOpenRegister();

		$listener = new class($this->l10n, $this->logger) extends RegisterProjectsLeafListener {
			protected function descriptorSupportsLoadStrategy(): bool {
				return false;
			}
		};

		$event = new RegisterLeafProvidersEvent();

		$listener->handle($event);

		$this->assertCount(1, $event->getLeaves(), $this->swallowed());
		$this->assertSame('planninq-projects', $event->getLeaves()[0]['descriptor']->getId());
		$this->assertNull($event->getLeaves()[0]['descriptor']->getLoadStrategy());
	}

	/**
	 * Skip when the OpenRegister beside this suite predates openregister#3956.
	 *
	 * These two tests read `getLoadStrategy()`, which is the thing #3956 added.
	 * The suite runs against whichever OpenRegister the workflow installs, and
	 * calling a getter that version does not have would report the sibling app's
	 * age as a planninq failure. The leaf itself is asserted either way, by
	 * testContributesExactlyOneLeaf, which is the behaviour that must hold on
	 * every version.
	 *
	 * @return void
	 */
	private function requireLoadStrategyAwareOpenRegister(): void {
		if (method_exists(LeafDescriptor::class, 'getLoadStrategy') === false) {
			$this->markTestSkipped(
				'The OpenRegister installed beside this suite predates openregister#3956 '
				. '(LeafDescriptor has no getLoadStrategy), so it has no load strategy to read.'
			);
		}
	}

	/**
	 * A throwing leaf must cost only its own leaf, never the catalogue.
	 */
	public function testALabelFailureIsLoggedAndSwallowed(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willThrowException(new \RuntimeException('no catalogue'));
		$this->logger->expects($this->once())->method('warning');

		$event = new RegisterLeafProvidersEvent();
		(new RegisterProjectsLeafListener($l10n, $this->logger))->handle($event);

		$this->assertSame([], $event->getLeaves());
	}
}
