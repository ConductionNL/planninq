<?php

/**
 * Golden test for the writes that run with OpenRegister's RBAC off.
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

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';
require_once __DIR__ . '/../Support/InMemoryTimetableObjectService.php';
require_once __DIR__ . '/../Support/MembershipFixture.php';

use OCA\Planninq\Service\BoardColumnService;
use OCA\Planninq\Service\DependencyGraph;
use OCA\Planninq\Service\DependencyRepository;
use OCA\Planninq\Service\DependencyService;
use OCA\Planninq\Service\ProjectHealthService;
use OCA\Planninq\Service\SettingsService;
use OCA\Planninq\Service\TimetableSessionService;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use OCA\Planninq\Tests\Unit\Support\InMemoryTimetableObjectService;
use OCA\Planninq\Tests\Unit\Support\MembershipFixture;
use OCP\App\IAppManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;

/**
 * Records what TimetableSessionService::save(), ProjectHealthService::refresh(),
 * BoardColumnService::assignColumns() and DependencyService::createImported()
 * return, write and log, for each answer OpenRegister can give.
 *
 * @covers \OCA\Planninq\Service\TimetableSessionService
 * @covers \OCA\Planninq\Service\ProjectHealthService
 * @covers \OCA\Planninq\Service\BoardColumnService
 * @covers \OCA\Planninq\Service\DependencyService
 * @uses   \OCA\Planninq\Service\TimetableSessionRows
 * @uses   \OCA\Planninq\Service\TimetableRowRules
 * @uses   \OCA\Planninq\Service\TimetableSessionQuery
 * @uses   \OCA\Planninq\Service\ProjectMembershipService
 * @uses   \OCA\Planninq\Service\DependencyRepository
 * @uses   \OCA\Planninq\Service\DependencyGraph
 * @uses   \OCA\Planninq\Service\ReadsObjectRowId
 */
class SystemWriteGoldenTest extends TestCase {
	use MembershipFixture;

	private const FIXTURE = __DIR__ . '/fixtures/system-write.golden.json';

	private const PROJECT = '11111111-1111-4111-8111-111111111111';

	/**
	 * Messages the services logged in the current case.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $logged = [];

	/**
	 * The timetable writes: create, update and publish, for each save answer.
	 *
	 * @return void
	 */
	public function testTimetableSavesMatchTheRecording(): void {
		$recorded = [];
		foreach (['ok', 'throw', 'null', 'noid', 'scalarid'] as $mode) {
			$objects = $this->timetableObjects(mode: $mode);
			$service = $this->timetableService(objects: $objects);

			$create = $service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->lesson(ref: 'zm-1', room: 'A1')]);
			$objects->seed(schema: 'timetableSession', id: 'stored-2', data: $this->stored(ref: 'zm-2', status: 'scheduled'));
			$update = $service->upsert(sourceSystem: 'roster-zermelo', sessions: [$this->lesson(ref: 'zm-2', room: 'B2')]);
			$objects->seed(schema: 'timetableSession', id: 'draft-3', data: $this->stored(ref: 'zm-3', status: 'draft'));
			$publish = $service->publish(sourceSystem: 'roster-zermelo', from: '2026-09-28T00:00:00+02:00', to: '2026-09-28T23:59:59+02:00');

			$recorded[$mode] = [
				'create'  => $create,
				'update'  => $update,
				'publish' => $publish,
				'saves'   => array_map(static fn (array $save): array => [$save['uuid'], $save['rbac']], $objects->saves),
				'logged'  => $this->logged,
			];
			$this->logged = [];
		}

		$this->assertCases(section: 'timetable', recorded: $recorded);
	}//end testTimetableSavesMatchTheRecording()

	/**
	 * The health copy onto a project: missing, unchanged, copied and refused.
	 *
	 * @return void
	 */
	public function testHealthRefreshMatchesTheRecording(): void {
		$cases = [
			'missing-project' => ['project' => null, 'saving' => ['statusTime' => 'atRisk', 'reportDate' => '2026-09-28'], 'fail' => false],
			'copied'          => ['project' => ['title' => 'P', 'owner' => 'carol'], 'saving' => ['statusTime' => 'offTrack', 'reportDate' => '2026-09-28'], 'fail' => false],
			'deleted-report'  => ['project' => ['title' => 'P', 'healthTime' => 'atRisk'], 'saving' => null, 'fail' => false],
			'save-refused'    => ['project' => ['title' => 'P'], 'saving' => ['statusMoney' => 'atRisk', 'reportDate' => '2026-09-28'], 'fail' => true],
		];

		$recorded = [];
		foreach ($cases as $name => $case) {
			$this->objects = new InMemoryObjectService();
			if ($case['project'] !== null) {
				$this->objects->seed('project', self::PROJECT, $case['project']);
			}

			if ($case['fail'] === true) {
				$this->objects->failingSaves = [self::PROJECT];
			}

			$health = new ProjectHealthService(membership: $this->membershipService(), container: $this->container(), logger: $this->logger());
			$result = $health->refresh(projectId: self::PROJECT, reportId: 'rep-1', saving: $case['saving']);
			$again  = $health->refresh(projectId: self::PROJECT, reportId: 'rep-1', saving: $case['saving']);

			$recorded[$name] = [
				'result' => $result,
				'again'  => $again,
				'saves'  => $this->saves(objects: $this->objects),
				'stored' => ($this->objects->rows['project'][self::PROJECT] ?? null),
				'logged' => $this->logged,
			];
			$this->logged = [];
		}

		$this->assertCases(section: 'health', recorded: $recorded);
	}//end testHealthRefreshMatchesTheRecording()

	/**
	 * Column assignment: no columns yet, existing columns, OpenRegister missing, and tasks that are skipped.
	 *
	 * @return void
	 */
	public function testAssignColumnsMatchesTheRecording(): void {
		$recorded = [];
		foreach (['fresh' => false, 'existing' => true] as $name => $withColumns) {
			foreach ([true, false] as $installed) {
				$this->objects = $this->creatingObjects();
				$this->objects->seed('project', self::PROJECT, ['title' => 'P', 'owner' => 'carol', 'members' => ['carol']]);
				if ($withColumns === true) {
					$this->objects->seed('column', 'col-b', ['title' => 'Later', 'order' => 2, 'project' => self::PROJECT, 'type' => 'done']);
					$this->objects->seed('column', 'col-a', ['title' => 'First', 'order' => 1, 'project' => self::PROJECT, 'status' => 'in_progress']);
					$this->objects->seed('column', 'col-c', ['title' => 'Review', 'order' => 3, 'project' => self::PROJECT, 'status' => 'review']);
					$this->objects->seed('column', '', ['title' => 'No id', 'order' => 0, 'project' => self::PROJECT, 'type' => 'done']);
				}

				foreach (['open', 'in_progress', 'blocked', 'done', 'cancelled', 'review'] as $i => $status) {
					$this->objects->seed('task', 'task-' . $status, ['title' => 'T' . $i, 'status' => $status, 'project' => self::PROJECT]);
				}

				$this->objects->seed('task', 'task-placed', ['title' => 'Placed', 'status' => 'open', 'project' => self::PROJECT, 'column' => 'col-x']);
				$this->objects->seed('task', 'task-nostatus', ['title' => 'No status', 'project' => self::PROJECT]);

				$result = $this->boardService(installed: $installed)->assignColumns(projectId: self::PROJECT);

				$recorded[$name . '-' . ($installed === true ? 'installed' : 'missing')] = [
					'result' => $result,
					'saves'  => $this->saves(objects: $this->objects),
					'tasks'  => array_map(static fn (array $task): ?string => ($task['column'] ?? null), $this->objects->rows['task']),
				];
			}
		}

		$this->assertCases(section: 'columns', recorded: $recorded);
	}//end testAssignColumnsMatchesTheRecording()

	/**
	 * Imported dependency links: new, existing, blank, self, cyclic and related.
	 *
	 * @return void
	 */
	public function testCreateImportedMatchesTheRecording(): void {
		$links = [
			['blocker' => 't1', 'blocked' => 't2', 'type' => 'blocks'],
			['blocker' => 't1', 'blocked' => 't2', 'type' => 'blocks'],
			['blocker' => 't2', 'blocked' => 't3', 'type' => 'blocks'],
			['blocker' => 't3', 'blocked' => 't1', 'type' => 'blocks'],
			['blocker' => 't3', 'blocked' => 't1', 'type' => 'relates'],
			['blocker' => '', 'blocked' => 't1', 'type' => 'blocks'],
			['blocker' => 't4', 'blocked' => 't4', 'type' => 'relates'],
			['blocker' => 't4', 'blocked' => 't1', 'type' => 'blocks'],
			['blocker' => 't1', 'blocked' => 't4', 'type' => 'blocks'],
			['blocker' => 't4', 'blocked' => '', 'type' => 'blocks'],
		];

		$recorded = [];
		foreach (['empty' => [], 'stored' => [['blocker' => 't2', 'blocked' => 't4', 'type' => 'blocks']]] as $name => $stored) {
			$this->objects = new InMemoryObjectService();
			foreach (['t1', 't2', 't3', 't4'] as $task) {
				$this->objects->seed('task', $task, ['title' => $task, 'project' => self::PROJECT]);
			}

			foreach ($stored as $i => $edge) {
				$this->objects->seed('dependency', 'dep-' . $i, $edge + ['project' => self::PROJECT]);
			}

			$result = $this->dependencyService()->createImported(projectId: self::PROJECT, links: $links);

			$recorded[$name] = [
				'result' => $result,
				'saves'  => $this->saves(objects: $this->objects),
				'logged' => $this->logged,
			];
			$this->logged = [];
		}

		$this->assertCases(section: 'dependencies', recorded: $recorded);
	}//end testCreateImportedMatchesTheRecording()

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

	/**
	 * The writes the in-memory OpenRegister received, without the volatile import time.
	 *
	 * @param InMemoryObjectService $objects The in-memory OpenRegister.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function saves(InMemoryObjectService $objects): array {
		return array_map(
			static function (array $save): array {
				unset($save['register']);
				return $save;
			},
			$objects->saves
		);
	}//end saves()

	/**
	 * A logger that keeps what it is told in $this->logged.
	 *
	 * @return AbstractLogger
	 */
	private function logger(): AbstractLogger {
		$logged = &$this->logged;
		return new class ($logged) extends AbstractLogger {
			/** @var array<int,array<string,mixed>> */
			private array $logged;

			public function __construct(array &$logged) {
				$this->logged = &$logged;
			}

			public function log($level, string|Stringable $message, array $context = []): void {
				$this->logged[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
			}
		};
	}//end logger()

	/**
	 * An in-memory timetable OpenRegister whose saves answer as the mode says.
	 *
	 * @param string $mode ok, throw, null, noid (an entity without an id) or scalarid (a row with an id).
	 *
	 * @return InMemoryTimetableObjectService
	 */
	private function timetableObjects(string $mode): InMemoryTimetableObjectService {
		return new class ($mode) extends InMemoryTimetableObjectService {
			public function __construct(private string $mode) {
			}

			public function saveObject(
				array|object $object,
				?array $extend = [],
				mixed $register = null,
				mixed $schema = null,
				?string $uuid = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $silent = false,
				bool $_validation = true,
			): ?object {
				if ($this->mode === 'throw') {
					throw new RuntimeException('save refused');
				}

				$saved = parent::saveObject($object, $extend, $register, $schema, $uuid, $_rbac, $_multitenancy, $silent, $_validation);
				return match ($this->mode) {
					'null' => null,
					'noid' => new class () {
						public function getUuid(): string {
							return '';
						}
					},
					'scalarid' => new class () {
						public function getId(): int {
							return 77;
						}
					},
					default => $saved,
				};
			}
		};
	}//end timetableObjects()

	/**
	 * The timetable service over an in-memory OpenRegister.
	 *
	 * @param InMemoryTimetableObjectService $objects The in-memory OpenRegister.
	 *
	 * @return TimetableSessionService
	 */
	private function timetableService(InMemoryTimetableObjectService $objects): TimetableSessionService {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);

		return new TimetableSessionService(container: $container, logger: $this->logger());
	}//end timetableService()

	/**
	 * One delivered lesson.
	 *
	 * @param string $ref  The occurrence id.
	 * @param string $room The room.
	 *
	 * @return array<string,string>
	 */
	private function lesson(string $ref, string $room): array {
		return [
			'externalRef'    => $ref,
			'subject'        => 'Wiskunde',
			'startsAt'       => '2026-09-28T09:00:00+02:00',
			'endsAt'         => '2026-09-28T09:50:00+02:00',
			'groupReference' => '3a',
			'roomReference'  => $room,
		];
	}//end lesson()

	/**
	 * One stored lesson.
	 *
	 * @param string $ref    The occurrence id.
	 * @param string $status The stored status.
	 *
	 * @return array<string,string>
	 */
	private function stored(string $ref, string $status): array {
		return [
			'externalRef'    => $ref,
			'sourceSystem'   => 'roster-zermelo',
			'subject'        => 'Wiskunde',
			'title'          => 'Wiskunde',
			'startsAt'       => '2026-09-28T09:00:00+02:00',
			'endsAt'         => '2026-09-28T09:50:00+02:00',
			'groupReference' => '3a',
			'roomReference'  => 'A1',
			'status'         => $status,
		];
	}//end stored()

	/**
	 * An in-memory OpenRegister that gives a create a uuid of its own.
	 *
	 * @return InMemoryObjectService
	 */
	private function creatingObjects(): InMemoryObjectService {
		return new class () extends InMemoryObjectService {
			private int $next = 0;

			public function saveObject(
				array|object $object,
				?array $extend = [],
				mixed $register = null,
				mixed $schema = null,
				?string $uuid = null,
				bool $_rbac = true,
				bool $_multitenancy = true,
				bool $silent = false,
				bool $_validation = true,
			): ?object {
				if ($uuid === null) {
					$this->next++;
					$uuid = sprintf('00000000-0000-4000-8000-%012d', $this->next);
				}

				return parent::saveObject($object, $extend, $register, $schema, $uuid, $_rbac, $_multitenancy, $silent, $_validation);
			}
		};
	}//end creatingObjects()

	/**
	 * The column service, with or without OpenRegister installed.
	 *
	 * @param bool $installed Whether OpenRegister is installed.
	 *
	 * @return BoardColumnService
	 */
	private function boardService(bool $installed): BoardColumnService {
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('getAdminSettings')->willReturn(['default_columns' => '["Intake","Work","Closed"]']);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn($installed);

		return new BoardColumnService(
			container: $this->container(),
			membership: $this->membershipService(),
			settings: $settings,
			logger: new NullLogger(),
			appManager: $appManager
		);
	}//end boardService()

	/**
	 * The dependency service over the fixture container.
	 *
	 * @return DependencyService
	 */
	private function dependencyService(): DependencyService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new DependencyService(
			repository: new DependencyRepository(container: $this->container(), logger: $this->logger(), appManager: $appManager),
			graph: new DependencyGraph(),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			logger: $this->logger()
		);
	}//end dependencyService()
}//end class
