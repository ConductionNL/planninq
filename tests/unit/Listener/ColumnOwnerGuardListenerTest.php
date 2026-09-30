<?php

/**
 * Tests for ColumnOwnerGuardListener: only the project owner changes a board's columns.
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

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectDeletingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ColumnOwnerGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ColumnOwnerGuardListenerTest extends TestCase {
	use MembershipFixture;

	private const COLUMN = ['title' => 'Review', 'project' => 'proj-a', 'order' => 2, 'members' => ['bob', 'carol']];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'members' => ['bob'], 'owner' => 'carol']);
	}//end setUp()

	private function listener(string $actor, bool $isAdmin = false): ColumnOwnerGuardListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($isAdmin === true && $uid === $actor));

		return new ColumnOwnerGuardListener(
			membership: $this->membershipService(),
			scopeResolver: $this->scopeResolver(),
			userSession: $session,
			groupManager: $groups,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	private function column(array $data, string $schema = 'column'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'col-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end column()

	/**
	 * Scenario "A member cannot change columns": an update through the API is refused.
	 */
	public function testAMemberWhoIsNotTheOwnerCannotRenameAColumn(): void {
		$event = new ObjectUpdatingEvent($this->column(data: ['title' => 'Waiting'] + self::COLUMN), $this->column(data: self::COLUMN));

		$this->listener(actor: 'bob')->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame(ColumnOwnerGuardListener::ERROR_CODE, $event->getErrors()['code']);
	}//end testAMemberWhoIsNotTheOwnerCannotRenameAColumn()

	/**
	 * Scenario "A member who is not the owner cannot change rules" (boards-column-automation):
	 * a PATCH of `automation` is refused; the owner's is not.
	 *
	 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
	 */
	public function testAMemberWhoIsNotTheOwnerCannotChangeTheRules(): void {
		$rules = ['automation' => [['action' => 'assignMover']]] + self::COLUMN;

		$member = new ObjectUpdatingEvent($this->column(data: $rules), $this->column(data: self::COLUMN));
		$this->listener(actor: 'bob')->handle($member);
		self::assertTrue($member->isPropagationStopped());
		self::assertSame(ColumnOwnerGuardListener::ERROR_CODE, $member->getErrors()['code']);

		$owner = new ObjectUpdatingEvent($this->column(data: $rules), $this->column(data: self::COLUMN));
		$this->listener(actor: 'carol')->handle($owner);
		self::assertFalse($owner->isPropagationStopped());
	}//end testAMemberWhoIsNotTheOwnerCannotChangeTheRules()

	public function testAMemberCannotAddOrRemoveAColumn(): void {
		$create = new ObjectCreatingEvent($this->column(data: self::COLUMN));
		$this->listener(actor: 'bob')->handle($create);
		self::assertTrue($create->isPropagationStopped(), 'create');

		$delete = new ObjectDeletingEvent($this->column(data: self::COLUMN));
		$this->listener(actor: 'bob')->handle($delete);
		self::assertTrue($delete->isPropagationStopped(), 'delete');
	}//end testAMemberCannotAddOrRemoveAColumn()

	/**
	 * Scenario "Add and rename a column": the owner may.
	 */
	public function testTheOwnerAndAnAdminMayChangeColumns(): void {
		foreach ([['carol', false], ['root', true]] as [$actor, $admin]) {
			$event = new ObjectUpdatingEvent($this->column(data: ['title' => 'Waiting'] + self::COLUMN), $this->column(data: self::COLUMN));
			$this->listener(actor: $actor, isAdmin: $admin)->handle($event);
			self::assertFalse($event->isPropagationStopped(), $actor);
		}
	}//end testTheOwnerAndAnAdminMayChangeColumns()

	/**
	 * A member leaving the project rewrites every column's members list as that member.
	 */
	public function testAMembersOnlyRewriteIsAllowed(): void {
		$event = new ObjectUpdatingEvent($this->column(data: ['members' => ['carol']] + self::COLUMN), $this->column(data: self::COLUMN));

		$this->listener(actor: 'bob')->handle($event);

		self::assertFalse($event->isPropagationStopped());
	}//end testAMembersOnlyRewriteIsAllowed()

	public function testNoSessionAndOtherSchemasPass(): void {
		$event = new ObjectUpdatingEvent($this->column(data: ['title' => 'Waiting'] + self::COLUMN), $this->column(data: self::COLUMN));
		$this->listener(actor: '')->handle($event);
		self::assertFalse($event->isPropagationStopped(), 'a repair step or occ has no session');

		$task = new ObjectUpdatingEvent($this->column(data: ['title' => 'x', 'project' => 'proj-a'], schema: 'task'), $this->column(data: ['title' => 'y', 'project' => 'proj-a'], schema: 'task'));
		$this->listener(actor: 'bob')->handle($task);
		self::assertFalse($task->isPropagationStopped(), 'members still move cards');
	}//end testNoSessionAndOtherSchemasPass()
}//end class
