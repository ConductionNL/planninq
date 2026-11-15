<?php

/**
 * Unit tests for TaskReportReaderService: who may read a report shared with project readers, and syncing them.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\TaskReportReaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Planninq\Service\TaskReportReaderService
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 */
class TaskReportReaderServiceTest extends TestCase {

	/**
	 * The membership service; only its register reads are doubled.
	 *
	 * @var ProjectMembershipService&MockObject
	 */
	private ProjectMembershipService&MockObject $membership;

	/**
	 * The saves made, as their named arguments.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $saved = [];

	/**
	 * The projects the register holds.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $projects = [
		'p-1' => [
			'owner' => 'olga',
			'members' => ['mia', 'olga'],
			'managers' => ['max'],
			'viewers' => ['vic'],
			'memberGroups' => ['team-a'],
			'viewerGroups' => ['board'],
			'portfolioReaders' => ['pat'],
		],
		'p-2' => ['owner' => 'otto', 'ownerGroups' => ['ops']],
	];

	/**
	 * Build the membership double over the projects.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->membership = $this->getMockBuilder(className: ProjectMembershipService::class)
			->disableOriginalConstructor()
			->onlyMethods(['objectData', 'rows'])
			->getMock();
		$this->membership->method('objectData')->willReturnCallback(
			fn (string $schema, string $id): ?array => ($this->projects[$id] ?? null)
		);
	}//end setUp()

	/**
	 * The service, with OpenRegister installed or not, saving through a recording object service.
	 *
	 * @param bool $installed Whether OpenRegister is installed.
	 * @param bool $failing   Whether saving fails.
	 *
	 * @return TaskReportReaderService
	 */
	private function service(bool $installed = true, bool $failing = false): TaskReportReaderService {
		$saved   = &$this->saved;
		$objects = new class($saved, $failing) {
			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, mixed>> $saved   Where saves go.
			 * @param bool                             $failing Whether to fail.
			 */
			public function __construct(private array &$saved, private bool $failing) {
			}

			/**
			 * Record a save.
			 *
			 * @param array<string, mixed> $object        The object.
			 * @param string               $register      The register.
			 * @param string               $schema        The schema.
			 * @param string               $uuid          The id.
			 * @param bool                 $_rbac         Whether RBAC applies.
			 * @param bool                 $_multitenancy Whether multitenancy applies.
			 * @param bool                 $silent        Whether events are silenced.
			 *
			 * @return array<string, mixed>
			 */
			public function saveObject(array $object, string $register, string $schema, string $uuid, bool $_rbac, bool $_multitenancy, bool $silent): array {
				if ($this->failing === true) {
					throw new RuntimeException('locked');
				}

				$this->saved[] = compact('object', 'register', 'schema', 'uuid', '_rbac', '_multitenancy', 'silent');
				return $object;
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->with('OCA\\OpenRegister\\Service\\ObjectService')->willReturn($objects);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('isInstalled')->with('openregister')->willReturn($installed);

		return new TaskReportReaderService(
			membership: $this->membership,
			container: $container,
			appManager: $apps,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * A report shared with readers is read by every member, viewer and portfolio reader of its projects, and their groups.
	 *
	 * @return void
	 */
	public function testReadersAreTheProjectsPeopleAndGroups(): void {
		$readers = $this->service()->readersOf(data: ['shared' => 'readers', 'projects' => ['p-1', 'p-2', 'p-gone']]);

		$this->assertSame(expected: ['max', 'mia', 'olga', 'otto', 'pat', 'vic'], actual: $readers['readers']);
		$this->assertSame(expected: ['board', 'ops', 'team-a'], actual: $readers['readerGroups']);
	}//end testReadersAreTheProjectsPeopleAndGroups()

	/**
	 * A report not shared with readers has none.
	 *
	 * @return void
	 */
	public function testAPrivateReportHasNoReaders(): void {
		$this->membership->expects($this->never())->method('objectData');

		$this->assertSame(
			expected: ['readers' => [], 'readerGroups' => []],
			actual: $this->service()->readersOf(data: ['shared' => 'private', 'projects' => ['p-1']])
		);
	}//end testAPrivateReportHasNoReaders()

	/**
	 * Syncing a project rewrites only the shared reports on it whose readers changed.
	 *
	 * @return void
	 */
	public function testSyncRewritesOnlyChangedReportsOnTheProject(): void {
		$current = ['readers' => ['otto'], 'readerGroups' => ['ops']];
		$this->membership->expects($this->once())->method('rows')
			->with('taskReport', ['shared' => 'readers'])
			->willReturn([
				[
					'id'   => 'r-stale',
					'data' => ['shared' => 'readers', 'projects' => ['p-2'], 'readers' => ['old'], '@self' => ['id' => 'r-stale'], 'id' => 'r-stale'],
				],
				['id' => 'r-current', 'data' => ['shared' => 'readers', 'projects' => ['p-2']] + $current],
				['id' => 'r-other', 'data' => ['shared' => 'readers', 'projects' => ['p-1'], 'readers' => []]],
			]);

		$this->assertSame(expected: 1, actual: $this->service()->syncProject(projectId: 'p-2'));

		$this->assertCount(expectedCount: 1, haystack: $this->saved);
		$this->assertSame(
			expected: [
				'object' => ['shared' => 'readers', 'projects' => ['p-2'], 'readers' => ['otto'], 'readerGroups' => ['ops']],
				'register' => 'planninq',
				'schema' => 'taskReport',
				'uuid' => 'r-stale',
				'_rbac' => false,
				'_multitenancy' => false,
				'silent' => true,
			],
			actual: $this->saved[0]
		);
	}//end testSyncRewritesOnlyChangedReportsOnTheProject()

	/**
	 * Without a project id or without OpenRegister nothing is read or written.
	 *
	 * @return void
	 */
	public function testSyncDoesNothingWithoutAProjectOrOpenRegister(): void {
		$this->membership->expects($this->never())->method('rows');

		$this->assertSame(expected: 0, actual: $this->service()->syncProject(projectId: ''));
		$this->assertSame(expected: 0, actual: $this->service(installed: false)->syncProject(projectId: 'p-2'));
	}//end testSyncDoesNothingWithoutAProjectOrOpenRegister()

	/**
	 * A save that fails counts as not written.
	 *
	 * @return void
	 */
	public function testAFailedSaveIsNotCounted(): void {
		$this->membership->method('rows')->willReturn([
			['id' => 'r-stale', 'data' => ['shared' => 'readers', 'projects' => ['p-2']]],
		]);

		$this->assertSame(expected: 0, actual: $this->service(failing: true)->syncProject(projectId: 'p-2'));
	}//end testAFailedSaveIsNotCounted()
}//end class
