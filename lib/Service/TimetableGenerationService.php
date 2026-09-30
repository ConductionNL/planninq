<?php

/**
 * Planninq Timetable Generation Service
 *
 * Queues a scenario for the generator and runs one time-boxed step of it
 * (design decision 6): each step starts from the best placements so far, the
 * job repeats while the run improves and the time budget lasts.
 *
 * @category Service
 * @package  OCA\Planninq\Service
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

namespace OCA\Planninq\Service;

use InvalidArgumentException;
use OCA\Planninq\Timetabling\SolverInput;
use OCA\Planninq\Timetabling\TimetableSolver;
use Throwable;

/**
 * Queue and step a generated timetable scenario.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
 */
class TimetableGenerationService {

	/**
	 * The longest one step runs, in seconds.
	 */
	public const STEP_SECONDS = 60;

	/**
	 * Statuses a run may be queued from.
	 */
	private const QUEUEABLE = ['queued', 'done', 'failed'];

	/**
	 * Constructor.
	 *
	 * @param TimetableScenarioStore $store        Reads and writes scenarios and wishes.
	 * @param TimetableInputBuilder  $inputBuilder Builds the input from learniq or the uploaded sheets.
	 * @param TimetableGridService   $grid         Holds the time budget.
	 * @param TimetableSolver        $solver       Places the lessons.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly TimetableScenarioStore $store,
		private readonly TimetableInputBuilder $inputBuilder,
		private readonly TimetableGridService $grid,
		private readonly TimetableSolver $solver,
	) {
	}//end __construct()

	/**
	 * Make a scenario ready for a run: snapshot the input, pick a seed, clear the last run, status queued.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return array<string,mixed> The stored scenario.
	 *
	 * @throws InvalidArgumentException When the scenario does not exist, is imported or published, or has no activities.
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	public function queue(string $id): array {
		$scenario = $this->store->load(id: $id);
		if ($scenario === null) {
			throw new InvalidArgumentException('There is no timetable scenario with this id.');
		}

		if (($scenario['source'] ?? '') !== 'generated' || in_array(($scenario['status'] ?? 'queued'), self::QUEUEABLE, true) === false) {
			throw new InvalidArgumentException('Only a generated scenario that is not running or published can be generated.');
		}

		$input = $this->inputBuilder->build(
			academicYear: $this->academicYear(date: (string)($scenario['windowFrom'] ?? '')),
			wishes: $this->store->wishes()
		);
		if ($input->isEmpty() === true) {
			throw new InvalidArgumentException((string)$input->reason);
		}

		$scenario = array_merge(
			$scenario,
			[
				'status'       => 'queued',
				'seed'         => ($scenario['seed'] ?? random_int(1, 2147483646)),
				'input'        => $input->toArray(),
				'placements'   => [],
				'unplaced'     => [],
				'brokenWishes' => [],
				'metrics'      => ['secondsSpent' => 0, 'steps' => 0, 'budgetSeconds' => $this->budgetSeconds()],
				'reason'       => '',
			]
		);
		$this->store->save(id: $id, data: $scenario);

		return $scenario;
	}//end queue()

	/**
	 * Run one step and store the result; whether the job should run another step.
	 *
	 * @param string $id The scenario id.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.2
	 */
	public function step(string $id): bool {
		$scenario = $this->store->load(id: $id);
		if ($scenario === null || in_array(($scenario['status'] ?? ''), ['queued', 'running'], true) === false) {
			return false;
		}

		try {
			return $this->runStep(id: $id, scenario: $scenario);
		} catch (Throwable $e) {
			$this->store->save(id: $id, data: array_merge($scenario, ['status' => 'failed', 'reason' => $e->getMessage()]));
			return false;
		}
	}//end step()

	/**
	 * One step: solve from the best placements so far for at most a minute of the budget left.
	 *
	 * @param string              $id       The scenario id.
	 * @param array<string,mixed> $scenario The stored scenario.
	 *
	 * @return bool Whether another step should run.
	 */
	private function runStep(string $id, array $scenario): bool {
		$metrics = (array)($scenario['metrics'] ?? []);
		$budget  = (int)($metrics['budgetSeconds'] ?? $this->budgetSeconds());
		$spent   = (int)($metrics['secondsSpent'] ?? 0);
		$input   = $this->inputOf(scenario: $scenario);
		$seconds = min(self::STEP_SECONDS, max(1, ($budget - $spent)));
		$began   = microtime(true);
		$result  = $this->solver->solve(
			input: $input,
			seconds: $seconds,
			seed: ((int)($scenario['seed'] ?? 1) + (int)($metrics['steps'] ?? 0)),
			start: (array)($scenario['placements'] ?? [])
		);

		$spent   += max(1, (int)ceil(microtime(true) - $began));
		$previous = ($metrics['cost'] ?? null);
		$improved = ($previous === null || $result->cost < (int)$previous);
		$more     = ($improved === true && $spent < $budget && $result->cost > 0);

		$this->store->save(
			id: $id,
			data: array_merge(
				$scenario,
				$result->toScenario(),
				[
					'status'  => ($more === true ? 'running' : 'done'),
					'metrics' => array_merge(
						$result->metrics,
						['cost' => $result->cost, 'secondsSpent' => $spent, 'steps' => ((int)($metrics['steps'] ?? 0) + 1), 'budgetSeconds' => $budget]
					),
				]
			)
		);

		return $more;
	}//end runStep()

	/**
	 * The solver input a scenario keeps.
	 *
	 * @param array<string,mixed> $scenario The scenario.
	 *
	 * @return SolverInput
	 *
	 * @throws InvalidArgumentException When the scenario has no lessons.
	 */
	private function inputOf(array $scenario): SolverInput {
		$input = (array)($scenario['input'] ?? []);
		if (($input['lessons'] ?? []) === []) {
			throw new InvalidArgumentException('The scenario has no lessons to place.');
		}

		return new SolverInput(
			periods: (array)($input['periods'] ?? []),
			rooms: (array)($input['rooms'] ?? []),
			lessons: (array)$input['lessons'],
			wishes: (array)($input['wishes'] ?? []),
			source: (string)($input['source'] ?? 'none')
		);
	}//end inputOf()

	/**
	 * The time budget of a run in seconds.
	 *
	 * @return int
	 */
	private function budgetSeconds(): int {
		return ((int)($this->grid->settings()[TimetableGridService::BUDGET_KEY] ?? TimetableGridService::DEFAULT_BUDGET) * 60);
	}//end budgetSeconds()

	/**
	 * The academic year a date falls in: from August on it is this year and the next.
	 *
	 * @param string $date A date, as in `2026-10-05`.
	 *
	 * @return string As in `2026-2027`.
	 */
	private function academicYear(string $date): string {
		$time  = (strtotime($date) ?: time());
		$year  = (int)date('Y', $time);
		$start = ((int)date('n', $time) >= 8) ? $year : ($year - 1);
		return $start.'-'.($start + 1);
	}//end academicYear()
}//end class
