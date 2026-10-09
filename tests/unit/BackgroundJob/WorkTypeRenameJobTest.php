<?php

/**
 * Unit tests for the work type rename: the list change, the queued job and the entry update.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
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
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\BackgroundJob\WorkTypeRenameJob;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\WorkTypeRenameService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\BackgroundJob\WorkTypeRenameJob
 * @covers \OCA\Planninq\Service\WorkTypeRenameService
 * @uses \OCA\Planninq\Service\DependencyRepository
 * @uses \OCA\Planninq\Service\ProjectMembershipService
 */
class WorkTypeRenameJobTest extends TestCase {
	use MembershipFixture;

	/**
	 * The stored `work_types` value.
	 *
	 * @var string
	 */
	private string $stored = '["Development","Meeting"]';

	/**
	 * Jobs queued: [class, argument].
	 *
	 * @var array<int,array{0:string,1:mixed}>
	 */
	private array $queued = [];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('plannedTimeEntry', 'e1', ['task' => 't', 'user' => 'anna', 'duration' => 60, 'workType' => 'Development']);
		$this->objects->seed('plannedTimeEntry', 'e2', ['task' => 't', 'user' => 'bram', 'duration' => 30, 'workType' => 'Meeting']);
		$this->objects->seed('plannedTimeEntry', 'e3', ['task' => 't', 'user' => 'anna', 'duration' => 15, 'workType' => 'Development']);
		$this->objects->seed('plannedTimeEntry', 'e4', ['task' => 't', 'user' => 'anna', 'duration' => 10]);
	}//end setUp()

	private function service(): WorkTypeRenameService {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(fn (): string => $this->stored);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->stored = $value;
				return true;
			}
		);
		$jobs = $this->createMock(originalClassName: IJobList::class);
		$jobs->method('add')->willReturnCallback(
			function (string $class, mixed $argument = null): void {
				$this->queued[] = [$class, $argument];
			}
		);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new WorkTypeRenameService(
			appConfig: $appConfig,
			membership: $this->membershipService(),
			repository: new DependencyRepository(container: $this->container(), logger: $logger, appManager: $appManager),
			jobs: $jobs,
			logger: $logger
		);
	}//end service()

	/**
	 * Renaming changes the list and, when asked, queues the entry update.
	 */
	public function testRenameChangesTheListAndQueuesTheEntryUpdate(): void {
		$result = $this->service()->rename(from: 'Development', to: ' Ontwikkeling ', updateEntries: true);

		self::assertSame(['ok' => true, 'queued' => true], $result);
		self::assertSame('["Ontwikkeling","Meeting"]', $this->stored);
		self::assertSame([[WorkTypeRenameJob::class, ['from' => 'Development', 'to' => 'Ontwikkeling']]], $this->queued);
	}//end testRenameChangesTheListAndQueuesTheEntryUpdate()

	/**
	 * Without the update choice the entries keep the old name and nothing is queued.
	 */
	public function testRenameWithoutUpdatingEntriesQueuesNothing(): void {
		self::assertSame(['ok' => true, 'queued' => false], $this->service()->rename(from: 'Meeting', to: 'Overleg', updateEntries: false));
		self::assertSame([], $this->queued);
		self::assertSame('Meeting', $this->objects->rows['plannedTimeEntry']['e2']['workType']);
	}//end testRenameWithoutUpdatingEntriesQueuesNothing()

	/**
	 * Unknown names, empty names and a name already in the list are refused and change nothing.
	 */
	public function testRefusedRenamesChangeNothing(): void {
		$service = $this->service();

		self::assertSame('unknown', $service->rename(from: 'Nope', to: 'X', updateEntries: true)['error']);
		self::assertSame('empty', $service->rename(from: 'Meeting', to: '  ', updateEntries: true)['error']);
		self::assertSame('duplicate', $service->rename(from: 'Meeting', to: 'development', updateEntries: true)['error']);
		self::assertSame('["Development","Meeting"]', $this->stored);
		self::assertSame([], $this->queued);
	}//end testRefusedRenamesChangeNothing()

	/**
	 * The job gives the entries that carry the old name the new one, and leaves the others alone.
	 */
	public function testRenameUpdatesExistingEntries(): void {
		$job = new WorkTypeRenameJob(time: $this->createMock(originalClassName: ITimeFactory::class), service: $this->service());

		self::assertSame(2, $job->renameEntries(['from' => 'Development', 'to' => 'Ontwikkeling']));

		$rows = $this->objects->rows['plannedTimeEntry'];
		self::assertSame('Ontwikkeling', $rows['e1']['workType']);
		self::assertSame('Ontwikkeling', $rows['e3']['workType']);
		self::assertSame('Meeting', $rows['e2']['workType']);
		self::assertArrayNotHasKey('workType', $rows['e4']);
		self::assertSame(60, $rows['e1']['duration'], 'the rest of the entry is kept');
		self::assertSame('anna', $rows['e1']['user']);
	}//end testRenameUpdatesExistingEntries()

	/**
	 * A job with a missing or unchanged name does nothing.
	 */
	public function testJobIgnoresAnIncompleteArgument(): void {
		$job = new WorkTypeRenameJob(time: $this->createMock(originalClassName: ITimeFactory::class), service: $this->service());

		self::assertSame(0, $job->renameEntries(['from' => 'Development']));
		self::assertSame(0, $job->renameEntries(['from' => 'Development', 'to' => 'Development']));
		self::assertSame(0, $job->renameEntries(null));
	}//end testJobIgnoresAnIncompleteArgument()
}//end class
