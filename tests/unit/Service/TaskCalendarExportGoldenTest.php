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
use OCA\OpenRegister\Kernel\Contracts\RegisterSchemaRef;
use OCA\Planninq\Service\TaskCalendarExportService;
use OCA\Planninq\Service\TaskCalendarTaskStore;
use OCA\Planninq\Service\TaskVtodoBuilder;
use OCA\Planninq\Tests\Unit\Support\CalendarExportFixture;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
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

	private const DAV_BACKEND = 'OCA\\DAV\\CalDAV\\CalDavBackend';

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
			'no-user-switched-on' => ['user' => '', 'on' => [''], 'dav' => true],
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

				public function searchObjectsBySlug(RegisterSchemaRef $target, array $filters = [], bool $_rbac = true, bool $_multitenancy = true): mixed {
					$registerSlug = (string)$target->register;
					$schemaSlug   = (string)$target->schema;
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
	 * Export one task to its people's task lists: written count, lists, objects (stamp masked), stored uid.
	 *
	 * @return void
	 */
	public function testExportTaskMatchesTheRecording(): void {
		$task  = ['title' => 'Draft', 'status' => 'open', 'project' => 'p-1', 'dueDate' => '2026-11-10', 'assignedTo' => 'ann'];
		$cases = [
			'one on'             => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task],
			'two on'             => ['users' => ['ann', 'bob'], 'on' => ['ann', 'bob'], 'task' => $task],
			'one of two on'      => ['users' => ['ann', 'bob'], 'on' => ['bob'], 'task' => $task],
			'none on'            => ['users' => ['ann', 'bob'], 'on' => [], 'task' => $task],
			'no users'           => ['users' => [], 'on' => ['ann'], 'task' => $task],
			'no dav'             => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'dav' => false],
			'no task id'         => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'id' => ''],
			'stored uid'         => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['calendarEventUid' => 'kept@x'] + $task],
			'stored blank uid'   => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['calendarEventUid' => ''] + $task],
			'stored number uid'  => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['calendarEventUid' => 5] + $task],
			'stored own uid'     => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['calendarEventUid' => 'planninq-task-t 1@inst'] + $task],
			'no project'         => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['project' => ''] + $task],
			'project ref'        => ['users' => ['ann'], 'on' => ['ann'], 'task' => ['project' => ['id' => 'p/2']] + $task],
			'list exists'        => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'list' => true],
			'object exists'      => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'list' => true, 'object' => true],
			'dav fails'          => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'fail' => true],
			'repeated user'      => ['users' => ['ann', 'ann'], 'on' => ['ann'], 'task' => $task],
			'list not made'      => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'noList' => true],
			'list without id'    => ['users' => ['ann'], 'on' => ['ann'], 'task' => $task, 'list' => true, 'idless' => true],
		];

		$recorded = [];
		foreach ($cases as $name => $case) {
			$export = $this->makeExport(on: $case['on'], withDav: ($case['dav'] ?? true));
			$taskId = ($case['id'] ?? 't 1');
			$this->prepare(case: $case, taskId: $taskId);
			try {
				$result = $export->exportTask(taskId: $taskId, task: $case['task'], users: $case['users']);
			} catch (RuntimeException $e) {
				$result = 'threw: ' . $e->getMessage();
			}

			$recorded[$name] = ['result' => $result] + $this->davState();
		}

		$this->assertCases(section: 'exportTask', recorded: $recorded);
	}//end testExportTaskMatchesTheRecording()

	/**
	 * Remove one task from its people's task lists.
	 *
	 * @return void
	 */
	public function testRemoveTaskMatchesTheRecording(): void {
		$cases = [
			'present'         => ['users' => ['ann'], 'list' => true, 'object' => true],
			'two users'       => ['users' => ['ann', 'bob'], 'list' => true, 'object' => true],
			'no object'       => ['users' => ['ann'], 'list' => true],
			'no list'         => ['users' => ['ann']],
			'no users'        => ['users' => [], 'list' => true, 'object' => true],
			'no dav'          => ['users' => ['ann'], 'list' => true, 'object' => true, 'dav' => false],
			'no task id'      => ['users' => ['ann'], 'list' => true, 'object' => true, 'id' => ''],
			'switched off'    => ['users' => ['ann'], 'list' => true, 'object' => true, 'on' => []],
			'list without id' => ['users' => ['ann'], 'list' => true, 'object' => true, 'idless' => true],
		];

		$recorded = [];
		foreach ($cases as $name => $case) {
			$export = $this->makeExport(on: ($case['on'] ?? ['ann', 'bob']), withDav: ($case['dav'] ?? true));
			$taskId = ($case['id'] ?? 't 1');
			$this->prepare(case: $case + ['users' => ['ann', 'bob']], taskId: $taskId);
			$this->prepare(case: $case + ['users' => ['ann', 'bob']], taskId: 'other');
			$export->removeTask(taskId: $taskId, users: $case['users']);
			$recorded[$name] = $this->davState();
		}

		$this->assertCases(section: 'removeTask', recorded: $recorded);
	}//end testRemoveTaskMatchesTheRecording()

	/**
	 * The DAV backend is not there when its class is missing, as in this unit run.
	 *
	 * @return void
	 */
	public function testDavBackendWithoutTheDavAppMatchesTheRecording(): void {
		if (class_exists(self::DAV_BACKEND) === true) {
			self::markTestSkipped('The DAV app is present, so its class cannot be missing in this run.');
		}

		$recorded = ['class missing' => $this->resolveBackend(answer: $this->newDavDouble(missing: ''))];

		$this->assertCases(section: 'davBackend without dav', recorded: $recorded);
	}//end testDavBackendWithoutTheDavAppMatchesTheRecording()

	/**
	 * The DAV backend as the container answers it, with the DAV app's class present.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState  disabled
	 *
	 * @return void
	 */
	public function testDavBackendMatchesTheRecording(): void {
		if (class_exists(self::DAV_BACKEND) === false) {
			require_once __DIR__ . '/../../stubs/dav/CalDavBackend.php';
		}

		$recorded = [
			'complete'         => $this->resolveBackend(answer: $this->newDavDouble(missing: '')),
			'no calendar'      => $this->resolveBackend(answer: $this->newDavDouble(missing: 'getCalendarByUri')),
			'no delete object' => $this->resolveBackend(answer: $this->newDavDouble(missing: 'deleteCalendarObject')),
			'not an object'    => $this->resolveBackend(answer: 'backend'),
			'container fails'  => $this->resolveBackend(answer: new RuntimeException('no dav')),
		];

		$this->assertCases(section: 'davBackend', recorded: $recorded);
	}//end testDavBackendMatchesTheRecording()

	/**
	 * Seed task lists and objects for a case: a list per user, and the task's object in it.
	 *
	 * @param array<string,mixed> $case   The case.
	 * @param string              $taskId The task UUID.
	 *
	 * @return void
	 */
	private function prepare(array $case, string $taskId): void {
		$this->backend->fail = ($case['fail'] ?? false);
		foreach ($case['users'] as $user) {
			$principal = 'principals/users/' . $user;
			if (($case['list'] ?? false) === true && isset($this->backend->lists[$principal]) === false) {
				$this->backend->createCalendar($principal, 'planninq', []);
			}

			if (($case['object'] ?? false) === true && isset($this->backend->lists[$principal]) === true) {
				$this->backend->objects[$this->backend->lists[$principal]]['planninq-task-' . $taskId . '.ics'] = 'BEGIN:VCALENDAR old';
			}
		}

		$this->backend->created = [];
		$this->backend->noList  = ($case['noList'] ?? false);
		$this->backend->idless  = ($case['idless'] ?? false);
	}//end prepare()

	/**
	 * The DAV double's lists, created lists, objects (time stamps masked) and the saved uids.
	 *
	 * @return array<string,mixed>
	 */
	private function davState(): array {
		$objects = [];
		foreach ($this->backend->objects as $listId => $items) {
			foreach ($items as $uri => $ics) {
				$objects[$listId][$uri] = preg_replace('/\d{8}T\d{6}Z/', '(stamp)', (string)$ics);
			}
		}

		return [
			'lists'   => $this->backend->lists,
			'created' => $this->backend->created,
			'objects' => $objects,
			'saved'   => $this->objects->saved,
			'deleted' => $this->backend->deleted,
		];
	}//end davState()

	/**
	 * What the real davBackend() answers when the container gives $answer (an exception is thrown).
	 *
	 * @param mixed $answer The container's answer.
	 *
	 * @return array<string,mixed>
	 */
	private function resolveBackend(mixed $answer): array {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(static function () use ($answer): mixed {
			if ($answer instanceof RuntimeException) {
				throw $answer;
			}

			return $answer;
		});
		$logged = [];
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['debug', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(static function (string $message, array $context=[]) use (&$logged, $level): void {
				$logged[] = [$level, $message, $context];
			});
		}

		$export = new TaskCalendarExportService(
			config: $this->createMock(originalClassName: IConfig::class),
			container: $container,
			urls: $this->createMock(originalClassName: IURLGenerator::class),
			jobs: $this->createMock(originalClassName: IJobList::class),
			l10n: $this->createMock(originalClassName: IL10N::class),
			builder: new TaskVtodoBuilder(),
			store: new TaskCalendarTaskStore($container, $logger),
			logger: $logger
		);
		$backend = $export->davBackend();

		return ['same' => ($backend !== null && $backend === $answer), 'null' => ($backend === null), 'logged' => $logged];
	}//end resolveBackend()

	/**
	 * A DAV backend double with every method the export uses but one.
	 *
	 * @param string $missing The method it lacks: getCalendarByUri, deleteCalendarObject, or '' for none.
	 *
	 * @return object
	 */
	private function newDavDouble(string $missing): object {
		// phpcs:disable
		return match ($missing) {
			'getCalendarByUri' => new class {
				public function createCalendar() {} public function deleteCalendar() {} public function getCalendarObject() {}
				public function createCalendarObject() {} public function updateCalendarObject() {} public function deleteCalendarObject() {}
			},
			'deleteCalendarObject' => new class {
				public function getCalendarByUri() {} public function createCalendar() {} public function deleteCalendar() {}
				public function getCalendarObject() {} public function createCalendarObject() {} public function updateCalendarObject() {}
			},
			default => new class {
				public function getCalendarByUri() {} public function createCalendar() {} public function deleteCalendar() {} public function getCalendarObject() {}
				public function createCalendarObject() {} public function updateCalendarObject() {} public function deleteCalendarObject() {}
			},
		};
		// phpcs:enable
	}//end newDavDouble()

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
