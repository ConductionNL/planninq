<?php

/**
 * Tests for the AssignBoardColumns repair step, over the real service and membership reader.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Repair
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

namespace OCA\Planninq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Repair\AssignBoardColumns;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AssignBoardColumnsTest extends TestCase {
	use MembershipFixture;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob'], 'owner' => 'alice']);
		$this->objects->seed('column', 'col-todo', ['title' => 'To do', 'project' => 'proj-a', 'order' => 0, 'type' => 'active', 'status' => 'open']);
		$this->objects->seed('column', 'col-done', ['title' => 'Done', 'project' => 'proj-a', 'order' => 1, 'type' => 'done', 'status' => 'done']);
		$this->objects->seed('task', 'ta', ['title' => 'TA', 'status' => 'open', 'project' => 'proj-a']);
		$this->objects->seed('task', 'tb', ['title' => 'TB', 'status' => 'done', 'project' => 'proj-a']);
		$this->objects->seed('task', 'tc', ['title' => 'TC', 'status' => 'open', 'project' => 'proj-a', 'column' => 'col-done']);
	}//end setUp()

	private function step(bool $openRegisterInstalled = true): AssignBoardColumns {
		$membership = $this->membershipService(openRegisterInstalled: $openRegisterInstalled);
		$settings   = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('getAdminSettings')->willReturn(['default_columns' => '["To do","Done"]']);

		return new AssignBoardColumns(
			columns: new BoardColumnService(
				container: $this->container(),
				membership: $membership,
				settings: $settings,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			membership: $membership,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end step()

	public function testRunPlacesColumnLessTasksAndLeavesPlacedOnesAlone(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with(self::stringContains('Placed 2 task(s)'));

		$this->step()->run($output);

		self::assertSame('col-todo', $this->objects->rows['task']['ta']['column']);
		self::assertSame('col-done', $this->objects->rows['task']['tb']['column']);
		self::assertSame('col-done', $this->objects->rows['task']['tc']['column'], 'a task that has a column keeps it');
	}//end testRunPlacesColumnLessTasksAndLeavesPlacedOnesAlone()

	public function testWithoutOpenRegisterItWarnsAndWritesNothing(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		$this->step(openRegisterInstalled: false)->run($output);

		self::assertSame([], $this->objects->saves);
	}//end testWithoutOpenRegisterItWarnsAndWritesNothing()
}//end class
