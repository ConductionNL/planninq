<?php

/**
 * Golden test for the task calendar export switch, its backfill and the task read.
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/CalendarExportFixture.php';

use JsonSerializable;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Service\TaskCalendarTaskStore;
use OCA\Planninq\Tests\Unit\Support\CalendarExportFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Records apply(), backfill() and TaskCalendarTaskStore::tasksOf() over a set of inputs.
 *
 * @covers \OCA\Planninq\Service\TaskCalendarExportService
 * @covers \OCA\Planninq\Service\TaskCalendarTaskStore
 * @uses   \OCA\Planninq\Service\TaskVtodoBuilder
 */
class TaskCalendarExportGoldenTest extends TestCase {
	use CalendarExportFixture;

	private const FIXTURE = __DIR__ . '/fixtures/task-calendar-export.golden.json';

	/**
	 * The switch: every posted value against a switch that was on or off.
	 *
	 * @return void
	 */
	public function testApplyMatchesTheRecording(): void {
		$posted = ['missing' => null, 'true' => 'true', 'false' => 'false', 'yes' => 'yes', 'off' => 'off', 'zero' => '0', 'one' => '1', 'empty' => '', 'bool-true' => true, 'bool-false' => false, 'maybe' => 'maybe', 'null' => null];
		$recorded = [];
		foreach ([false, true] as $was) {
			foreach ($posted as $name => $value) {
				$export = $this->makeExport(on: ($was === true ? ['ann'] : []));
				$this->backend->createCalendar('principals/users/ann', 'planninq', []);
				$data = ['other' => 'x'];
				if ($name !== 'missing') {
					$data[TaskCalendarExportService::SWITCH_KEY] = $value;
				}

				$export->apply(userId: 'ann', data: $data);
				$recorded[($was === true ? 'was-on' : 'was-off') . ' / ' . $name] = [
					'stored' => ($this->userValues['ann'][TaskCalendarExportService::SWITCH_KEY] ?? '(unset)'),
					'queued' => $this->queued,
					'lists'  => array_keys($this->backend->lists),
				];
			}
		}

		$this->assertCases(section: 'apply', recorded: $recorded);
	}//end testApplyMatchesTheRecording()

	/**
	 * The backfill: who it runs for and which tasks it writes.
	 *
	 * @return void
	 */
	public function testBackfillMatchesTheRecording(): void {
		$tasks = [
			't-1' => ['title' => 'Own', 'assignedTo' => 'ann', 'project' => 'p-1'],
			't-2' => ['title' => 'Shared', 'sharedWith' => ['bob', 'ann'], 'project' => 'p-1'],
			't-3' => ['title' => 'Other', 'assignedTo' => 'bob', 'project' => 'p-1'],
			't-4' => ['title' => 'Stored uid', 'assignedTo' => 'ann', 'calendarEventUid' => 'kept@x'],
		];
		$cases = [
			'on'        => ['user' => 'ann', 'on' => ['ann'], 'dav' => true],
			'off'       => ['user' => 'ann', 'on' => [], 'dav' => true],
			'no-dav'    => ['user' => 'ann', 'on' => ['ann'], 'dav' => false],
			'no-user'   => ['user' => '', 'on' => ['ann'], 'dav' => true],
			'bob'       => ['user' => 'bob', 'on' => ['bob'], 'dav' => true],
		];

		$recorded = [];
		foreach ($cases as $name => $case) {
			$export  = $this->makeExport(on: $case['on'], withDav: $case['dav'], assigned: $tasks);
			$written = $export->backfill(userId: $case['user']);
			$present = [];
			foreach (array_keys($tasks) as $taskId) {
				$present[$taskId] = ($this->vtodo(user: $case['user'], taskId: $taskId) !== null);
			}

			$recorded[$name] = [
				'written'  => $written,
				'present'  => $present,
				'searches' => count($this->objects->searches),
				'uids'     => array_map(static fn (array $save): array => [$save['uuid'], $save['object']['calendarEventUid'] ?? null], $this->objects->saved),
			];
		}

		$this->assertCases(section: 'backfill', recorded: $recorded);
	}//end testBackfillMatchesTheRecording()

	/**
	 * The task read: every answer shape OpenRegister can give.
	 *
	 * @return void
	 */
	public function testTasksOfMatchesTheRecording(): void {
		$entity = new class () {
			public function getUuid(): string {
				return 'e-1';
			}

			public function getObject(): array {
				return ['title' => 'Entity', 'id' => 'ignored'];
			}
		};
		$serialised = new class () implements JsonSerializable {
			public function jsonSerialize(): array {
				return ['title' => 'Serialised', '@self' => ['id' => 's-1']];
			}
		};
		$nullUuid = new class () {
			public function getUuid(): ?string {
				return null;
			}

			public function getObject(): array {
				return ['title' => 'No uuid', 'id' => 'n-1'];
			}
		};
		$answers = [
			'results'    => ['results' => [['id' => 'r-1', 'title' => 'A'], ['@self' => ['id' => 'r-2'], 'title' => 'B'], ['title' => 'No id'], ['id' => 7, 'title' => 'Int id'], ['id' => ['x'], 'title' => 'List id']]],
			'list'       => [['id' => 'l-1', 'title' => 'A'], ['id' => '', '@self' => ['id' => 'l-2']]],
			'entities'   => [$entity, $serialised, $nullUuid],
			'results-entities' => ['results' => [$entity]],
			'empty'      => [],
			'count'      => 3,
			'no-results' => ['total' => 0],
			'throws'     => new RuntimeException('search down'),
		];

		$recorded = [];
		foreach ($answers as $name => $answer) {
			$search = new class ($answer) {
				public function __construct(private mixed $answer) {
				}

				public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): mixed {
					if ($this->answer instanceof RuntimeException) {
						throw $this->answer;
					}

					return $this->answer;
				}
			};
			$container = $this->createMock(originalClassName: ContainerInterface::class);
			$container->method('get')->willReturn($search);
			$logged = [];
			$logger = $this->createMock(originalClassName: LoggerInterface::class);
			$logger->method('warning')->willReturnCallback(static function (string $message, array $context = []) use (&$logged): void {
				$logged[] = $message . ' ' . json_encode($context);
			});

			$store = new TaskCalendarTaskStore($container, $logger);
			$recorded[$name] = ['tasks' => $store->tasksOf(userId: 'ann'), 'logged' => $logged];
		}

		$this->assertCases(section: 'tasksOf', recorded: $recorded);
	}//end testTasksOfMatchesTheRecording()

	/**
	 * Compare each case with the recording, or write the recording when asked.
	 *
	 * @param string              $section  The section of the fixture.
	 * @param array<string,mixed> $recorded The cases of this run.
	 *
	 * @return void
	 */
	private function assertCases(string $section, array $recorded): void {
		$fixture = [];
		if (is_file(self::FIXTURE) === true) {
			$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		}

		$actual = json_decode((string)json_encode($recorded), true);
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			$fixture[$section] = $actual;
			ksort($fixture);
			file_put_contents(self::FIXTURE, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		self::assertSame(expected: array_keys($fixture[$section]), actual: array_keys($actual));
		foreach ($actual as $case => $value) {
			self::assertSame(expected: $fixture[$section][$case], actual: $value, message: $section . ' / ' . $case);
		}
	}//end assertCases()
}//end class
