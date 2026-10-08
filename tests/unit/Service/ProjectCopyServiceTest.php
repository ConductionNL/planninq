<?php

/**
 * Tests for ProjectCopyService: remapped references, the date shift, the
 * people choice and a clean slate after a failure.
 *
 * @category Test
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
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\Exception\ProjectCopyException;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\ProjectCopyService;
use OCA\Planninq\Service\ProjectMembershipService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Planninq\Service\ProjectCopyService
 */
class ProjectCopyServiceTest extends TestCase {

	/**
	 * Build the service over a fake ObjectService.
	 *
	 * @param object $fake The fake ObjectService.
	 *
	 * @return ProjectCopyService
	 */
	private function service(object $fake): ProjectCopyService {
		$membership = $this->createMock(originalClassName: ProjectMembershipService::class);
		$membership->method('objectData')->willReturn(
			[
				'title' => 'Aanbesteding', 'owner' => 'olga', 'members' => ['olga', 'mies'], 'managers' => ['mark'],
				'startDate' => '2026-03-01', 'isTemplate' => true, 'key' => 'AANB',
			]
		);
		$membership->method('rows')->willReturnCallback(
			static fn (string $schema, array $filters): array => match ($schema) {
				'column' => [['id' => 'c1', 'data' => ['title' => 'Intake', 'order' => 1, 'project' => 'src']]],
				'projectPhase' => [['id' => 'ph1', 'data' => ['title' => 'Fase', 'startDate' => '2026-03-01', 'project' => 'src']]],
				'task' => [
					['id' => 't2', 'data' => ['title' => 'Sub', 'parent' => 't1', 'status' => 'done', 'dueDate' => '2026-03-20', 'assignedTo' => 'mies', 'project' => 'src']],
					['id' => 't1', 'data' => ['title' => 'Main', 'column' => 'c1', 'phase' => 'ph1', 'status' => 'done', 'dueDate' => '2026-03-15', 'assignedTo' => 'mies', 'project' => 'src']],
				],
				'dependency' => [['id' => 'd1', 'data' => ['blocker' => 't1', 'blocked' => 't2', 'type' => 'blocks']]],
				default => [],
			}
		);
		$repository = $this->createMock(originalClassName: DependencyRepository::class);
		$repository->method('objectService')->willReturn($fake);

		return new ProjectCopyService(
			membership: $membership,
			repository: $repository,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

	}//end service()

	/**
	 * A fake ObjectService that records writes and can fail on a schema.
	 *
	 * @param string|null $failOn Schema slug whose save throws.
	 *
	 * @return object
	 */
	private function fake(?string $failOn = null): object {
		return new class($failOn) {
			// phpcs:disable
			public array $saved = [];
			public array $deleted = [];
			private int $n = 0;
			public function __construct(private ?string $failOn) {}
			public function saveObject(array $object, string $register, string $schema, ?string $uuid = null): array {
				if ($schema === $this->failOn) {
					throw new \RuntimeException('boom');
				}
				$id = $schema . '-new-' . (++$this->n);
				$this->saved[] = [$schema, $id, $object];
				return ['id' => $id];
			}
			public function deleteObject(string $uuid, string $register, string $schema, bool $_rbac = true): bool {
				$this->deleted[] = [$schema, $uuid];
				return true;
			}
			// phpcs:enable
		};

	}//end fake()

	/**
	 * Columns, phases, parents and dependencies point at the copies; dates shift.
	 *
	 * @return void
	 */
	public function testReferencesAreRemappedAndDatesShift(): void {
		$fake   = $this->fake();
		$result = $this->service($fake)->copy(
			sourceId: 'src',
			userId: 'anna',
			options: ['title' => 'Wegbeheer', 'startDate' => '2026-06-01', 'parts' => ['columns' => true, 'phases' => true, 'tasks' => true, 'dependencies' => true]]
		);

		$bySchema = [];
		foreach ($fake->saved as [$schema, $id, $data]) {
			$bySchema[$schema][$id] = $data;
		}

		$newProject = array_key_first($bySchema['project']);
		self::assertSame(expected: $newProject, actual: $result['id']);
		self::assertSame(expected: ['columns' => 1, 'phases' => 1, 'tasks' => 2, 'dependencies' => 1], actual: $result['counts']);

		$main = array_values(array_filter($bySchema['task'], static fn (array $t): bool => $t['title'] === 'Main'))[0];
		$sub  = array_values(array_filter($bySchema['task'], static fn (array $t): bool => $t['title'] === 'Sub'))[0];
		$mainId = array_search($main, $bySchema['task'], true);
		self::assertSame(expected: array_key_first($bySchema['column']), actual: $main['column']);
		self::assertSame(expected: array_key_first($bySchema['projectPhase']), actual: $main['phase']);
		self::assertSame(expected: $mainId, actual: $sub['parent']);
		self::assertSame(expected: '2026-06-15', actual: $main['dueDate']);
		self::assertSame(expected: 'open', actual: $main['status']);
		self::assertArrayNotHasKey('assignedTo', $main);
		self::assertSame(expected: $newProject, actual: $main['project']);

		$dependency = array_values($bySchema['dependency'])[0];
		self::assertSame(expected: $mainId, actual: $dependency['blocker']);
		self::assertSame(expected: array_search($sub, $bySchema['task'], true), actual: $dependency['blocked']);

	}//end testReferencesAreRemappedAndDatesShift()

	/**
	 * Without the people choice the caller is the only person on the copy.
	 *
	 * @return void
	 */
	public function testPeopleStayBehindUnlessKept(): void {
		$fake = $this->fake();
		$this->service($fake)->copy(sourceId: 'src', userId: 'anna', options: ['title' => 'Kopie', 'parts' => []]);

		$project = $fake->saved[0][2];
		self::assertSame(expected: ['anna'], actual: $project['members']);
		self::assertSame(expected: 'anna', actual: $project['owner']);
		self::assertArrayNotHasKey('managers', $project);
		self::assertFalse($project['isTemplate']);
		self::assertArrayNotHasKey('key', $project);

		$kept = $this->fake();
		$this->service($kept)->copy(sourceId: 'src', userId: 'anna', options: ['title' => 'Kopie', 'parts' => ['people' => true]]);
		self::assertSame(expected: ['anna', 'olga', 'mies'], actual: $kept->saved[0][2]['members']);
		self::assertSame(expected: ['mark'], actual: $kept->saved[0][2]['managers']);

	}//end testPeopleStayBehindUnlessKept()

	/**
	 * A failure while writing tasks removes the project, columns and phases.
	 *
	 * @return void
	 */
	public function testAFailureMidCopyLeavesNothing(): void {
		$fake = $this->fake(failOn: 'task');

		try {
			$this->service($fake)->copy(sourceId: 'src', userId: 'anna', options: ['title' => 'Kopie', 'parts' => ['columns' => true, 'phases' => true, 'tasks' => true]]);
			self::fail('The copy should have failed.');
		} catch (ProjectCopyException $e) {
			self::assertSame(expected: 'tasks', actual: $e->getStep());
		}

		$written = array_map(static fn (array $row): string => $row[1], $fake->saved);
		$removed = array_map(static fn (array $row): string => $row[1], $fake->deleted);
		self::assertCount(3, $written);
		self::assertEqualsCanonicalizing($written, $removed);

	}//end testAFailureMidCopyLeavesNothing()

}//end class
