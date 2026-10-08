<?php

/**
 * Tests for the BackfillProjectMembers repair step (planninq#681).
 *
 * Rows stored before this release carry no `members` list, so the new rule
 * `{"members": {"$contains": "$userId"}}` would hide them from every member.
 * The step fills the list for every task, column, phase and planned time entry
 * of every project, on upgrade and on install.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Repair\BackfillProjectMembers;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Repair\BackfillProjectMembers
 * @covers \OCA\Planninq\Service\ProjectMembershipService
 */
class BackfillProjectMembersTest extends TestCase {
	use MembershipFixture;

	/**
	 * Two projects with legacy children that have no members list.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob', 'alice'], 'owner' => 'alice']);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'members' => ['dave'], 'owner' => 'erin']);
		$this->objects->seed('task', 'ta', ['title' => 'TA', 'status' => 'open', 'project' => 'proj-a']);
		$this->objects->seed('task', 'tb', ['title' => 'TB', 'status' => 'open', 'project' => 'proj-b']);
		$this->objects->seed('column', 'ca', ['title' => 'Todo', 'project' => 'proj-a', 'order' => 0]);
		$this->objects->seed('projectPhase', 'pb', ['title' => 'Plan', 'project' => 'proj-b']);
		$this->objects->seed('plannedTimeEntry', 'ea', ['task' => 'ta', 'user' => 'bob', 'duration' => 60]);
		$this->objects->seed('plannedTimeEntry', 'eb', ['project' => 'proj-b', 'task' => 'tb', 'user' => 'dave', 'duration' => 15]);
		$this->objects->seed('task', 'orphan', ['title' => 'Lost', 'status' => 'open', 'project' => 'proj-deleted']);

	}//end setUp()

	/**
	 * The repair step under test.
	 *
	 * @param bool $openRegisterInstalled Whether OpenRegister is installed.
	 *
	 * @return BackfillProjectMembers
	 */
	private function step(bool $openRegisterInstalled = true): BackfillProjectMembers {
		return new BackfillProjectMembers(
			membership: $this->membershipService(openRegisterInstalled: $openRegisterInstalled),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end step()

	/**
	 * Every child of every project gets its project's members and owner.
	 *
	 * @return void
	 */
	public function testFillsMembersOnEveryChildOfEveryProject(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertSame(['alice', 'bob'], $this->objects->rows['task']['ta']['members']);
		self::assertSame(['dave', 'erin'], $this->objects->rows['task']['tb']['members']);
		self::assertSame(['alice', 'bob'], $this->objects->rows['column']['ca']['members']);
		self::assertSame(['dave', 'erin'], $this->objects->rows['projectPhase']['pb']['members']);
		self::assertSame(['alice', 'bob'], $this->objects->rows['plannedTimeEntry']['ea']['members'], 'resolved through its task');
		self::assertSame(['dave', 'erin'], $this->objects->rows['plannedTimeEntry']['eb']['members']);
	}//end testFillsMembersOnEveryChildOfEveryProject()

	/**
	 * The rest of each row is written back unchanged.
	 *
	 * @return void
	 */
	public function testKeepsTheRestOfTheRow(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertSame(
			['task' => 'ta', 'user' => 'bob', 'duration' => 60, 'members' => ['alice', 'bob']],
			$this->objects->rows['plannedTimeEntry']['ea']
		);
	}//end testKeepsTheRestOfTheRow()

	/**
	 * A child whose project no longer exists is left alone, so it stays admin-only.
	 *
	 * @return void
	 */
	public function testLeavesOrphansAlone(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertArrayNotHasKey('members', $this->objects->rows['task']['orphan']);
	}//end testLeavesOrphansAlone()

	/**
	 * A second run writes nothing: the step is idempotent.
	 *
	 * @return void
	 */
	public function testSecondRunWritesNothing(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));
		$afterFirst = count($this->objects->saves);

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		self::assertSame(6, $afterFirst);
		self::assertSame($afterFirst, count($this->objects->saves));
	}//end testSecondRunWritesNothing()

	/**
	 * Projects are read across organisations and without RBAC: an upgrade has no user.
	 *
	 * @return void
	 */
	public function testReadsEveryProjectAsTheSystem(): void {
		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$projectSearch = array_values(array_filter($this->objects->searches, static fn (array $s): bool => $s['schema'] === 'project'));
		self::assertCount(1, $projectSearch);
		self::assertFalse($projectSearch[0]['_rbac']);
		self::assertFalse($projectSearch[0]['_multitenancy']);
	}//end testReadsEveryProjectAsTheSystem()

	/**
	 * It says what it did.
	 *
	 * @return void
	 */
	public function testReportsTheCount(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with($this->stringContains('6'));

		$this->step()->run($output);
	}//end testReportsTheCount()

	/**
	 * Without OpenRegister it warns and writes nothing.
	 *
	 * @return void
	 */
	public function testSkipsWithoutOpenRegister(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		$this->step(openRegisterInstalled: false)->run($output);

		self::assertSame([], $this->objects->saves);
	}//end testSkipsWithoutOpenRegister()

	/**
	 * A failure never escapes: under `<install>` an exception aborts the app install.
	 *
	 * @return void
	 */
	public function testNeverThrows(): void {
		$this->objects->failingSaves = ['ta', 'ca'];
		$output = $this->createMock(originalClassName: IOutput::class);

		$this->step()->run($output);

		self::assertSame(['dave', 'erin'], $this->objects->rows['task']['tb']['members'], 'the other rows still go through');
	}//end testNeverThrows()

	/**
	 * The step is registered after InitializeSettings in both repair blocks.
	 *
	 * InitializeSettings imports the register that adds the `members` property.
	 * Before that import there is no column to write to. `<install>` is the block
	 * that ran for the planix to planninq rename, so the step sits in both.
	 *
	 * @return void
	 */
	public function testRegisteredAfterTheRegisterImportInBothBlocks(): void {
		$xml = simplexml_load_file(__DIR__ . '/../../../appinfo/info.xml');
		self::assertNotFalse($xml);

		foreach (['post-migration', 'install'] as $block) {
			$steps = array_map('strval', iterator_to_array($xml->{'repair-steps'}->{$block}->step, false));
			$import = array_search('OCA\\Planninq\\Repair\\InitializeSettings', $steps, true);
			$backfill = array_search(BackfillProjectMembers::class, $steps, true);

			self::assertIsInt($import, $block . ': InitializeSettings is registered');
			self::assertIsInt($backfill, $block . ': BackfillProjectMembers is registered');
			self::assertGreaterThan($import, $backfill, $block . ': the back-fill runs after the import');
		}
	}//end testRegisteredAfterTheRegisterImportInBothBlocks()
}//end class
