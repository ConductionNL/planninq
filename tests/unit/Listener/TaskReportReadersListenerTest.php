<?php

/**
 * Tests for TaskReportReadersListener: a shared report is read by the people
 * who read its projects, not by every signed-in user.
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
use OCA\Planninq\Listener\TaskReportReadersListener;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCA\Planninq\Service\TaskReportReaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @spec openspec/specs/custom-reports/spec.md
 */
class TaskReportReadersListenerTest extends TestCase {
	use MembershipFixture;

	protected function setUp(): void {
		parent::setUp();
		$this->schemaSlugs['26'] = 'taskReport';
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', ['title' => 'A', 'owner' => 'carol', 'members' => ['bob'], 'viewers' => ['vera'], 'memberGroups' => ['team-a'], 'portfolioReaders' => ['mia']]);
		$this->objects->seed('project', 'proj-b', ['title' => 'B', 'owner' => 'dave', 'viewerGroups' => ['audit']]);
	}//end setUp()

	private function listener(): TaskReportReadersListener {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturn(true);
		$readers = new TaskReportReaderService(
			membership: $this->membershipService(),
			container: $this->container(),
			appManager: $apps,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		return new TaskReportReadersListener(membership: $this->membershipService(), scopeResolver: $this->scopeResolver(), readers: $readers);
	}//end listener()

	private function report(array $data, string $schema = 'taskReport'): ObjectEntity {
		return InMemoryObjectService::entity(uuid: 'rep-1', data: $data, register: '1', schema: $this->schemaId(slug: $schema));
	}//end report()

	/**
	 * Scenario "Share a report": the readers of every project in it, by user and by group.
	 */
	public function testASharedReportIsReadByTheReadersOfItsProjects(): void {
		$event = new ObjectCreatingEvent($this->report(data: ['title' => 'Flow', 'owner' => 'carol', 'shared' => 'readers', 'projects' => ['proj-a', 'proj-b']]));

		$this->listener()->handle($event);

		self::assertSame(['bob', 'carol', 'dave', 'mia', 'vera'], $event->getModifiedData()['readers'] ?? null);
		self::assertSame(['audit', 'team-a'], $event->getModifiedData()['readerGroups'] ?? null);
	}//end testASharedReportIsReadByTheReadersOfItsProjects()

	/**
	 * A private report, or one sending its own reader list, carries only what the server computes.
	 */
	public function testAPrivateReportAndAForgedListAreOverwritten(): void {
		$private = new ObjectCreatingEvent($this->report(data: ['title' => 'Mine', 'shared' => 'private', 'projects' => ['proj-a'], 'readers' => ['eve']]));
		$this->listener()->handle($private);
		self::assertSame(['readers' => [], 'readerGroups' => []], $private->getModifiedData());

		$stored = ['title' => 'Flow', 'shared' => 'readers', 'projects' => ['proj-b'], 'readers' => ['dave'], 'readerGroups' => ['audit']];
		$forged = new ObjectUpdatingEvent($this->report(data: ['readers' => ['dave', 'eve'], 'readerGroups' => ['audit', 'everyone']] + $stored), $this->report(data: $stored));
		$this->listener()->handle($forged);
		self::assertSame(['readers' => ['dave'], 'readerGroups' => ['audit']], $forged->getModifiedData());

		$inStep = new ObjectUpdatingEvent($this->report(data: ['title' => 'Flow 2'] + $stored), $this->report(data: $stored));
		$this->listener()->handle($inStep);
		self::assertSame([], $inStep->getModifiedData());
	}//end testAPrivateReportAndAForgedListAreOverwritten()

	/**
	 * Other schemas are left alone.
	 */
	public function testOtherSchemasPass(): void {
		$event = new ObjectCreatingEvent($this->report(data: ['shared' => 'readers', 'projects' => ['proj-a']], schema: 'boardView'));

		$this->listener()->handle($event);

		self::assertSame([], $event->getModifiedData());
	}//end testOtherSchemasPass()
}//end class
