<?php

/**
 * Tests for LoggedHoursHandoverListener.
 *
 * A planninq time entry created with its hours on it has them booked on
 * humaniq's `TimeEntry` after the save; one created already pointing at a
 * humaniq entry, without hours, or without humaniq is left alone.
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
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planninq\Kernel\Contracts\TaskScopeResolverInterface;
use OCA\Planninq\Listener\LoggedHoursHandoverListener;
use OCA\Planninq\Service\LoggedHoursMover;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Listener\LoggedHoursHandoverListener
 * @uses \OCA\Planninq\Service\LoggedHoursMover
 * @uses \OCA\Planninq\Service\ObjectRows
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
class LoggedHoursHandoverListenerTest extends TestCase {

	/**
	 * The created planninq time entry.
	 *
	 * @var string
	 */
	private const ENTRY = '44444444-4444-4444-8444-444444444444';

	/**
	 * The task the time was logged on.
	 *
	 * @var string
	 */
	private const TASK = '11111111-1111-4111-8111-111111111111';

	/**
	 * What the portal's "Log time" action sends: 90 minutes on a task.
	 *
	 * @var array<string,mixed>
	 */
	private const SENT = [
		'task' => self::TASK,
		'user' => 'carla',
		'date' => '2026-11-20',
		'duration' => 90,
		'description' => 'Werkbezoek',
		'contractorRef' => 'contractor-7',
	];

	/**
	 * The fake OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objects;

	/**
	 * Whether humaniq is enabled.
	 *
	 * @var bool
	 */
	private bool $humaniq = true;

	/**
	 * Start every test with humaniq enabled and an empty OpenRegister.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->humaniq = true;
		$this->objects = new InMemoryObjectService();
	}//end setUp()

	/**
	 * 90 minutes created on the planninq row become one humaniq entry of 1.5
	 * hours on the task, with the derived uuid, and the row points at it.
	 *
	 * @return void
	 */
	public function testHoursCreatedOnTheRowAreBookedOnHumaniq(): void {
		$this->objects->seed(LoggedHoursMover::SCHEMA, self::ENTRY, self::SENT);

		$this->listener()->handle(new ObjectCreatedEvent($this->entry(data: self::SENT)));

		$ownerId = LoggedHoursMover::ownerUuid(plannedId: self::ENTRY);
		$owner   = $this->objects->rows[LoggedHoursMover::OWNER_SCHEMA][$ownerId];
		self::assertSame(expected: 1.5, actual: $owner['hours']);
		self::assertSame(expected: 'carla', actual: $owner['userId']);
		self::assertSame(expected: '2026-11-20', actual: $owner['date']);
		self::assertSame(expected: 'planninq:task', actual: $owner['domainObjectType']);
		self::assertSame(expected: self::TASK, actual: $owner['domainObjectRef']);
		self::assertSame(expected: 'humaniq', actual: $this->objects->saves[0]['register']);

		$planned = $this->objects->rows[LoggedHoursMover::SCHEMA][self::ENTRY];
		self::assertSame(expected: $ownerId, actual: $planned['timeEntry']);
		self::assertSame(expected: 'contractor-7', actual: $planned['contractorRef']);
	}//end testHoursCreatedOnTheRowAreBookedOnHumaniq()

	/**
	 * A row planninq's own pages created already references its humaniq entry: nothing is written.
	 *
	 * @return void
	 */
	public function testARowThatReferencesAnEntryIsLeftAlone(): void {
		$this->listener()->handle(new ObjectCreatedEvent($this->entry(data: ['timeEntry' => 'owner-1'] + self::SENT)));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testARowThatReferencesAnEntryIsLeftAlone()

	/**
	 * A row without hours has nothing to book.
	 *
	 * @return void
	 */
	public function testARowWithoutHoursIsLeftAlone(): void {
		$this->listener()->handle(new ObjectCreatedEvent($this->entry(data: ['duration' => 0] + self::SENT)));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testARowWithoutHoursIsLeftAlone()

	/**
	 * Without humaniq the hours stay on the row for the repair step to move later.
	 *
	 * @return void
	 */
	public function testWithoutHumaniqTheHoursWait(): void {
		$this->humaniq = false;

		$this->listener()->handle(new ObjectCreatedEvent($this->entry(data: self::SENT)));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testWithoutHumaniqTheHoursWait()

	/**
	 * Another schema, another event, or an entity without a uuid is not a planninq time entry create.
	 *
	 * @return void
	 */
	public function testOtherObjectsAndEventsAreIgnored(): void {
		$listener = $this->listener();

		$listener->handle(new ObjectCreatedEvent($this->entry(data: self::SENT, schema: 'task')));
		$listener->handle(new ObjectUpdatedEvent($this->entry(data: self::SENT), $this->entry(data: self::SENT)));
		$anonymous = InMemoryObjectService::entity(uuid: '', data: self::SENT, register: '1', schema: LoggedHoursMover::SCHEMA);
		$listener->handle(new ObjectCreatedEvent($anonymous));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testOtherObjectsAndEventsAreIgnored()

	/**
	 * When OpenRegister cannot be resolved the listener logs and writes nothing.
	 *
	 * @return void
	 */
	public function testAnUnreachableObjectServiceIsLogged(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('gone'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->listener(container: $container, logger: $logger)->handle(event: new ObjectCreatedEvent(object: $this->entry(data: self::SENT)));

		self::assertSame(expected: [], actual: $this->objects->saves);
	}//end testAnUnreachableObjectServiceIsLogged()

	/**
	 * The listener over the fake OpenRegister.
	 *
	 * @param ContainerInterface|null $container The container, one handing out the fake when null.
	 * @param LoggerInterface|null    $logger    The logger, a double when null.
	 *
	 * @return LoggedHoursHandoverListener
	 */
	private function listener(?ContainerInterface $container = null, ?LoggerInterface $logger = null): LoggedHoursHandoverListener {
		$resolver = $this->createMock(originalClassName: TaskScopeResolverInterface::class);
		$resolver->method('planninqSchemaSlug')->willReturnCallback(
			function (string $registerId, string $schemaId): string {
				if ($registerId === '1') {
					return $schemaId;
				}

				return '';
			}
		);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturnCallback(
			fn (string $app): bool => ($app === 'humaniq' && $this->humaniq)
		);

		if ($container === null) {
			$container = $this->createMock(originalClassName: ContainerInterface::class);
			$container->method('get')->willReturn($this->objects);
		}

		$logger = ($logger ?? $this->createMock(originalClassName: LoggerInterface::class));

		return new LoggedHoursHandoverListener(
			scopeResolver: $resolver,
			mover: new LoggedHoursMover(container: $container, logger: $logger),
			appManager: $appManager
		);
	}//end listener()

	/**
	 * A planninq object entity, its schema id being its slug.
	 *
	 * @param array<string,mixed> $data   The data.
	 * @param string              $schema The schema slug.
	 *
	 * @return ObjectEntity
	 */
	private function entry(array $data, string $schema = LoggedHoursMover::SCHEMA): ObjectEntity {
		return InMemoryObjectService::entity(uuid: self::ENTRY, data: $data, register: '1', schema: $schema);
	}//end entry()
}//end class
