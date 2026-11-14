<?php

/**
 * Tests for the MoveLoggedHoursToHumaniq repair step.
 *
 * Every planninq time entry without a humaniq `TimeEntry` gets one, minutes
 * becoming hours, and a second run creates nothing.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Repair
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
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\Planninq\Repair\MoveLoggedHoursToHumaniq;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Repair\MoveLoggedHoursToHumaniq
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-existing-entries-move-once-without-loss
 */
class MoveLoggedHoursToHumaniqTest extends TestCase {

	/**
	 * The task the seeded entry was logged on.
	 *
	 * @var string
	 */
	private const TASK = '11111111-1111-4111-8111-111111111111';

	/**
	 * The project the seeded entry was logged in.
	 *
	 * @var string
	 */
	private const PROJECT = '22222222-2222-4222-8222-222222222222';

	/**
	 * The seeded planninq time entry.
	 *
	 * @var string
	 */
	private const ENTRY = '33333333-3333-4333-8333-333333333333';

	/**
	 * The fake OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objects;

	/**
	 * Which apps the app manager reports, by id.
	 *
	 * @var array<string,bool>
	 */
	private array $apps = [];

	/**
	 * Every info and warning line, in order.
	 *
	 * @var array<int,string>
	 */
	private array $said = [];

	/**
	 * Seed one 45-minute entry.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->said = [];
		$this->apps = ['openregister' => true, 'humaniq' => true];
		$this->objects = new InMemoryObjectService();
		$this->objects->seed(
			MoveLoggedHoursToHumaniq::SCHEMA,
			self::ENTRY,
			[
				'task' => self::TASK,
				'project' => self::PROJECT,
				'user' => 'anna',
				'date' => '2026-10-07',
				'duration' => 45,
				'description' => 'Bestek nagelopen',
				'billable' => true,
				'hourlyRate' => 85,
				'contractorRef' => 'contractor-1',
			]
		);
	}//end setUp()

	/**
	 * 45 minutes becomes one humaniq time entry of 0.75 hours on the task,
	 * and the planninq entry references it and keeps its rate.
	 *
	 * @return void
	 */
	public function testFortyFiveMinutesBecomeThreeQuartersOfAnHour(): void {
		$this->step()->run($this->repairOutput());

		$owner = $this->objects->rows[MoveLoggedHoursToHumaniq::OWNER_SCHEMA];
		$this->assertCount(1, $owner);
		$ownerId = array_key_first($owner);
		$this->assertSame(MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::ENTRY), $ownerId);
		$this->assertSame(
			[
				'date' => '2026-10-07',
				'hours' => 0.75,
				'userId' => 'anna',
				'description' => 'Bestek nagelopen',
				'billable' => true,
				'projectId' => self::PROJECT,
				'domainObjectType' => 'planninq:task',
				'domainObjectRef' => self::TASK,
			],
			$owner[$ownerId]
		);
		$this->assertSame('humaniq', $this->objects->saves[0]['register']);
		$this->assertFalse($this->objects->saves[0]['_rbac']);

		$planned = $this->objects->rows[MoveLoggedHoursToHumaniq::SCHEMA][self::ENTRY];
		$this->assertSame($ownerId, $planned['timeEntry']);
		$this->assertSame(85, $planned['hourlyRate']);
		$this->assertSame('contractor-1', $planned['contractorRef']);
		$this->assertSame(self::ENTRY, $this->objects->saves[1]['uuid']);
		$this->assertContains('Moved the hours of 1 time entr(y/ies) to humaniq; 0 could not be moved.', $this->said);
	}//end testFortyFiveMinutesBecomeThreeQuartersOfAnHour()

	/**
	 * A second run creates no further humaniq time entry.
	 *
	 * @return void
	 */
	public function testASecondRunCreatesNothing(): void {
		$this->step()->run($this->repairOutput());
		$saves = count($this->objects->saves);

		$this->step()->run($this->repairOutput());

		$this->assertCount(1, $this->objects->rows[MoveLoggedHoursToHumaniq::OWNER_SCHEMA]);
		$this->assertSame($saves, count($this->objects->saves));
	}//end testASecondRunCreatesNothing()

	/**
	 * Without humaniq nothing is written, and the step says the hours wait.
	 *
	 * @return void
	 */
	public function testWithoutHumaniqTheHoursWait(): void {
		$this->apps['humaniq'] = false;

		$this->step()->run($this->repairOutput());

		$this->assertSame([], $this->objects->saves);
		$this->assertSame(['humaniq is not enabled; logged hours stay in planninq until it is, and this step moves them then.'], $this->said);
	}//end testWithoutHumaniqTheHoursWait()

	/**
	 * A row whose humaniq entry cannot be written is logged and left
	 * unreferenced, so the next run tries it again.
	 *
	 * @return void
	 */
	public function testARowThatCannotMoveIsLeftForTheNextRun(): void {
		$this->objects->failingSaves = [self::ENTRY];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->step($logger)->run($this->repairOutput());

		$this->assertArrayNotHasKey('timeEntry', $this->objects->rows[MoveLoggedHoursToHumaniq::SCHEMA][self::ENTRY]);
		$this->assertContains('Moved the hours of 0 time entr(y/ies) to humaniq; 1 could not be moved.', $this->said);

		// The next run books the hours on the same humaniq entry, not a second one.
		$this->objects->failingSaves = [];
		$this->step()->run($this->repairOutput());

		$owner = $this->objects->rows[MoveLoggedHoursToHumaniq::OWNER_SCHEMA];
		$this->assertSame([MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::ENTRY)], array_keys($owner));
		$this->assertSame(
			MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::ENTRY),
			$this->objects->rows[MoveLoggedHoursToHumaniq::SCHEMA][self::ENTRY]['timeEntry']
		);
	}//end testARowThatCannotMoveIsLeftForTheNextRun()

	/**
	 * The derived uuid is stable per row, distinct between rows, and shaped
	 * as a version 4 uuid.
	 *
	 * @return void
	 */
	public function testTheOwnerUuidIsStablePerRow(): void {
		$uuid = MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::ENTRY);

		$this->assertSame($uuid, MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::ENTRY));
		$this->assertNotSame($uuid, MoveLoggedHoursToHumaniq::ownerUuid(plannedId: self::TASK));
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
	}//end testTheOwnerUuidIsStablePerRow()

	/**
	 * An entry without a task or project writes neither reference.
	 *
	 * @return void
	 */
	public function testAnEntryWithoutTaskOrProjectNamesNeither(): void {
		$entry = (new MoveLoggedHoursToHumaniq($this->appManager(), $this->container(), $this->createMock(LoggerInterface::class)))
			->ownerEntry(data: ['duration' => 90, 'user' => 'bram', 'date' => '2026-10-07', 'task' => ['id' => ''], 'project' => null]);

		$this->assertSame(['date' => '2026-10-07', 'hours' => 1.5, 'userId' => 'bram', 'description' => '', 'billable' => false], $entry);
	}//end testAnEntryWithoutTaskOrProjectNamesNeither()

	/**
	 * The step over the fake OpenRegister.
	 *
	 * @param LoggerInterface|null $logger The logger, a double when null.
	 *
	 * @return MoveLoggedHoursToHumaniq
	 */
	private function step(?LoggerInterface $logger = null): MoveLoggedHoursToHumaniq {
		return new MoveLoggedHoursToHumaniq(
			$this->appManager(),
			$this->container(),
			($logger ?? $this->createMock(LoggerInterface::class))
		);
	}//end step()

	/**
	 * An app manager answering from $apps.
	 *
	 * @return IAppManager&MockObject
	 */
	private function appManager(): IAppManager {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturnCallback(fn (string $app): bool => ($this->apps[$app] ?? false));
		$appManager->method('isEnabledForAnyone')->willReturnCallback(fn (string $app): bool => ($this->apps[$app] ?? false));

		return $appManager;
	}//end appManager()

	/**
	 * A container handing out the fake OpenRegister.
	 *
	 * @return ContainerInterface&MockObject
	 */
	private function container(): ContainerInterface {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);

		return $container;
	}//end container()

	/**
	 * Repair output recording what the step says.
	 *
	 * @return IOutput&MockObject
	 */
	private function repairOutput(): IOutput {
		$output = $this->createMock(IOutput::class);
		$output->method('info')->willReturnCallback(function (string $message): void {
			$this->said[] = $message;
		});
		$output->method('warning')->willReturnCallback(function (string $message): void {
			$this->said[] = $message;
		});

		return $output;
	}//end repairOutput()
}//end class
