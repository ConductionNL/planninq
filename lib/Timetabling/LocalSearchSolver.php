<?php

/**
 * Planninq Local Search Solver
 *
 * The PHP engine of design decision 1: place the most constrained lesson first on
 * its cheapest free option, then improve with simulated annealing on the scorer's cost. A lesson
 * that cannot be placed without breaking a hard wish is left unplaced and names that wish.
 *
 * @category Timetabling
 * @package  OCA\Planninq\Timetabling
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

namespace OCA\Planninq\Timetabling;


/**
 * Greedy construction, simulated annealing, and a repair pass for hard wishes.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
 */
final class LocalSearchSolver implements TimetableSolver {

	/**
	 * Moves tried in one call; the job's 60-second steps repeat calls from the best state.
	 */
	public const STEP_ITERATIONS = 4000;

	/**
	 * The starting temperature, in cost units: a few soft wishes.
	 */
	private const START_TEMPERATURE = 40.0;

	/**
	 * Constructor.
	 *
	 * @param TimetableScorer  $scorer     The cost the search minimises.
	 * @param PlacementOptions $options    The options per lesson.
	 * @param WishChecker      $wishes     Evaluates the wishes.
	 * @param int              $iterations Moves per call.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly TimetableScorer $scorer=new TimetableScorer(),
		private readonly PlacementOptions $options=new PlacementOptions(),
		private readonly WishChecker $wishes=new WishChecker(),
		private readonly int $iterations=self::STEP_ITERATIONS,
	) {
	}//end __construct()

	/**
	 * Place the lessons.
	 *
	 * @param SolverInput                                                $input   What to place.
	 * @param int                                                        $seconds The most time this call may take.
	 * @param int                                                        $seed    The seed.
	 * @param array<int,array{lesson:string,period:string,room:string}> $start   Placements to continue from.
	 *
	 * @return SolverResult
	 *
	 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.1
	 */
	public function solve(SolverInput $input, int $seconds, int $seed, array $start=[]): SolverResult {
		$deadline = (microtime(true) + max(1, $seconds));
		$built    = $this->options->build(input: $input);
		$random   = new SeededRandom(seed: $seed);

		$run      = new SolveRun(input: $input, built: $built, random: $random, deadline: $deadline);

		$state = $this->resumed(run: $run, start: $start);
		$this->construct(run: $run, state: $state);
		[$best, $iterations] = $this->anneal(run: $run, state: $state);

		$state    = $this->resumed(run: $run, start: $best);
		$repaired = $this->repair(input: $input, state: $state);

		return $this->result(
			run: $run,
			outcome: new SearchOutcome(state: $state, repaired: $repaired, iterations: $iterations)
		);
	}//end solve()

	/**
	 * A fresh state with the placements of an earlier step put back that are still options and free.
	 *
	 * @param SolveRun                                                    $run   The input and the options per lesson.
	 * @param array<int,array{lesson:string,period:string,room:string}> $start The earlier placements.
	 *
	 * @return SearchState
	 */
	private function resumed(SolveRun $run, array $start): SearchState {
		$state   = new SearchState(lessons: array_column($run->input->lessons, null, 'key'));
		$options = $run->built['options'];
		foreach ($start as $placement) {
			$key = (string)($placement['lesson'] ?? '');
			foreach (($options[$key] ?? []) as $option) {
				if ($option['period'] === (string)$placement['period'] && $option['room'] === (string)$placement['room']) {
					$state->place(key: $key, option: $option);
					break;
				}
			}
		}

		return $state;
	}//end resumed()

	/**
	 * Place every lesson not yet placed, the one with the fewest options first, on its cheapest free option.
	 *
	 * @param SolveRun    $run   The input and the options per lesson.
	 * @param SearchState $state The state.
	 *
	 * @return void
	 */
	private function construct(SolveRun $run, SearchState $state): void {
		$options = $run->built['options'];
		$lessons = array_column($run->input->lessons, null, 'key');
		$soft    = array_values(array_filter($run->input->wishes, static fn (array $wish): bool => ($wish['strength'] ?? 'soft') !== 'hard'));
		foreach ($this->fewestOptionsFirst(options: $options) as $key) {
			if ($state->option(key: (string)$key) !== null) {
				continue;
			}

			$candidate = new PlacementCandidate(key: (string)$key, lesson: $lessons[$key], soft: $soft, state: $state);
			$choice    = $this->cheapest(candidate: $candidate, options: $options[$key]);
			if ($choice !== null) {
				$state->place(key: (string)$key, option: $choice);
			}
		}
	}//end construct()

	/**
	 * The lesson keys, the one with the fewest options first, then by key.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $options The options per lesson.
	 *
	 * @return array<int,string>
	 */
	private function fewestOptionsFirst(array $options): array {
		$order = array_keys($options);
		usort($order, static fn (string $one, string $two): int => [count($options[$one]), $one] <=> [count($options[$two]), $two]);

		return $order;
	}//end fewestOptionsFirst()

	/**
	 * The cheapest of a lesson's options that fits; the first one wins a tie.
	 *
	 * @param PlacementCandidate             $candidate The lesson to place.
	 * @param array<int,array<string,mixed>> $options   Its options.
	 *
	 * @return array<string,mixed>|null Null when no option fits.
	 */
	private function cheapest(PlacementCandidate $candidate, array $options): ?array {
		$choice = null;
		$lowest = PHP_INT_MAX;
		foreach ($options as $option) {
			$price = $this->price(candidate: $candidate, option: $option);
			if ($price < $lowest && $candidate->state->fits(key: $candidate->key, option: $option) === true) {
				$choice = $option;
				$lowest = $price;
			}
		}

		return $choice;
	}//end cheapest()

	/**
	 * The local price of an option while constructing: the soft wishes it breaks by itself,
	 * then how full the day already is for the teacher and the group, so lessons spread over the week.
	 *
	 * @param PlacementCandidate  $candidate The lesson, its soft wishes and the state.
	 * @param array<string,mixed> $option    The option.
	 *
	 * @return int
	 */
	private function price(PlacementCandidate $candidate, array $option): int {
		$price  = 0;
		$lesson = $candidate->lesson;
		foreach ($candidate->soft as $wish) {
			if ($this->wishes->forbids(wish: $wish, lesson: $lesson, periods: $option['periods'], room: $option['room']) === true) {
				$price += (max(1, (int)($wish['weight'] ?? 1)) * TimetableScorer::SOFT_COST * 10);
			}
		}

		$day = explode('-', (string)$option['period'], 2)[0];
		return $price
			+ $candidate->state->onDay(field: 'group', reference: (string)($lesson['group'] ?? ''), day: $day)
			+ $candidate->state->onDay(field: 'teacher', reference: (string)($lesson['teacher'] ?? ''), day: $day);
	}//end price()

	/**
	 * Simulated annealing: move one lesson to another free option, keep the move when it
	 * costs less or, with a chance that shrinks as the search cools, when it costs a little more.
	 *
	 * @param SolveRun    $run   The input, options, random numbers and deadline.
	 * @param SearchState $state The state.
	 *
	 * @return array{0:array<int,array<string,string>>,1:int} The best placements and the moves tried.
	 */
	private function anneal(SolveRun $run, SearchState $state): array {
		$options = $run->built['options'];
		$movable = array_values(array_filter(array_keys($options), static fn ($key): bool => $options[$key] !== []));
		$cost    = $this->cost(input: $run->input, state: $state);
		$best    = [$cost, $state->placements()];
		$tried   = 0;
		while ($movable !== [] && $tried < $this->iterations && microtime(true) < $run->deadline) {
			$tried++;
			$key    = (string)$movable[$run->random->below(max: count($movable))];
			$target = $options[$key][$run->random->below(max: count($options[$key]))];
			$before = $state->remove(key: $key);
			if ($state->place(key: $key, option: $target) === false) {
				$this->restore(state: $state, key: $key, option: $before);
				continue;
			}

			$next = $this->cost(input: $run->input, state: $state);
			if ($this->accept(delta: ($next - $cost), progress: ($tried / $this->iterations), random: $run->random) === false) {
				$state->remove(key: $key);
				$this->restore(state: $state, key: $key, option: $before);
				continue;
			}

			$cost = $next;
			if ($cost < $best[0]) {
				$best = [$cost, $state->placements()];
			}
		}//end while

		return [$best[1], $tried];
	}//end anneal()

	/**
	 * Whether to keep a move.
	 *
	 * @param int          $delta    The change in cost.
	 * @param float        $progress How far the search is, 0 to 1.
	 * @param SeededRandom $random   The random numbers.
	 *
	 * @return bool
	 */
	private function accept(int $delta, float $progress, SeededRandom $random): bool {
		if ($delta <= 0) {
			return true;
		}

		$temperature = max(0.01, (self::START_TEMPERATURE * (1.0 - $progress)));
		return $random->fraction() < exp(-$delta / $temperature);
	}//end accept()

	/**
	 * Put a lesson back on the option it had, if it had one.
	 *
	 * @param SearchState               $state  The state.
	 * @param string                    $key    The lesson key.
	 * @param array<string,mixed>|null $option The option it had.
	 *
	 * @return void
	 */
	private function restore(SearchState $state, string $key, ?array $option): void {
		if ($option !== null) {
			$state->place(key: $key, option: $option);
		}
	}//end restore()

	/**
	 * The scorer's cost of the current placements, with every lesson that breaks a hard wish
	 * counted as a hard breach, so the search also shrinks how badly a hard wish is broken.
	 *
	 * @param SolverInput $input The input.
	 * @param SearchState $state The state.
	 *
	 * @return int
	 */
	private function cost(SolverInput $input, SearchState $state): int {
		$score = $this->scorer->score(input: $input, placements: $state->placements());
		$cost  = $this->scorer->costOf(metrics: $score['metrics']);
		foreach ($score['brokenWishes'] as $broken) {
			if ($broken['strength'] === 'hard') {
				$cost += (count($broken['lessons']) * TimetableScorer::HARD_COST);
			}
		}

		return $cost;
	}//end cost()

	/**
	 * Take off the grid every lesson that still breaks a hard wish, so a generated
	 * scenario never breaks one; returns the wish id per lesson taken off.
	 *
	 * @param SolverInput $input The input.
	 * @param SearchState $state The state.
	 *
	 * @return array<string,string>
	 */
	private function repair(SolverInput $input, SearchState $state): array {
		$taken = [];
		foreach ($input->wishes as $wish) {
			if (($wish['strength'] ?? '') !== 'hard') {
				continue;
			}

			$broken = $this->scorer->score(input: $input, placements: $state->placements())['brokenWishes'];
			foreach ($broken as $entry) {
				if ($entry['wish'] !== (string)($wish['id'] ?? '')) {
					continue;
				}

				foreach ($entry['lessons'] as $key) {
					$state->remove(key: (string)$key);
					$taken[(string)$key] = (string)$entry['wish'];
				}
			}
		}

		return $taken;
	}//end repair()

	/**
	 * The result: placements, unplaced lessons with the wish that blocked them, and the score.
	 *
	 * @param SolveRun      $run     The input and the hard wishes that narrowed each lesson's options.
	 * @param SearchOutcome $outcome The final state, the lessons the repair took off and the moves tried.
	 *
	 * @return SolverResult
	 */
	private function result(SolveRun $run, SearchOutcome $outcome): SolverResult {
		$unplaced = [];
		foreach ($run->input->lessons as $lesson) {
			$key = (string)$lesson['key'];
			if ($outcome->state->option(key: $key) !== null) {
				continue;
			}

			$wish       = ($outcome->repaired[$key] ?? ($run->built['blockers'][$key][0] ?? null));
			$unplaced[] = [
				'lesson' => $key,
				'wish'   => $wish,
				'reason' => match (true) {
					isset($outcome->repaired[$key]) => 'hardWish',
					$wish !== null         => 'hardWishPeriods',
					default                => 'noFreePlace',
				},
			];
		}

		$placements = $outcome->state->placements();
		$score      = $this->scorer->score(input: $run->input, placements: $placements);
		return new SolverResult(
			placements: $placements,
			unplaced: $unplaced,
			brokenWishes: $score['brokenWishes'],
			metrics: $score['metrics'],
			cost: $this->scorer->costOf(metrics: $score['metrics']),
			iterations: $outcome->iterations
		);
	}//end result()
}//end class
