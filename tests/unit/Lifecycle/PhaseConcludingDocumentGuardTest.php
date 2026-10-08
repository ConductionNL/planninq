<?php

/**
 * Unit tests for PhaseConcludingDocumentGuard.
 *
 * The guard runs inside OpenRegister's LifecycleValidationListener on the
 * `complete` transition of a project phase. These tests construct the real
 * OpenRegister GuardResult and LifecycleGuardInterface (tests/stubs/openregister
 * holds verbatim copies) and answer the file lookup with a fake FileService.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Lifecycle
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Lifecycle;

use OCA\OpenRegister\Lifecycle\LifecycleGuardInterface;
use OCA\Planninq\Lifecycle\PhaseConcludingDocumentGuard;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Tests for PhaseConcludingDocumentGuard.
 */
class PhaseConcludingDocumentGuardTest extends TestCase {

	private const PHASE = '2b7c1e4a-5d6f-4a8b-9c0d-1e2f3a4b5c6d';

	/**
	 * The fake OpenRegister FileService: files attached per object uuid, and every call it received.
	 *
	 * @var object
	 */
	private object $files;

	/**
	 * Set up a phase with one attached file, 4711.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->files = new class {
			/** @var array<string,array<int,string>> */
			public array $attached = [];

			/** @var array<int,string> */
			public array $calls = [];

			/**
			 * Mirrors FileService::getFile(object, file): a file id is looked up inside the object's folder only.
			 *
			 * @param mixed $object The object uuid.
			 * @param string|int $file The file id.
			 *
			 * @return object|null
			 */
			public function getFile(mixed $object = null, string|int $file = ''): ?object {
				$this->calls[] = 'getFile';
				return in_array((string)$file, ($this->attached[(string)$object] ?? []), true) ? new \stdClass() : null;
			}
		};
		$this->files->attached = [self::PHASE => ['4711']];
	}//end setUp()

	/**
	 * The guard under test, resolving the fake FileService.
	 *
	 * @param bool $available Whether OpenRegister's FileService resolves.
	 *
	 * @return PhaseConcludingDocumentGuard
	 */
	private function guard(bool $available = true): PhaseConcludingDocumentGuard {
		$container = $this->createMock(ContainerInterface::class);
		if ($available === true) {
			$container->method('get')->willReturn($this->files);
		} else {
			$container->method('get')->willThrowException(new \RuntimeException('no openregister'));
		}

		return new PhaseConcludingDocumentGuard(container: $container, logger: new NullLogger());
	}//end guard()

	/**
	 * Without a concluding document the phase does not close, and the message says what to do.
	 *
	 * @return void
	 */
	public function testDeniesWithoutDocument(): void {
		self::assertInstanceOf(LifecycleGuardInterface::class, $this->guard());
		foreach ([null, '', '  '] as $missing) {
			$result = $this->guard()->check(['id' => self::PHASE, 'status' => 'completed', 'concludingDocument' => $missing], 'complete', 'bob');
			self::assertFalse($result->isAllowed());
			self::assertSame(PhaseConcludingDocumentGuard::MESSAGE, $result->getMessage());
		}

		$result = $this->guard()->check(['id' => self::PHASE, 'status' => 'completed'], 'complete', 'bob');
		self::assertFalse($result->isAllowed());
	}//end testDeniesWithoutDocument()

	/**
	 * A file id that is not attached to this phase does not count.
	 *
	 * @return void
	 */
	public function testDeniesWhenFileIsNotOnThePhase(): void {
		$result = $this->guard()->check(['id' => self::PHASE, 'status' => 'completed', 'concludingDocument' => '999'], 'complete', 'bob');
		self::assertFalse($result->isAllowed());
		self::assertSame(PhaseConcludingDocumentGuard::MESSAGE, $result->getMessage());

		$elsewhere = $this->guard()->check(['id' => 'another-phase', 'status' => 'completed', 'concludingDocument' => '4711'], 'complete', 'bob');
		self::assertFalse($elsewhere->isAllowed(), 'a file on another phase does not conclude this one');

		$unavailable = $this->guard(available: false)->check(['id' => self::PHASE, 'status' => 'completed', 'concludingDocument' => '4711'], 'complete', 'bob');
		self::assertFalse($unavailable->isAllowed(), 'without the file service the guard fails closed');
	}//end testDeniesWhenFileIsNotOnThePhase()

	/**
	 * A file attached to the phase, named as its concluding document, lets the phase close.
	 *
	 * @return void
	 */
	public function testAllowsWithAttachedDocument(): void {
		$result = $this->guard()->check(['id' => self::PHASE, 'status' => 'completed', 'concludingDocument' => '4711'], 'complete', 'bob');
		self::assertTrue($result->isAllowed());
		self::assertNull($result->getMessage());
	}//end testAllowsWithAttachedDocument()

	/**
	 * The guard reads one file and changes nothing.
	 *
	 * @return void
	 */
	public function testNeverWrites(): void {
		$object = ['id' => self::PHASE, 'status' => 'completed', 'concludingDocument' => '4711'];
		$copy = $object;
		$this->guard()->check($object, 'complete', 'bob');
		self::assertSame($copy, $object);
		self::assertSame(['getFile'], $this->files->calls);
	}//end testNeverWrites()
}//end class
