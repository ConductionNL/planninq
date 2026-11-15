<?php

/**
 * Golden test for the timeline endpoints.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\TimelineController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Calls forProject() and forProjects() on seeded registers (readable and
 * unreadable projects, tasks with and without dates, phases, edges inside
 * and across projects, entity and array rows, wrapped and plain result
 * lists) and on the refusals (no user, no OpenRegister, too many ids), and
 * compares status, body, the ObjectService calls and the log lines with the
 * fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\Controller\TimelineController
 */
class TimelineControllerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/timeline-controller.golden.json';

	private const DATES = [null, '', '2026-10-01', '2026-10-15', '2026-11-03', '2026-10-15T08:00:00Z'];

	private const WINDOWS = [
		[null, null],
		['2026-10-10', null],
		[null, '2026-10-10'],
		['2026-10-02', '2026-10-20'],
		['2026-11-01', '2026-11-30'],
		['2026-10-01', '2026-10-15'],
	];

	/**
	 * What the double and the logger saw in the current call.
	 *
	 * @var list<array<string,mixed>>
	 */
	private array $log = [];

	/**
	 * The recorded answers equal the fixture.
	 *
	 * @return void
	 */
	public function testEndpointsMatchTheGoldenRecording(): void {
		$cases = [
			'forProject no user'      => $this->call(register: null, user: false, arguments: ['p1', null, null]),
			'forProject no register'  => $this->call(register: null, user: true, arguments: ['p1', null, null]),
			'forProjects no user'     => $this->call(register: null, user: false, arguments: 'p1,p2'),
			'forProjects no register' => $this->call(register: null, user: true, arguments: 'p1,p2'),
			'forProjects too many'    => $this->call(register: [], user: true, arguments: 'p' . implode(',p', range(1, 51))),
			'forProjects empty'       => $this->call(register: [], user: true, arguments: ' , ,'),
			'forProjects empty, no register' => $this->call(register: null, user: true, arguments: ''),
		];
		$edges = [
			'projects' => ['p1' => ['id' => 'p1', 'title' => 'Edges']],
			'tasks'    => ['p1' => [['id' => 'p1-t1', 'startDate' => '2026-10-15', 'dueDate' => '2026-10-20'], ['id' => 'p1-t2', 'dueDate' => '2026-10-01']]],
			'phases'   => [],
			'edges'    => [],
			'wrap'     => false,
		];
		$cases['forProject starts on the end day']  = $this->call(register: $edges, user: true, arguments: ['p1', null, '2026-10-15']);
		$cases['forProject ends on the start day']  = $this->call(register: $edges, user: true, arguments: ['p1', '2026-10-01', null]);
		$cases['forProject one day before the end'] = $this->call(register: $edges, user: true, arguments: ['p1', null, '2026-10-14']);
		for ($seed = 1; $seed <= 40; $seed++) {
			$random   = new Randomizer(new Mt19937($seed));
			$register = $this->register(random: $random);
			$window   = self::WINDOWS[$random->getInt(0, (count(self::WINDOWS) - 1))];
			$cases['seed ' . $seed . ' forProject p1'] = $this->call(register: $register, user: true, arguments: ['p1', $window[0], $window[1]]);
			$cases['seed ' . $seed . ' forProject p3'] = $this->call(register: $register, user: true, arguments: ['p3', $window[0], $window[1]]);
			$cases['seed ' . $seed . ' forProjects']   = $this->call(register: $register, user: true, arguments: 'p2, p1,p3,,p1 ,p4');
		}

		$json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($cases as $name => $case) {
			self::assertSame(expected: json_encode($expected[$name] ?? null), actual: json_encode($case), message: $name);
		}

		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testEndpointsMatchTheGoldenRecording()

	/**
	 * Call one endpoint: forProject() for a list request, forProjects() for a string.
	 *
	 * @param array<string,mixed>|null                   $register  The seeded objects, or null when OpenRegister is missing.
	 * @param bool                                       $user      Whether a user is signed in.
	 * @param array{0:string,1:?string,2:?string}|string $arguments The forProject() arguments, or the forProjects() list.
	 *
	 * @return array<string,mixed>
	 */
	private function call(?array $register, bool $user, array|string $arguments): array {
		$this->log = [];
		$session   = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn(null);
		if ($user === true) {
			$session = $this->createMock(originalClassName: IUserSession::class);
			$session->method('getUser')->willReturn($this->createMock(originalClassName: IUser::class));
		}
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		if ($register === null) {
			$container->method('get')->willThrowException(new RuntimeException('no OpenRegister'));
		} else {
			$container->method('get')->willReturn($this->objectService(register: $register));
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->log[] = ['error' => $message, 'context' => $context];
			}
		);

		$request    = $this->createMock(originalClassName: IRequest::class);
		$controller = new TimelineController(request: $request, userSession: $session, container: $container, logger: $logger);
		if (is_array($arguments) === true) {
			$response = $controller->forProject(projectId: $arguments[0], from: $arguments[1], to: $arguments[2]);
		} else {
			$response = $controller->forProjects(projects: $arguments);
		}

		return ['status' => $response->getStatus(), 'data' => $response->getData(), 'log' => $this->log];
	}//end call()

	/**
	 * A seeded register: projects p1 to p4 (p4 never readable), their tasks, phases and edges.
	 *
	 * @param Randomizer $random The seeded randomizer.
	 *
	 * @return array<string,mixed>
	 */
	private function register(Randomizer $random): array {
		$register = ['projects' => [], 'tasks' => [], 'phases' => [], 'edges' => []];
		$taskIds  = [];
		foreach (['p1', 'p2', 'p3'] as $project) {
			if ($random->getInt(0, 4) > 0) {
				$register['projects'][$project] = [
					'id'        => $project,
					'title'     => 'Project ' . $project,
					'status'    => ['active', 'paused', ''][$random->getInt(0, 2)],
					'startDate' => self::DATES[$random->getInt(0, 5)],
					'endDate'   => self::DATES[$random->getInt(0, 5)],
				];
			}

			$register['tasks'][$project]  = [];
			$register['phases'][$project] = [];
			for ($index = $random->getInt(0, 6); $index > 0; $index--) {
				$id        = $project . '-t' . $index;
				$taskIds[] = $id;
				$task      = [
					'title'             => 'Task ' . $id,
					'status'            => ['open', 'done', 'in_progress'][$random->getInt(0, 2)],
					'priority'          => 'normal',
					'startDate'         => self::DATES[$random->getInt(0, 5)],
					'dueDate'           => self::DATES[$random->getInt(0, 5)],
					'estimatedDuration' => [null, 60, 480][$random->getInt(0, 2)],
					'percentComplete'   => [null, 0, 50][$random->getInt(0, 2)],
				];
				$shapes    = [['id' => $id] + $task, ['@self' => ['id' => $id]] + $task, ['id' => ''] + $task, ['object' => ['id' => $id] + $task]];
				$register['tasks'][$project][] = $shapes[$random->getInt(0, 3)];
			}

			for ($index = $random->getInt(0, 3); $index > 0; $index--) {
				$register['phases'][$project][] = [
					'id'        => $project . '-ph' . $index,
					'order'     => $random->getInt(0, 5),
					'title'     => 'Phase ' . $index,
					'status'    => 'open',
					'startDate' => self::DATES[$random->getInt(0, 5)],
					'endDate'   => self::DATES[$random->getInt(0, 5)],
				];
			}
		}//end foreach

		$taskIds[] = 'elsewhere';
		for ($index = $random->getInt(0, 6); $index > 0; $index--) {
			$blocker = $taskIds[$random->getInt(0, (count($taskIds) - 1))];
			$edge    = ['id' => 'e' . $index, 'blocker' => $blocker, 'blocked' => $taskIds[$random->getInt(0, (count($taskIds) - 1))]];
			if ($random->getInt(0, 1) === 1) {
				$edge['type'] = 'relates';
			}

			$register['edges'][] = $edge;
		}

		$register['wrap'] = ($random->getInt(0, 1) === 1);
		return $register;
	}//end register()

	/**
	 * A recording ObjectService double over a seeded register.
	 *
	 * @param array<string,mixed> $register The seeded objects.
	 *
	 * @return object
	 */
	private function objectService(array $register): object {
		$test = $this;
		return new class($register, function (array $entry) use ($test): void {
			$test->record(entry: $entry);
		}) {
			/**
			 * The schema named by the last setSchema().
			 *
			 * @var string
			 */
			private string $schema = '';

			/**
			 * @param array<string,mixed> $register The seeded objects.
			 * @param \Closure            $record   Records one call.
			 */
			public function __construct(private array $register, private \Closure $record) {
			}

			/**
			 * @return void
			 */
			public function clearCurrents(): void {
				($this->record)(['call' => 'clearCurrents']);
			}

			/**
			 * @param string $register The register slug.
			 *
			 * @return void
			 */
			public function setRegister(string $register): void {
				($this->record)(['call' => 'setRegister', 'register' => $register]);
			}

			/**
			 * @param string $schema The schema slug.
			 *
			 * @return void
			 */
			public function setSchema(string $schema): void {
				$this->schema = $schema;
				($this->record)(['call' => 'setSchema', 'schema' => $schema]);
			}

			/**
			 * @param string $id The object id.
			 *
			 * @return object|null
			 */
			public function find(string $id): ?object {
				($this->record)(['call' => 'find', 'id' => $id, 'schema' => $this->schema]);
				$data = ($this->register['projects'][$id] ?? null);
				if ($this->schema !== 'project' || $data === null) {
					return null;
				}

				return new class($data) {
					/**
					 * @param array<string,mixed> $data The object.
					 */
					public function __construct(private array $data) {
					}

					/**
					 * @return array<string,mixed>
					 */
					public function getObject(): array {
						return $this->data;
					}
				};
			}

			/**
			 * @param string              $registerSlug The register slug.
			 * @param string              $schemaSlug   The schema slug.
			 * @param array<string,mixed> $filters      The filters.
			 *
			 * @return array<int|string,mixed>
			 */
			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters = []): array {
				($this->record)(['call' => 'searchObjectsBySlug', 'register' => $registerSlug, 'schema' => $schemaSlug, 'filters' => $filters]);
				$project = (string)($filters['project'] ?? '');
				$rows    = match ($schemaSlug) {
					'task' => array_map(static fn (array $row): array => ($row['object'] ?? $row), ($this->register['tasks'][$project] ?? [])),
					'projectPhase' => ($this->register['phases'][$project] ?? []),
					'dependency' => $this->register['edges'],
					default => [],
				};
				if ($this->register['wrap'] === true) {
					return ['results' => $rows];
				}

				return $rows;
			}
		};
	}//end objectService()

	/**
	 * Record one ObjectService call.
	 *
	 * @param array<string,mixed> $entry The call.
	 *
	 * @return void
	 */
	public function record(array $entry): void {
		$this->log[] = $entry;
	}//end record()
}//end class
