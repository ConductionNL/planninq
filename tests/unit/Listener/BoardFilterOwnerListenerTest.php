<?php

/**
 * Tests for BoardFilterOwnerListener: a saved filter belongs to the person who
 * saved it, whatever a client sends, and keeps that owner on every update.
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
use OCA\Planninq\Listener\BoardFilterOwnerListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.1
 */
class BoardFilterOwnerListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const FILTER = ['project' => '00000000-0000-4000-8000-000000000001', 'name' => 'Overdue legal work', 'owner' => 'anna', 'shared' => true, 'criteria' => ['due' => ['op' => 'is', 'values' => ['overdue']]]];

	private function listener(string $actor): BoardFilterOwnerListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);

		return new BoardFilterOwnerListener(scopeResolver: $this->scopeResolver(), userSession: $session);
	}//end listener()

	private function filter(array $data, string $schema = 'boardFilter'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'bf-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end filter()

	public function testTheSaverOwnsANewFilter(): void {
		$event = new ObjectCreatingEvent($this->filter(data: ['owner' => 'bram'] + self::FILTER));

		$this->listener(actor: 'anna')->handle($event);

		self::assertSame(['owner' => 'anna'], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'boardFilter', payload: array_merge(self::FILTER, $event->getModifiedData())));
	}//end testTheSaverOwnsANewFilter()

	public function testAnUpdateKeepsTheStoredOwner(): void {
		foreach (['claimed' => 'bram', 'nulled' => null] as $case => $owner) {
			$event = new ObjectUpdatingEvent($this->filter(data: ['owner' => $owner] + self::FILTER), $this->filter(data: self::FILTER));
			$this->listener(actor: 'root')->handle($event);
			self::assertSame(['owner' => 'anna'], $event->getModifiedData(), $case);
		}

		$renamed = new ObjectUpdatingEvent($this->filter(data: ['name' => 'Legal, overdue'] + self::FILTER), $this->filter(data: self::FILTER));
		$this->listener(actor: 'anna')->handle($renamed);
		self::assertSame([], $renamed->getModifiedData(), 'nothing to restore');
	}//end testAnUpdateKeepsTheStoredOwner()

	public function testNoSessionAndOtherSchemasPass(): void {
		$event = new ObjectCreatingEvent($this->filter(data: self::FILTER));
		$this->listener(actor: '')->handle($event);
		self::assertSame([], $event->getModifiedData(), 'an import keeps the owner it brings');

		$task = new ObjectCreatingEvent($this->filter(data: self::FILTER, schema: 'task'));
		$this->listener(actor: 'bram')->handle($task);
		self::assertSame([], $task->getModifiedData(), 'not a saved filter');
	}//end testNoSessionAndOtherSchemasPass()

	/**
	 * A cross-project view belongs to whoever saved it, the same way.
	 *
	 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-1.1
	 */
	public function testTheSaverOwnsANewViewAndKeepsIt(): void {
		$view  = ['title' => 'IT operations', 'owner' => 'anna', 'members' => ['ben'], 'projects' => ['00000000-0000-4000-8000-000000000001']];
		$event = new ObjectCreatingEvent($this->filter(data: ['owner' => 'ben'] + $view, schema: 'boardView'));
		$this->listener(actor: 'anna')->handle($event);
		self::assertSame(['owner' => 'anna'], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'boardView', payload: array_merge($view, $event->getModifiedData())));

		$claim = new ObjectUpdatingEvent($this->filter(data: ['owner' => 'ben'] + $view, schema: 'boardView'), $this->filter(data: $view, schema: 'boardView'));
		$this->listener(actor: 'ben')->handle($claim);
		self::assertSame(['owner' => 'anna'], $claim->getModifiedData());
	}//end testTheSaverOwnsANewViewAndKeepsIt()
}//end class
