<?php

/**
 * Tests for ProjectClosureListener: the server stamps and clears a project's closedAt.
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
require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ProjectClosureListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Listener\ProjectClosureListener
 *
 * @spec openspec/changes/retention-on-planninq-records/tasks.md#task-1.1
 */
class ProjectClosureListenerTest extends TestCase {
	use MembershipFixture;
	use RegisterSchemaValidation;

	private const NOW = '2026-11-17T15:00:00+00:00';

	private function listener(): ProjectClosureListener {
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime(self::NOW));

		return new ProjectClosureListener(scopeResolver: $this->scopeResolver(), timeFactory: $time);
	}//end listener()

	private function project(array $data, string $schema = 'project'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'p1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end project()

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function closedStatuses(): array {
		return ['completed' => ['completed'], 'archived' => ['archived']];
	}//end closedStatuses()

	/**
	 * @dataProvider closedStatuses
	 */
	public function testClosingAProjectStampsClosedAt(string $status): void {
		$event = new ObjectUpdatingEvent(
			$this->project(data: ['title' => 'P', 'status' => $status]),
			$this->project(data: ['title' => 'P', 'status' => 'active'])
		);

		$this->listener()->handle($event);

		self::assertSame(['closedAt' => self::NOW], $event->getModifiedData());
		self::assertSame([], $this->registerSchemaErrors(slug: 'project', payload: ['title' => 'P', 'status' => $status] + $event->getModifiedData()));
	}//end testClosingAProjectStampsClosedAt()

	public function testReopeningAProjectClearsClosedAt(): void {
		$event = new ObjectUpdatingEvent(
			$this->project(data: ['title' => 'P', 'status' => 'active', 'closedAt' => self::NOW]),
			$this->project(data: ['title' => 'P', 'status' => 'completed', 'closedAt' => self::NOW])
		);

		$this->listener()->handle($event);

		self::assertSame(['closedAt' => null], $event->getModifiedData());
	}//end testReopeningAProjectClearsClosedAt()

	public function testArchivingACompletedProjectKeepsItsClosure(): void {
		$event = new ObjectUpdatingEvent(
			$this->project(data: ['title' => 'P', 'status' => 'archived', 'closedAt' => '2025-01-31T09:00:00+00:00']),
			$this->project(data: ['title' => 'P', 'status' => 'completed', 'closedAt' => '2025-01-31T09:00:00+00:00'])
		);

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData(), 'the retention keeps dating from the first closure');
	}//end testArchivingACompletedProjectKeepsItsClosure()

	public function testAnActiveProjectGetsNoClosure(): void {
		$event = new ObjectCreatingEvent($this->project(data: ['title' => 'P', 'status' => 'active']));

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testAnActiveProjectGetsNoClosure()

	public function testAProjectCreatedClosedIsStampedAndMerged(): void {
		$event = new ObjectCreatingEvent($this->project(data: ['title' => 'P', 'status' => 'archived']));
		$event->setModifiedData(['members' => ['alice']]);

		$this->listener()->handle($event);

		self::assertSame(['members' => ['alice'], 'closedAt' => self::NOW], $event->getModifiedData());
	}//end testAProjectCreatedClosedIsStampedAndMerged()

	public function testOtherSchemasAreIgnored(): void {
		$event = new ObjectUpdatingEvent(
			$this->project(data: ['title' => 'T', 'status' => 'completed'], schema: 'task'),
			$this->project(data: ['title' => 'T', 'status' => 'active'], schema: 'task')
		);

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testOtherSchemasAreIgnored()
}//end class
