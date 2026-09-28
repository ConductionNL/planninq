<?php

/**
 * Tests for TaskCompletionListener: the server stamps and clears completedAt.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Listener
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

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\TaskCompletionListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class TaskCompletionListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const NOW = '2026-09-28T21:30:00+00:00';

	private function listener(): TaskCompletionListener {
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime(self::NOW));

		return new TaskCompletionListener(scopeResolver: $this->scopeResolver(), timeFactory: $time);
	}//end listener()

	private function task(array $data, string $schema = 'task'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 't1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end task()

	/**
	 * Scenario "A move through the API is stamped too": status becomes done on update.
	 */
	public function testEnteringDoneStampsCompletedAt(): void {
		$event = new ObjectUpdatingEvent(
			$this->task(data: ['title' => 'T', 'status' => 'done']),
			$this->task(data: ['title' => 'T', 'status' => 'in_progress'])
		);

		$this->listener()->handle($event);

		self::assertSame(['completedAt' => self::NOW], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: ['title' => 'T', 'status' => 'done'] + $event->getModifiedData()));
	}//end testEnteringDoneStampsCompletedAt()

	public function testLeavingDoneClearsCompletedAt(): void {
		$event = new ObjectUpdatingEvent(
			$this->task(data: ['title' => 'T', 'status' => 'open', 'completedAt' => self::NOW]),
			$this->task(data: ['title' => 'T', 'status' => 'done', 'completedAt' => self::NOW])
		);

		$this->listener()->handle($event);

		self::assertSame(['completedAt' => null], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'task', payload: ['title' => 'T', 'status' => 'open'] + $event->getModifiedData()));
	}//end testLeavingDoneClearsCompletedAt()

	public function testAnUnrelatedUpdateWritesNothing(): void {
		$event = new ObjectUpdatingEvent(
			$this->task(data: ['title' => 'Renamed', 'status' => 'done', 'completedAt' => '2026-09-01T10:00:00+00:00']),
			$this->task(data: ['title' => 'T', 'status' => 'done', 'completedAt' => '2026-09-01T10:00:00+00:00'])
		);

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData(), 'a task that stays done keeps its finish time');
	}//end testAnUnrelatedUpdateWritesNothing()

	public function testACreateWithStatusDoneIsStamped(): void {
		$event = new ObjectCreatingEvent($this->task(data: ['title' => 'T', 'status' => 'done']));

		$this->listener()->handle($event);

		self::assertSame(['completedAt' => self::NOW], $event->getModifiedData());
	}//end testACreateWithStatusDoneIsStamped()

	public function testItMergesWithWhatOtherListenersSet(): void {
		$event = new ObjectCreatingEvent($this->task(data: ['title' => 'T', 'status' => 'done']));
		$event->setModifiedData(['members' => ['alice']]);

		$this->listener()->handle($event);

		self::assertSame(['members' => ['alice'], 'completedAt' => self::NOW], $event->getModifiedData());
	}//end testItMergesWithWhatOtherListenersSet()

	public function testOtherSchemasAreIgnored(): void {
		$event = new ObjectCreatingEvent($this->task(data: ['title' => 'T', 'status' => 'done'], schema: 'column'));

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testOtherSchemasAreIgnored()
}//end class
