<?php

/**
 * Tests for BoardExportMapper over the shipped Trello and Jira mapping configurations.
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

use InvalidArgumentException;
use OCA\Planninq\Service\BoardExportMapper;
use PHPUnit\Framework\TestCase;

/**
 * Each sample export maps to a project, its columns and its tasks.
 *
 * @covers \OCA\Planninq\Service\BoardExportMapper
 */
class BoardExportMapperTest extends TestCase {

	/**
	 * A shipped mapping configuration.
	 *
	 * @param string $id The configuration file name without extension.
	 *
	 * @return array<string,mixed>
	 */
	private static function config(string $id): array {
		return json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/board-import/' . $id . '.json'), true, 512, JSON_THROW_ON_ERROR);
	}//end config()

	/**
	 * A sample export.
	 *
	 * @param string $name The fixture file name.
	 *
	 * @return string
	 */
	private static function sample(string $name): string {
		return (string)file_get_contents(__DIR__ . '/../../fixtures/board-import/' . $name);
	}//end sample()

	/**
	 * Scenario "Import a Trello board": the board becomes a project, its open lists the columns, its open cards the tasks.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testATrelloBoardMapsToAProjectColumnsAndTasks(): void {
		$plan = (new BoardExportMapper())->map(config: self::config('trello'), content: self::sample('Vergunningen.json'), fileName: 'Vergunningen.json');

		self::assertSame(['title' => 'Vergunningen', 'description' => 'Aanvragen omgevingsvergunning'], $plan['project']);
		self::assertSame(
			[['key' => 'l1', 'title' => 'Binnengekomen'], ['key' => 'l2', 'title' => 'In behandeling'], ['key' => 'l3', 'title' => 'Besloten']],
			$plan['columns']
		);
		self::assertSame(
			[
				['column' => 'l1', 'sourceId' => 'c1', 'data' => ['title' => 'Dakkapel Kerkstraat 4', 'description' => 'Tekening ontbreekt', 'dueDate' => '2026-12-01'], 'users' => ['assignedTo' => 'ada']],
				['column' => 'l2', 'sourceId' => 'c2', 'data' => ['title' => 'Uitbouw Molenweg 12'], 'users' => ['assignedTo' => 'trellobob']],
				['column' => 'l3', 'sourceId' => 'c3', 'data' => ['title' => 'Kap boom Parklaan', 'description' => 'Advies ecoloog binnen', 'dueDate' => '2026-11-20'], 'users' => []],
			],
			$plan['tasks']
		);
		self::assertSame(
			[
				'row 5: its list or status "lx" is not imported, so it was left out.',
				'row 6: no title, so it was left out.',
			],
			$plan['losses']
		);
	}//end testATrelloBoardMapsToAProjectColumnsAndTasks()

	/**
	 * Scenario "Import a Jira project": the statuses become columns in workflow order, every issue a task in its status.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testAJiraCsvMapsToAProjectColumnsAndTasks(): void {
		$plan = (new BoardExportMapper())->map(config: self::config('jira-csv'), content: self::sample('jira-export.csv'), fileName: 'jira-export.csv');

		self::assertSame(['title' => 'Vergunningen'], $plan['project']);
		self::assertSame(['To Do', 'In Progress', 'Done'], array_column($plan['columns'], 'title'));
		self::assertSame(['Done', 'In Progress', 'To Do', 'To Do'], array_column($plan['tasks'], 'column'));
		self::assertSame(['VERG-1', 'VERG-2', 'VERG-3', 'VERG-4'], array_column($plan['tasks'], 'sourceId'));
		self::assertSame(
			['title' => 'Loket koppelen', 'description' => 'Koppeling met het loket, getest', 'dueDate' => '2026-11-01', 'priority' => 'high'],
			$plan['tasks'][0]['data']
		);
		self::assertSame(['assignedTo' => 'ada'], $plan['tasks'][0]['users']);
		self::assertSame("Twee regels\nbeschrijving", $plan['tasks'][1]['data']['description'], 'a quoted field keeps its line break');
		self::assertSame(['title' => 'Testplan schrijven', 'dueDate' => '2026-12-15', 'priority' => 'low'], $plan['tasks'][2]['data']);
		self::assertSame(['title' => 'Release plannen'], $plan['tasks'][3]['data']);
		self::assertSame(
			[
				'row 4 dueDate: "not a date" is not a date this import reads, so it was left empty.',
				'row 4 priority: "Blocker" has no counterpart, so it was left empty.',
			],
			$plan['losses']
		);
	}//end testAJiraCsvMapsToAProjectColumnsAndTasks()

	/**
	 * Without a project name column the file name is the project title.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testTheFileNameNamesAProjectTheExportDoesNot(): void {
		$plan = (new BoardExportMapper())->map(config: self::config('jira-csv'), content: "Summary,Status\nEen,Klaar\n", fileName: '/tmp/Wegen 2027.csv');

		self::assertSame(['title' => 'Wegen 2027'], $plan['project']);
		self::assertSame([['key' => 'Klaar', 'title' => 'Klaar']], $plan['columns'], 'a status outside the preferred order still becomes a column');
	}//end testTheFileNameNamesAProjectTheExportDoesNot()

	/**
	 * A file that is not the configured format, or a format nobody configured, is refused.
	 *
	 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.1
	 */
	public function testUnreadableExportsAreRefused(): void {
		$mapper = new BoardExportMapper();
		foreach ([
			'not json'      => [self::config('trello'), '{"name": '],
			'not a board'   => [self::config('trello'), '"just a string"'],
			'no csv header' => [self::config('jira-csv'), ''],
			'unknown'       => [['format' => 'xml'], '<board/>'],
		] as $case => [$config, $content]) {
			try {
				$mapper->map(config: $config, content: $content, fileName: 'x');
				self::fail($case . ' was accepted');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage(), $case);
			}
		}
	}//end testUnreadableExportsAreRefused()

}//end class
