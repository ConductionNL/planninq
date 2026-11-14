<?php

/**
 * Tests for BoardExportImportService: a mapped export written as a project with columns and tasks.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use InvalidArgumentException;
use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\BoardExportImportService;
use OCA\Planninq\Service\BoardExportMapper;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The import writes the project, then its columns in order, then each task in its column with its status.
 *
 * @covers \OCA\Planninq\Service\BoardExportImportService
 * @uses   \OCA\Planninq\Service\BoardExportMapper
 * @uses   \OCA\Planninq\Service\BoardColumnService
 */
class BoardExportImportServiceTest extends TestCase {

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $objects;

	/**
	 * The service over the in-memory store, with "ada" as the only account.
	 *
	 * @return BoardExportImportService
	 */
	private function service(): BoardExportImportService {
		$this->objects = new InMemoryObjectService();
		$container     = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			fn (string $id): object => match ($id) {
				'OCA\\OpenRegister\\Service\\ObjectService' => $this->objects,
			}
		);
		$users = $this->createMock(IUserManager::class);
		$users->method('userExists')->willReturnCallback(static fn (string $uid): bool => $uid === 'ada');
		$logger  = $this->createMock(LoggerInterface::class);
		$columns = new BoardColumnService(
			container: $container,
			membership: $this->createMock(ProjectMembershipService::class),
			settings: $this->createMock(SettingsService::class),
			logger: $logger,
			appManager: $this->createMock(IAppManager::class)
		);

		return new BoardExportImportService(mapper: new BoardExportMapper(), columns: $columns, users: $users, container: $container, logger: $logger);
	}//end service()

	/**
	 * A sample export as an upload.
	 *
	 * @param string $name The fixture file name.
	 *
	 * @return array{name: string, content: string}
	 */
	private static function upload(string $name): array {
		return ['name' => $name, 'content' => (string)file_get_contents(__DIR__ . '/../../fixtures/board-import/' . $name)];
	}//end upload()

	/**
	 * The saved objects of one schema, in save order.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function saved(string $schema): array {
		$out = [];
		foreach ($this->objects->saves as $save) {
			if ($save['schema'] === $schema) {
				self::assertSame('planninq', $save['register']);
				self::assertFalse($save['_rbac'], 'the admin-only import writes with RBAC off');
				$out[] = (array)$save['object'];
			}
		}

		return $out;
	}//end saved()

	/**
	 * Scenario "Import a Trello board": a project "Vergunningen" with three columns named after the lists,
	 * and every card a task with its title, description, assignee and due date.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testATrelloBoardBecomesAProject(): void {
		$result = $this->service()->import(tool: 'trello', file: self::upload('Vergunningen.json'), owner: 'carol');

		$projects = $this->saved('project');
		self::assertCount(1, $projects);
		self::assertSame(
			['title' => 'Vergunningen', 'description' => 'Aanvragen omgevingsvergunning', 'status' => 'active', 'owner' => 'carol', 'members' => ['carol'], 'metadata' => ['importedFrom' => 'trello']],
			$projects[0]
		);
		self::assertSame($result['project'], array_key_first($this->objects->rows['project']));

		$columns = $this->saved('column');
		self::assertSame(['Binnengekomen', 'In behandeling', 'Besloten'], array_column($columns, 'title'));
		self::assertSame(['open', 'in_progress', 'done'], array_column($columns, 'status'));
		self::assertSame([$result['project']], array_values(array_unique(array_column($columns, 'project'))));
		$columnIds = array_keys($this->objects->rows['column']);

		$tasks = $this->saved('task');
		self::assertSame(['Dakkapel Kerkstraat 4', 'Uitbouw Molenweg 12', 'Kap boom Parklaan'], array_column($tasks, 'title'));
		self::assertSame($columnIds, array_column($tasks, 'column'));
		self::assertSame(['open', 'in_progress', 'done'], array_column($tasks, 'status'));
		self::assertSame('ada', $tasks[0]['assignedTo']);
		self::assertSame('2026-12-01', $tasks[0]['dueDate']);
		self::assertSame('Tekening ontbreekt', $tasks[0]['description']);
		self::assertArrayNotHasKey('assignedTo', $tasks[1], 'a Trello user with no account stays unassigned');
		self::assertSame(['importedFrom' => 'trello', 'sourceId' => 'c3'], $tasks[2]['metadata']);

		self::assertSame(3, $result['columns']);
		self::assertSame(3, $result['tasks']);
		self::assertContains('"Uitbouw Molenweg 12": trellobob has no account here, so the task is unassigned.', $result['losses']);
	}//end testATrelloBoardBecomesAProject()

	/**
	 * Scenario "Import a Jira project": the three statuses are the columns, and each issue sits in the
	 * column of its status, in file order.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testAJiraExportBecomesAProject(): void {
		$result = $this->service()->import(tool: 'jira-csv', file: self::upload('jira-export.csv'), owner: 'carol');

		self::assertSame(['To Do', 'In Progress', 'Done'], array_column($this->saved('column'), 'title'));
		[$todo, $doing, $done] = array_keys($this->objects->rows['column']);

		$tasks = $this->saved('task');
		self::assertSame([$done, $doing, $todo, $todo], array_column($tasks, 'column'));
		self::assertSame(['done', 'in_progress', 'open', 'open'], array_column($tasks, 'status'));
		self::assertSame([0, 0, 0, 1], array_column($tasks, 'columnOrder'), 'issues keep their file order within a column');
		self::assertSame('high', $tasks[0]['priority']);
		self::assertSame(4, $result['tasks']);
	}//end testAJiraExportBecomesAProject()

	/**
	 * The available imports are the shipped configurations.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testTheShippedImportsAreListed(): void {
		self::assertSame(
			[
				['id' => 'jira-csv', 'label' => 'Jira project (CSV export)', 'format' => 'csv'],
				['id' => 'trello', 'label' => 'Trello board (JSON export)', 'format' => 'json'],
			],
			$this->service()->tools()
		);
	}//end testTheShippedImportsAreListed()

	/**
	 * An unknown import, an export without columns and one without a project name write nothing.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testARefusedImportWritesNothing(): void {
		$service = $this->service();
		$cases   = [
			'unknown tool' => ['asana', self::upload('Vergunningen.json')],
			'no columns'   => ['trello', ['name' => 'leeg.json', 'content' => '{"name": "Leeg", "lists": [], "cards": []}']],
			'no project'   => ['trello', ['name' => 'naamloos.json', 'content' => '{"lists": [{"id": "l", "name": "A"}]}']],
		];
		foreach ($cases as $case => [$tool, $file]) {
			try {
				$service->import(tool: $tool, file: $file, owner: 'carol');
				self::fail($case . ' was imported');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage(), $case);
			}
		}

		self::assertSame([], $this->objects->saves);
	}//end testARefusedImportWritesNothing()

}//end class
