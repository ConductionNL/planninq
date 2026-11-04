<?php

/**
 * Planninq Dependency Graph
 *
 * The pure, I/O-free half of task dependencies: cycle detection over the
 * blocker→blocked edge list and the blocked-task derivation mirrored by the
 * frontend `src/utils/taskHelpers.js` helper.
 *
 * Split out of DependencyService, which carried both these graph algorithms AND
 * the whole OpenRegister read/write plane and tripped PHPMD's
 * ExcessiveClassComplexity threshold — the rule was correctly naming a real
 * Single Responsibility violation. Every method here is pure (no state, no
 * I/O), so the move is behaviour-preserving by construction. They are instance
 * methods rather than statics purely so callers can inject this collaborator
 * instead of reaching for static access (PHPMD StaticAccess).
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

/**
 * Pure graph algorithms over the task-dependency edge list.
 *
 * @spec openspec/specs/task-dependencies/spec.md
 */
final class DependencyGraph {
	/**
	 * Task statuses that count as "resolved" — a blocker in one of these
	 * states no longer blocks anything.
	 *
	 * @var array<int,string>
	 */
	private const RESOLVED_STATUSES = ['done', 'cancelled'];

	/**
	 * Determine whether adding the edge blocker→blocked would close a cycle,
	 * and if so, return the offending path; otherwise return null.
	 *
	 * Pure function over the edge list — no I/O. A cycle is closed when the
	 * proposed `blocked` task can already reach the proposed `blocker` task by
	 * following existing blocker→blocked edges. The returned path is rendered
	 * as it would read once the edge is added:
	 * `[blocker, blocked, …existing hops…, blocker]`.
	 *
	 * Self-edges (blocker === blocked) are reported as a one-hop cycle. The DFS
	 * uses a visited set, so it terminates even if the existing graph already
	 * contains a cycle (e.g. a concurrent-write artifact).
	 *
	 * @param array<int,array<string,mixed>> $edges Existing edges (each with `blocker`/`blocked`).
	 * @param string $blocker UUID of the proposed blocking task.
	 * @param string $blocked UUID of the proposed blocked task.
	 *
	 * @return array<int,string>|null The cycle path of task UUIDs, or null when no cycle forms.
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function cyclePath(array $edges, string $blocker, string $blocked): ?array {
		if ($blocker === $blocked) {
			return [$blocker, $blocker];
		}

		return $this->closingPath(adjacency: $this->buildAdjacency(edges: $edges), ends: [$blocker, $blocked]);
	}//end cyclePath()

	/**
	 * The path that adding blocker→blocked would close, when blocked already reaches blocker.
	 *
	 * A depth-first walk following blocker→blocked edges from `blocked`; a node is
	 * expanded once, so the walk ends on a graph that already holds a cycle.
	 *
	 * @param array<string,array<int,string>> $adjacency Blocker UUID → blocked UUIDs.
	 * @param array{0:string,1:string}        $ends      The proposed blocker and blocked task.
	 *
	 * @return array<int,string>|null blocker → blocked → … → blocker, or null.
	 */
	private function closingPath(array $adjacency, array $ends): ?array {
		[$blocker, $blocked] = $ends;
		$visited = [];
		$stack   = [[$blocked, [$blocked]]];
		while ($stack !== []) {
			[$node, $trail] = array_pop($stack);
			if ($node === $blocker) {
				return array_merge([$blocker], $trail);
			}

			if (isset($visited[$node]) === true) {
				continue;
			}

			$visited[$node] = true;
			$unvisited      = array_diff(($adjacency[$node] ?? []), array_keys($visited));
			$stack          = array_merge($stack, $this->stepsFrom(trail: $trail, next: $unvisited));
		}

		return null;
	}//end closingPath()

	/**
	 * One walk step per next task, each carrying the trail extended with that task.
	 *
	 * @param array<int,string> $trail The trail so far.
	 * @param array<int,string> $next  The tasks to step to, in order.
	 *
	 * @return array<int,array{0:string,1:array<int,string>}>
	 */
	private function stepsFrom(array $trail, array $next): array {
		$steps = [];
		foreach ($next as $task) {
			$steps[] = [$task, array_merge($trail, [$task])];
		}

		return $steps;
	}//end stepsFrom()

	/**
	 * Build an adjacency map (blocker UUID → list of blocked UUIDs) from edges.
	 *
	 * @param array<int,array<string,mixed>> $edges Existing edges.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function buildAdjacency(array $edges): array {
		$adjacency = [];
		foreach ($this->blockingEnds(edges: $edges) as [$from, $to]) {
			$adjacency[$from][] = $to;
		}

		return $adjacency;
	}//end buildAdjacency()

	/**
	 * The blocker and blocked task of every blocking edge that names both.
	 *
	 * @param array<int,array<string,mixed>> $edges The edges.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private function blockingEnds(array $edges): array {
		$ends = [];
		foreach ($edges as $edge) {
			$pair = $this->endsOf(edge: $edge);
			if (in_array('', $pair, true) === false && $this->isBlocking(edge: $edge) === true) {
				$ends[] = $pair;
			}
		}

		return $ends;
	}//end blockingEnds()

	/**
	 * An edge's blocker and blocked task, empty when unset.
	 *
	 * @param array<string,mixed> $edge The edge.
	 *
	 * @return array{0:string,1:string}
	 */
	private function endsOf(array $edge): array {
		return [(string)($edge['blocker'] ?? ''), (string)($edge['blocked'] ?? '')];
	}//end endsOf()

	/**
	 * Convenience boolean wrapper around {@see cyclePath()}.
	 *
	 * @param array<int,array<string,mixed>> $edges Existing edges.
	 * @param string $blocker UUID of the proposed blocking task.
	 * @param string $blocked UUID of the proposed blocked task.
	 *
	 * @return bool True when the edge would form a cycle.
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function wouldFormCycle(array $edges, string $blocker, string $blocked): bool {
		return $this->cyclePath(edges: $edges, blocker: $blocker, blocked: $blocked) !== null;
	}//end wouldFormCycle()

	/**
	 * Derive the set of task UUIDs that are blocked, given edges and task statuses.
	 *
	 * Pure function — used by the backend for assertions and mirrored by the
	 * frontend `isBlocked` helper. A task is blocked when at least one edge
	 * names it as `blocked` whose `blocker` task exists in the supplied status
	 * map and is not in a resolved (`done`/`cancelled`) status. Edges whose
	 * blocker UUID does not resolve in the status map are ignored (tolerant
	 * reads). The status map is keyed by task UUID.
	 *
	 * @param array<int,array<string,mixed>> $edges Edge list.
	 * @param array<string,string> $statusById Map of task UUID → status string.
	 *
	 * @return array<int,string> Sorted, de-duplicated UUIDs of blocked tasks.
	 *
	 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
	 */
	public function deriveBlockedTaskIds(array $edges, array $statusById): array {
		$blockedIds = [];
		foreach ($this->blockingEnds(edges: $edges) as [$blockerId, $blockedId]) {
			// Tolerant read: an edge whose blocker task no longer resolves is ignored.
			if (array_key_exists($blockerId, $statusById) === true
				&& in_array($statusById[$blockerId], self::RESOLVED_STATUSES, true) === false
			) {
				$blockedIds[$blockedId] = true;
			}
		}

		$ids = array_keys($blockedIds);
		sort($ids);

		return $ids;
	}//end deriveBlockedTaskIds()

	/**
	 * Whether an edge blocks: one without a type (every edge made before types
	 * were used) or of type `blocks`. A related link, such as an imported
	 * start-to-start link, is drawn but never blocks.
	 *
	 * @param array<string,mixed> $edge The edge.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
	 */
	private function isBlocking(array $edge): bool {
		$type = (string)($edge['type'] ?? '');
		return ($type === '' || $type === 'blocks');
	}//end isBlocking()
}//end class
