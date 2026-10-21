<?php

/**
 * Tests for BoardOrderService: card moves, lane order and lane removal are
 * checked whole before anything is written.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
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

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Exception\ProjectActionException;
use OCA\Planninq\Service\BoardOrderService;
use OCA\Planninq\Service\ProjectActionSupport;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
class BoardOrderServiceTest extends TestCase {
	use MembershipFixture;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'dave', 'members' => ['dave']]);
		$this->objects->seed('column', 'todo', ['title' => 'Todo', 'project' => 'proj-a', 'order' => 0]);
		$this->objects->seed('column', 'doing', ['title' => 'Doing', 'project' => 'proj-a', 'order' => 1, 'status' => 'in_progress']);
		$this->objects->seed('column', 'done', ['title' => 'Done', 'project' => 'proj-a', 'order' => 2, 'type' => 'done']);
		$this->objects->seed('column', 'col-b', ['title' => 'Todo', 'project' => 'proj-b', 'order' => 0]);
		$this->objects->seed('task', 't1', ['title' => 'One', 'status' => 'open', 'project' => 'proj-a', 'column' => 'todo', 'columnOrder' => 1000]);
		$this->objects->seed('task', 't2', ['title' => 'Two', 'status' => 'open', 'project' => 'proj-a', 'column' => 'todo', 'columnOrder' => 2000]);
		$this->objects->seed('task', 't3', ['title' => 'Three', 'status' => 'done', 'project' => 'proj-a', 'column' => 'done', 'columnOrder' => 1000]);
	}//end setUp()

	private function service(string $actor): BoardOrderService {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);
		$groups->method('getUserGroupIds')->willReturn([]);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturn(true);
		$support = new ProjectActionSupport(membership: $this->membershipService(), container: $this->container(), userSession: $session, groupManager: $groups, appManager: $apps);

		return new BoardOrderService(membership: $this->membershipService(), support: $support);
	}//end service()

	private function assertRefused(callable $action, string $code, int $status): void {
		try {
			$action();
			self::fail('expected ' . $code);
		} catch (ProjectActionException $e) {
			self::assertSame($code, $e->getReason());
			self::assertSame($status, $e->getStatus());
		}
	}//end assertRefused()

	public function testCardsMoveTogetherAndOnlyTheirPlaceChanges(): void {
		$saved = $this->service(actor: 'bob')->orderTasks(projectId: 'proj-a', patches: [
			['id' => 't2', 'columnOrder' => 500, 'title' => 'Renamed'],
			['id' => 't1', 'column' => 'doing', 'columnOrder' => 1000, 'status' => 'in_progress'],
		]);

		self::assertCount(2, $saved);
		self::assertSame(500, $this->objects->rows['task']['t2']['columnOrder']);
		self::assertSame('Two', $this->objects->rows['task']['t2']['title'], 'only the place fields are written');
		self::assertSame('doing', $this->objects->rows['task']['t1']['column']);
	}//end testCardsMoveTogetherAndOnlyTheirPlaceChanges()

	public function testNothingIsWrittenWhenOneCardLeavesTheProject(): void {
		$this->assertRefused(
			action: fn () => $this->service(actor: 'bob')->orderTasks(projectId: 'proj-a', patches: [['id' => 't1', 'columnOrder' => 9], ['id' => 't2', 'column' => 'col-b']]),
			code: 'planninq-invalid-order',
			status: 400
		);
		self::assertSame(1000, $this->objects->rows['task']['t1']['columnOrder']);
		$this->assertRefused(action: fn () => $this->service(actor: 'dave')->orderTasks(projectId: 'proj-a', patches: [['id' => 't1', 'columnOrder' => 9]]), code: 'planninq-not-a-project-member', status: 403);
	}//end testNothingIsWrittenWhenOneCardLeavesTheProject()

	public function testTheOwnerReordersTheLanes(): void {
		$order = $this->service(actor: 'carol')->orderColumns(projectId: 'proj-a', columnIds: ['done', 'todo']);

		self::assertSame([['id' => 'done', 'order' => 0], ['id' => 'todo', 'order' => 1], ['id' => 'doing', 'order' => 2]], $order);
		self::assertSame(0, $this->objects->rows['column']['done']['order']);
		$this->assertRefused(action: fn () => $this->service(actor: 'bob')->orderColumns(projectId: 'proj-a', columnIds: ['done']), code: 'planninq-not-column-owner', status: 403);
	}//end testTheOwnerReordersTheLanes()

	public function testRemovingALaneMovesItsCardsToTheBottomOfAnother(): void {
		self::assertSame(2, $this->service(actor: 'carol')->removeColumn(columnId: 'todo', targetId: 'done'));

		self::assertSame('done', $this->objects->rows['task']['t1']['column']);
		self::assertSame('done', $this->objects->rows['task']['t1']['status']);
		self::assertSame(2000, $this->objects->rows['task']['t1']['columnOrder']);
		self::assertSame(3000, $this->objects->rows['task']['t2']['columnOrder']);
		self::assertArrayNotHasKey('todo', $this->objects->rows['column']);

		$this->assertRefused(action: fn () => $this->service(actor: 'carol')->removeColumn(columnId: 'doing', targetId: 'col-b'), code: 'planninq-invalid-target', status: 400);
		self::assertArrayHasKey('doing', $this->objects->rows['column']);
	}//end testRemovingALaneMovesItsCardsToTheBottomOfAnother()
}//end class
