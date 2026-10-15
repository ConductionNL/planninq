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
		$lessons  = array_column($input->lessons, null, 'key');
		$built    = $this->options->build(input: $input);
		$state    = new SearchState(lessons: $lessons);
		$random   = new SeededRandom(seed: $seed);

		$this->resume(state: $state, options: $built['options'], start: $start);
		$this->construct(input: $input, state: $state, options: $built['options']);
		[$best, $iterations] = $this->anneal(input: $input, state: $state, options: $built['options'], random: $random, deadline: $deadline);

		$state = new SearchState(lessons: $lessons);
		$this->resume(state: $state, options: $built['options'], start: $best);
		$repaired = $this->repair(input: $input, state: $state);

		return $this->result(
			input: $input,
			state: $state,
			blockers: $built['blockers'],
			repaired: $repaired,
			iterations: $iterations
		);
	}//end solve()

	/**
	 * Put back the placements of an earlier step that are still options and free.
	 *
	 * @param SearchState                                                 $state   The state.
	 * @param array<string,array<int,array<string,mixed>>>                $options The options per lesson.
	 * @param array<int,array{lesson:string,period:string,room:string}> $start   The earlier placements.
	 *
	 * @return void
	 */
	private function resume(SearchState $state, array $options, array $start): void {
		foreach ($start as $placement) {
			$key = (string)($placement['lesson'] ?? '');
			foreach (($options[$key] ?? []) as $option) {
				if ($option['period'] === (string)$placement['period'] && $option['room'] === (string)$placement['room']) {
					$state->place(key: $key, option: $option);
					break;
				}
			}
		}
	}//end resume()

	/**
	 * Place every lesson not yet placed, the one with the fewest options first, on its cheapest free option.
	 *
	 * @param SolverInput                                  $input   The input.
	 * @param SearchState                                  $state   The state.
	 * @param array<string,array<int,array<string,mixed>>> $options The options per lesson.
	 *
	 * @return void
	 */
	private function construct(SolverInput $input, SearchState $state, array $options): void {
		$order = array_keys($options);
		usort($order, static fn (string $one, string $two): int => [count($options[$one]), $one] <=> [count($options[$two]), $two]);
		$lessons = array_column($input->lessons, null, 'key');
		$soft    = array_values(array_filter($input->wishes, static fn (array $wish): bool => ($wish['strength'] ?? 'soft') !== 'hard'));
		foreach ($order as $key) {
			if ($state->option(key: (string)$key) !== null) {
				continue;
			}

			$choice = null;
			$lowest = PHP_INT_MAX;
			foreach ($options[$key] as $option) {
				$price = $this->price(lesson: $lessons[$key], option: $option, soft: $soft, state: $state);
				if ($price < $lowest && $state->fits(key: (string)$key, option: $option) === true) {
					$choice = $option;
					$lowest = $price;
				}
			}

			if ($choice !== null) {
				$state->place(key: (string)$key, option: $choice);
			}
		}//end foreach
	}//end construct()

	/**
	 * The local price of an option while constructing: the soft wishes it breaks by itself,
	 * then how full the day already is for the teacher and the group, so lessons spread over the week.
	 *
	 * @param array<string,mixed>            $lesson The lesson.
	 * @param array<string,mixed>            $option The option.
	 * @param array<int,array<string,mixed>> $soft   The soft wishes.
	 * @param SearchState                    $state  The state.
	 *
	 * @return int
	 */
	private function price(array $lesson, array $option, array $soft, SearchState $state): int {
		$price = 0;
		foreach ($soft as $wish) {
			if ($this->wishes->forbids(wish: $wish, lesson: $lesson, periods: $option['periods'], room: $option['room']) === true) {
				$price += (max(1, (int)($wish['weight'] ?? 1)) * TimetableScorer::SOFT_COST * 10);
			}
		}

		$day = explode('-', (string)$option['period'], 2)[0];
		return $price
			+ $state->onDay(field: 'group', reference: (string)($lesson['group'] ?? ''), day: $day)
			+ $state->onDay(field: 'teacher', reference: (string)($lesson['teacher'] ?? ''), day: $day);
	}//end price()

	/**
	 * Simulated annealing: move one lesson to another free option, keep the move when it
	 * costs less or, with a chance that shrinks as the search cools, when it costs a little more.
	 *
	 * @param SolverInput                                  $input    The input.
	 * @param SearchState                                  $state    The state.
	 * @param array<string,array<int,array<string,mixed>>> $options  The options per lesson.
	 * @param SeededRandom                                 $random   The random numbers.
	 * @param float                                        $deadline When to stop, as microtime.
	 *
	 * @return array{0:array<int,array<string,string>>,1:int} The best placements and the moves tried.
	 */
	private function anneal(SolverInput $input, SearchState $state, array $options, SeededRandom $random, float $deadline): array {
		$movable = array_values(array_filter(array_keys($options), static fn ($key): bool => $options[$key] !== []));
		$cost    = $this->cost(input: $input, state: $state);
		$best    = [$cost, $state->placements()];
		$tried   = 0;
		while ($movable !== [] && $tried < $this->iterations && microtime(true) < $deadline) {
			$tried++;
			$key    = (string)$movable[$random->below(max: count($movable))];
			$target = $options[$key][$random->below(max: count($options[$key]))];
			$before = $state->remove(key: $key);
			if ($state->place(key: $key, option: $target) === false) {
				$this->restore(state: $state, key: $key, option: $before);
				continue;
			}

			$next = $this->cost(input: $input, state: $state);
			if ($this->accept(delta: ($next - $cost), progress: ($tried / $this->iterations), random: $random) === false) {
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
	 * @param SolverInput                     $input      The input.
	 * @param SearchState                     $state      The final state.
	 * @param array<string,array<int,string>> $blockers   The hard wishes that narrowed each lesson's options.
	 * @param array<string,string>            $repaired   The lessons taken off by the repair, with the wish.
	 * @param int                             $iterations The moves tried.
	 *
	 * @return SolverResult
	 */
	private function result(SolverInput $input, SearchState $state, array $blockers, array $repaired, int $iterations): SolverResult {
		$unplaced = [];
		foreach ($input->lessons as $lesson) {
			$key = (string)$lesson['key'];
			if ($state->option(key: $key) !== null) {
				continue;
			}

			$wish       = ($repaired[$key] ?? ($blockers[$key][0] ?? null));
			$unplaced[] = [
				'lesson' => $key,
				'wish'   => $wish,
				'reason' => match (true) {
					isset($repaired[$key]) => 'hardWish',
					$wish !== null         => 'hardWishPeriods',
					default                => 'noFreePlace',
				},
			];
		}

		$placements = $state->placements();
		$score      = $this->scorer->score(input: $input, placements: $placements);
		return new SolverResult(
			placements: $placements,
			unplaced: $unplaced,
			brokenWishes: $score['brokenWishes'],
			metrics: $score['metrics'],
			cost: $this->scorer->costOf(metrics: $score['metrics']),
			iterations: $iterations
		);
	}//end result()
}//end class
