<?php

/**
 * Golden test for what a project change copies to the project's objects.
 *
 * @category Test
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

use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCA\Planninq\Listener\ProjectMembershipSyncListener;
use OCA\Planninq\Service\FinanceLineService;
use OCA\Planninq\Service\TaskReportReaderService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Records the writes, searches and log lines of each project change.
 *
 * @covers \OCA\Planninq\Listener\ProjectMembershipSyncListener
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Listener\TaskScopeResolver
 * @uses   \OCA\Planninq\Service\FinanceLineService
 * @uses   \OCA\Planninq\Service\TaskReportReaderService
 */
class ProjectMembershipSyncGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/project-membership-sync.golden.json';

	private const BASE = ['title' => 'A', 'members' => ['alice', 'bob'], 'owner' => 'alice'];

	/** @var array<int,array<string,mixed>> */
	private array $logged = [];

	/**
	 * Every change matches the recording.
	 *
	 * @return void
	 */
	public function testSyncMatchesTheRecording(): void {
		$changes = [
			'unchanged'        => [self::BASE, self::BASE],
			'member added'     => [self::BASE, ['members' => ['alice', 'bob', 'carol']] + self::BASE],
			'member removed'   => [self::BASE, ['members' => ['alice']] + self::BASE],
			'owner changed'    => [self::BASE, ['owner' => 'dave'] + self::BASE],
			'viewers changed'  => [self::BASE, ['viewers' => ['vic']] + self::BASE],
			'groups changed'   => [self::BASE, ['memberGroups' => ['devs'], 'viewerGroups' => ['audit']] + self::BASE],
			'readers changed'  => [self::BASE, ['portfolioReaders' => ['pat']] + self::BASE],
			'readers reorder'  => [['portfolioReaders' => ['pat', 'quin']] + self::BASE, ['portfolioReaders' => ['quin', 'pat', 'pat']] + self::BASE],
			'portfolio moved'  => [['portfolio' => 'pf-1'] + self::BASE, ['portfolio' => 'pf-2'] + self::BASE],
			'title only'       => [self::BASE, ['title' => 'B'] + self::BASE],
			'no stored'        => [null, self::BASE],
			'save fails'       => [self::BASE, ['members' => ['alice', 'bob', 'carol']] + self::BASE],
		];

		$recorded = [];
		foreach ($changes as $name => [$old, $new]) {
			$this->seed();
			if ($name === 'save fails') {
				$this->objects->failingSaves = ['t1'];
			}

			$this->listener()->handle($this->update(old: $old, new: $new, schema: 'project'));
			$recorded[$name] = $this->observed();
		}

		$this->seed();
		$this->listener()->handle($this->update(old: self::BASE, new: ['members' => ['zed']] + self::BASE, schema: 'task'));
		$recorded['not a project'] = $this->observed();

		$this->seed();
		$this->listener()->handle(
			new ObjectUpdatingEvent(
				InMemoryObjectService::entity(uuid: 'proj-a', data: ['members' => ['zed']] + self::BASE, register: '1', schema: $this->schemaId(slug: 'project')),
				InMemoryObjectService::entity(uuid: 'proj-a', data: self::BASE, register: '1', schema: $this->schemaId(slug: 'project'))
			)
		);
		$recorded['pre event'] = $this->observed();

		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($fixture), actual: array_keys($recorded));
		foreach ($recorded as $case => $value) {
			self::assertSame(expected: $fixture[$case], actual: $value, message: $case);
		}
	}//end testSyncMatchesTheRecording()

	/**
	 * A failure while copying does not reach the project write: it is logged as an error.
	 *
	 * @return void
	 */
	public function testAFailingCopyIsLoggedNotThrown(): void {
		$this->seed();
		$logger = $this->createMock(originalClassName: \Psr\Log\LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with('Planninq: could not copy a project membership change to its objects');
		$reports = $this->createMock(originalClassName: TaskReportReaderService::class);
		$reports->method('syncProject')->willThrowException(new \RuntimeException('reports down'));
		$membership = $this->membershipService();

		$listener = new ProjectMembershipSyncListener(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			scopeResolver: $this->scopeResolver(),
			logger: $logger,
			reports: $reports
		);
		$listener->handle($this->update(old: self::BASE, new: ['members' => ['alice']] + self::BASE, schema: 'project'));
	}//end testAFailingCopyIsLoggedNotThrown()

	/**
	 * The writes, searches and log lines of the run, then reset.
	 *
	 * @return array<string,mixed>
	 */
	private function observed(): array {
		$out = [
			'saves'    => array_map(static fn (array $save): array => [$save['schema'], $save['uuid'], $save['object'], $save['_rbac'], $save['silent']], $this->objects->saves),
			'searches' => array_map(static fn (array $search): array => [$search['schema'], $search['filters']], $this->objects->searches),
			'logged'   => $this->logged,
		];
		$this->logged = [];

		return json_decode((string)json_encode($out), true);
	}//end observed()

	/**
	 * Seed project A with children in step, a finance line and a report; project B untouched.
	 *
	 * @return void
	 */
	private function seed(): void {
		$inStep        = ['alice', 'bob'];
		$this->objects = new InMemoryObjectService();
		$this->objects->seed('project', 'proj-a', self::BASE);
		$this->objects->seed('task', 't1', ['title' => 'T1', 'project' => 'proj-a', 'members' => $inStep]);
		$this->objects->seed('column', 'c1', ['title' => 'Todo', 'project' => 'proj-a', 'members' => $inStep]);
		$this->objects->seed('plannedTimeEntry', 'te-by-task', ['task' => 't1', 'user' => 'alice', 'members' => $inStep]);
		$this->objects->seed('financeLine', 'fl-1', ['project' => 'proj-a', 'amount' => 10]);
		$this->objects->seed('task', 'other', ['title' => 'Elsewhere', 'project' => 'proj-b', 'members' => ['dave']]);
	}//end seed()

	/**
	 * An updated event from old to new data.
	 *
	 * @param array<string,mixed>|null $old    The stored data, null when unknown.
	 * @param array<string,mixed>      $new    The saved data.
	 * @param string                   $schema The schema slug.
	 *
	 * @return ObjectUpdatedEvent
	 */
	private function update(?array $old, array $new, string $schema): ObjectUpdatedEvent {
		$oldEntity = null;
		if ($old !== null) {
			$oldEntity = InMemoryObjectService::entity(uuid: 'proj-a', data: $old, register: '1', schema: $this->schemaId(slug: $schema));
		}

		return new ObjectUpdatedEvent(
			InMemoryObjectService::entity(uuid: 'proj-a', data: $new, register: '1', schema: $this->schemaId(slug: $schema)),
			$oldEntity
		);
	}//end update()

	/**
	 * The listener under test, logging into $this->logged.
	 *
	 * @return ProjectMembershipSyncListener
	 */
	private function listener(): ProjectMembershipSyncListener {
		$logged = &$this->logged;
		$logger = new class ($logged) extends AbstractLogger {
			/** @var array<int,array<string,mixed>> */
			private array $logged;

			public function __construct(array &$logged) {
				$this->logged = &$logged;
			}

			public function log($level, string|Stringable $message, array $context = []): void {
				$this->logged[] = [$level, (string)$message, $context];
			}
		};

		$membership = $this->membershipService();
		$apps       = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->willReturn(true);

		return new ProjectMembershipSyncListener(
			membership: $membership,
			finance: new FinanceLineService(membership: $membership, container: $this->container(), logger: $logger),
			scopeResolver: $this->scopeResolver(),
			logger: $logger,
			reports: new TaskReportReaderService(membership: $membership, container: $this->container(), appManager: $apps, logger: $logger)
		);
	}//end listener()
}//end class
