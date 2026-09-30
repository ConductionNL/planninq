<?php

/**
 * Tests for a generator run: queueing snapshots the input, a step stores the
 * placements and asks for another step while the run improves, the run stops
 * at the time budget, and an error stores the run as failed with the reason.
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

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use InvalidArgumentException;
use OCA\Planninq\BackgroundJob\GenerateTimetableScenario;
use OCA\Planninq\Service\TimetableGenerationService;
use OCA\Planninq\Service\TimetableGridService;
use OCA\Planninq\Service\TimetableInputBuilder;
use OCA\Planninq\Service\TimetableScenarioStore;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use OCA\Planninq\Timetabling\LocalSearchSolver;
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\SolverResult;
use OCA\Planninq\Timetabling\TimetableSolver;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * The real store over an in-memory ObjectService, the real input builder over uploaded sheets, the real solver.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.2
 */
class TimetableGenerationServiceTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * App config values, in memory.
	 *
	 * @var array<string,string>
	 */
	private array $config = [];

	/**
	 * The fake ObjectService.
	 *
	 * @var object
	 */
	private object $objects;

	/**
	 * Uploaded sheets (3A and 3B, two teachers, two rooms), a hard wish, a small grid, a one-minute budget and a queued scenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->config  = [
			TimetableGridService::GRID_KEY   => '{"days":["mon","wed"],"periods":[{"start":"08:30","end":"09:20"},{"start":"09:20","end":"10:10"},{"start":"10:10","end":"11:00"}]}',
			TimetableGridService::BUDGET_KEY => '1',
		];
		$this->objects = new class {
			/**
			 * Stored objects by id.
			 *
			 * @var array<string,array<string,mixed>>
			 */
			public array $stored = [];

			/**
			 * Saved payloads, in order.
			 *
			 * @var array<int,array<string,mixed>>
			 */
			public array $saves = [];

			/**
			 * OpenRegister's find.
			 *
			 * @param string $id The id.
			 *
			 * @return array<string,mixed>|null
			 */
			public function find(string $id, mixed ...$rest): ?array {
				return ($this->stored[$id] ?? null);
			}

			/**
			 * OpenRegister's saveObject.
			 *
			 * @param array<string,mixed> $object The data.
			 *
			 * @return array<string,mixed>
			 */
			public function saveObject(array $object, mixed ...$rest): array {
				$this->saves[] = $object;
				$this->stored[(string)$rest['uuid']] = array_merge($object, ['id' => (string)$rest['uuid']]);
				return $this->stored[(string)$rest['uuid']];
			}

			/**
			 * OpenRegister's searchObjectsBySlug.
			 *
			 * @return array<string,mixed>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, mixed ...$rest): array {
				return ['results' => array_values(array_filter($this->stored, static fn (array $row): bool => isset($row['strength'])))];
			}
		};
		$this->objects->stored['w-1'] = ['id' => 'w-1', 'appliesTo' => 'teacher', 'reference' => 'klaas', 'kind' => 'unavailable', 'periods' => ['wed-3'], 'strength' => 'hard'];
		$this->objects->stored['s-1'] = [
			'id'         => 's-1',
			'title'      => 'First try',
			'source'     => 'generated',
			'weekOf'     => '2026-10-05',
			'windowFrom' => '2026-10-05',
			'windowTo'   => '2026-10-30',
			'status'     => 'done',
		];

		$this->builder()->storeUpload(
			rooms: [['reference' => 'r-1', 'capacity' => 30, 'type' => 'classroom'], ['reference' => 'r-2', 'capacity' => 30, 'type' => 'classroom']],
			activities: [
				['group' => '3A', 'subject' => 'English', 'teacher' => 'klaas', 'lessonsPerWeek' => 3, 'lessonLength' => 1, 'roomType' => 'classroom'],
				['group' => '3B', 'subject' => 'Maths', 'teacher' => 'noor', 'lessonsPerWeek' => 2, 'lessonLength' => 1, 'roomType' => 'classroom'],
			]
		);
	}//end setUp()

	/**
	 * The input builder over the in-memory config, with an event nobody answers.
	 *
	 * @return TimetableInputBuilder
	 */
	private function builder(): TimetableInputBuilder {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = '', bool $lazy = false): string => ($this->config[$key] ?? $default)
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false): bool {
				$this->config[$key] = $value;
				return true;
			}
		);

		return new TimetableInputBuilder(dispatcher: $this->createMock(IEventDispatcher::class), appConfig: $config, grid: new TimetableGridService(appConfig: $config));
	}//end builder()

	/**
	 * The service with the given solver.
	 *
	 * @param TimetableSolver|null $solver The solver; the real one when null.
	 *
	 * @return TimetableGenerationService
	 */
	private function service(?TimetableSolver $solver=null): TimetableGenerationService {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);
		$builder = $this->builder();
		$config  = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(fn (string $app, string $key, string $default = ''): string => ($this->config[$key] ?? $default));

		return new TimetableGenerationService(
			store: new TimetableScenarioStore(container: $container),
			inputBuilder: $builder,
			grid: new TimetableGridService(appConfig: $config),
			solver: ($solver ?? new LocalSearchSolver())
		);
	}//end service()

	/**
	 * The scenario as stored now.
	 *
	 * @return array<string,mixed>
	 */
	private function scenario(): array {
		return $this->objects->stored['s-1'];
	}//end scenario()

	/**
	 * Queueing snapshots the input with the wish, picks a seed and stores status queued; the stored payload passes the real schema.
	 *
	 * @return void
	 */
	public function testQueueSnapshotsTheInput(): void {
		$queued = $this->service()->queue(id: 's-1');

		self::assertSame(expected: 'queued', actual: $queued['status']);
		self::assertCount(expectedCount: 5, haystack: $queued['input']['lessons']);
		self::assertSame(expected: 'w-1', actual: $queued['input']['wishes'][0]['id']);
		self::assertIsInt(actual: $queued['seed']);
		self::assertSame(expected: 60, actual: $queued['metrics']['budgetSeconds']);
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: end($this->objects->saves)));
	}//end testQueueSnapshotsTheInput()

	/**
	 * An imported scenario and an unknown id are refused.
	 *
	 * @return void
	 */
	public function testQueueRefusesAnImportedOrUnknownScenario(): void {
		$this->objects->stored['s-1']['source'] = 'imported';
		try {
			$this->service()->queue(id: 's-1');
			self::fail('An imported scenario was queued.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString(needle: 'generated', haystack: $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service()->queue(id: 'nope');
	}//end testQueueRefusesAnImportedOrUnknownScenario()

	/**
	 * A step stores the placements with no clash and no broken hard wish, and asks for another step while it improves;
	 * the next step does not improve on a perfect week and ends the run as done. The stored payloads pass the real schema.
	 *
	 * @return void
	 */
	public function testAStepStoresTheRunAndRequeuesWhileImproving(): void {
		$service = $this->service();
		$service->queue(id: 's-1');

		$more     = $service->step(id: 's-1');
		$scenario = $this->scenario();
		self::assertCount(expectedCount: 5, haystack: $scenario['placements']);
		self::assertSame(expected: 0, actual: $scenario['metrics']['clashes']);
		self::assertSame(expected: 0, actual: $scenario['metrics']['hardWishesBroken']);
		self::assertSame(expected: 1, actual: $scenario['metrics']['steps']);
		self::assertSame(expected: ($more === true ? 'running' : 'done'), actual: $scenario['status']);
		self::assertSame(expected: [], actual: $this->registerSchemaErrors(slug: 'timetableScenario', payload: end($this->objects->saves)));

		while ($more === true) {
			$more = $service->step(id: 's-1');
		}

		self::assertSame(expected: 'done', actual: $this->scenario()['status']);
		self::assertFalse(condition: $service->step(id: 's-1'), message: 'A done run takes no more steps.');
	}//end testAStepStoresTheRunAndRequeuesWhileImproving()

	/**
	 * A run whose budget is spent stops, even while it would still improve.
	 *
	 * @return void
	 */
	public function testTheRunStopsAtTheBudget(): void {
		$solver = $this->createMock(TimetableSolver::class);
		$cost   = 1000;
		$solver->method('solve')->willReturnCallback(
			static function (SolverInput $input, int $seconds) use (&$cost): SolverResult {
				$cost--;
				return new SolverResult(placements: [], unplaced: [], brokenWishes: [], metrics: ['clashes' => 0], cost: $cost, iterations: 1);
			}
		);
		$service = $this->service(solver: $solver);
		$service->queue(id: 's-1');
		$this->objects->stored['s-1']['metrics']['budgetSeconds'] = 2;

		$steps = 1;
		while ($service->step(id: 's-1') === true) {
			$steps++;
		}

		self::assertLessThanOrEqual(expected: 2, actual: $steps);
		self::assertSame(expected: 'done', actual: $this->scenario()['status']);
		self::assertGreaterThanOrEqual(expected: 2, actual: $this->scenario()['metrics']['secondsSpent']);
	}//end testTheRunStopsAtTheBudget()

	/**
	 * An error in a step stores the run as failed with the reason, and no further step runs.
	 *
	 * @return void
	 */
	public function testAnErrorStoresFailedWithTheReason(): void {
		$solver = $this->createMock(TimetableSolver::class);
		$solver->method('solve')->willThrowException(new RuntimeException('The grid has no periods.'));
		$service = $this->service(solver: $solver);
		$service->queue(id: 's-1');

		self::assertFalse(condition: $service->step(id: 's-1'));
		self::assertSame(expected: 'failed', actual: $this->scenario()['status']);
		self::assertSame(expected: 'The grid has no periods.', actual: $this->scenario()['reason']);
	}//end testAnErrorStoresFailedWithTheReason()

	/**
	 * The job queues its next step only when the step asks for one.
	 *
	 * @return void
	 */
	public function testTheJobRequeuesOnlyWhileTheRunGoesOn(): void {
		$generation = $this->createMock(TimetableGenerationService::class);
		$generation->method('step')->willReturnOnConsecutiveCalls(true, false);
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->once())->method('add')->with(GenerateTimetableScenario::class, ['scenario' => 's-1']);
		$job = new GenerateTimetableScenario(time: $this->createMock(ITimeFactory::class), generation: $generation, jobList: $jobList);

		self::assertTrue(condition: $job->runStep(argument: ['scenario' => 's-1']));
		self::assertFalse(condition: $job->runStep(argument: ['scenario' => 's-1']));
		self::assertFalse(condition: $job->runStep(argument: []));
	}//end testTheJobRequeuesOnlyWhileTheRunGoesOn()
}//end class
