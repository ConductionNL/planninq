<?php

/**
 * Tests for ReleaseShipGuardListener: only the ship action marks a release released.
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
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\OpenRegister\Service\SystemOperationContext;
use OCA\Planninq\Listener\ReleaseShipGuardListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
 */
class ReleaseShipGuardListenerTest extends TestCase {
	use MembershipFixture;

	private const RELEASE = ['title' => '1.0', 'status' => 'planned', 'project' => 'proj-a', 'releasedAt' => null];

	protected function setUp(): void {
		parent::setUp();
		$this->objects = new InMemoryObjectService();
	}//end setUp()

	private function listener(string $actor = 'bob'): ReleaseShipGuardListener {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($actor);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($actor === '' ? null : $user);

		return new ReleaseShipGuardListener(scopeResolver: $this->scopeResolver(), userSession: $session);
	}//end listener()

	private function release(array $data): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'rel-1', data: $data, register: '1', schema: $this->schemaId(slug: 'projectRelease'));
	}//end release()

	/**
	 * A person cannot mark a release released or back-date it, on create or update.
	 */
	public function testAPersonCannotShipByWritingTheRelease(): void {
		$forged = ['status' => 'released', 'releasedAt' => '2020-01-01T00:00:00+00:00'] + self::RELEASE;

		$create = new ObjectCreatingEvent($this->release(data: $forged));
		$this->listener()->handle($create);
		self::assertSame(['releasedAt' => null, 'status' => 'planned'], $create->getModifiedData());

		$update = new ObjectUpdatingEvent($this->release(data: $forged), $this->release(data: self::RELEASE));
		$this->listener()->handle($update);
		self::assertSame(['releasedAt' => null, 'status' => 'planned'], $update->getModifiedData());

		$shipped  = ['status' => 'released', 'releasedAt' => '2026-10-21T12:00:00+00:00'] + self::RELEASE;
		$backdate = new ObjectUpdatingEvent($this->release(data: ['releasedAt' => '2026-01-01T00:00:00+00:00'] + $shipped), $this->release(data: $shipped));
		$this->listener()->handle($backdate);
		self::assertSame(['releasedAt' => '2026-10-21T12:00:00+00:00'], $backdate->getModifiedData());
	}//end testAPersonCannotShipByWritingTheRelease()

	/**
	 * Renaming a release, and the ship action's own system write, pass.
	 */
	public function testOrdinaryEditsAndTheShipActionPass(): void {
		$rename = new ObjectUpdatingEvent($this->release(data: ['title' => '1.0 final'] + self::RELEASE), $this->release(data: self::RELEASE));
		$this->listener()->handle($rename);
		self::assertSame([], $rename->getModifiedData());

		$ship = new ObjectUpdatingEvent($this->release(data: ['status' => 'released', 'releasedAt' => '2026-10-21T12:00:00+00:00'] + self::RELEASE), $this->release(data: self::RELEASE));
		SystemOperationContext::run(fn () => $this->listener()->handle($ship));
		self::assertSame([], $ship->getModifiedData());
	}//end testOrdinaryEditsAndTheShipActionPass()
}//end class
