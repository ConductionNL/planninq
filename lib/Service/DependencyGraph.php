<?php

/**
 * Planix Dependency Graph
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
 * @package  OCA\Planix\Service
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

namespace OCA\Planix\Service;

/**
 * Pure graph algorithms over the task-dependency edge list.
 */
final class DependencyGraph
{
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
     * @param array<int,array<string,mixed>> $edges   Existing edges (each with `blocker`/`blocked`).
     * @param string                         $blocker UUID of the proposed blocking task.
     * @param string                         $blocked UUID of the proposed blocked task.
     *
     * @return array<int,string>|null The cycle path of task UUIDs, or null when no cycle forms.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function cyclePath(array $edges, string $blocker, string $blocked): ?array
    {
        if ($blocker === $blocked) {
            return [$blocker, $blocker];
        }

        $adjacency = $this->buildAdjacency(edges: $edges);

        // Does `blocked` already reach `blocker`? DFS following blocker→blocked.
        $visited = [];
        $stack   = [[$blocked, [$blocked]]];

        while ($stack !== []) {
            [$node, $trail] = array_pop($stack);

            if ($node === $blocker) {
                // Adding blocker→blocked closes the loop: blocker → blocked → … → blocker.
                return array_merge([$blocker], $trail);
            }

            if (isset($visited[$node]) === true) {
                continue;
            }

            $visited[$node] = true;

            foreach (($adjacency[$node] ?? []) as $next) {
                if (isset($visited[$next]) === true) {
                    continue;
                }

                $stack[] = [$next, array_merge($trail, [$next])];
            }
        }//end while

        return null;

    }//end cyclePath()

    /**
     * Build an adjacency map (blocker UUID → list of blocked UUIDs) from edges.
     *
     * @param array<int,array<string,mixed>> $edges Existing edges.
     *
     * @return array<string,array<int,string>>
     */
    private function buildAdjacency(array $edges): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $from = (string) ($edge['blocker'] ?? '');
            $to   = (string) ($edge['blocked'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }

            $adjacency[$from][] = $to;
        }

        return $adjacency;

    }//end buildAdjacency()

    /**
     * Convenience boolean wrapper around {@see cyclePath()}.
     *
     * @param array<int,array<string,mixed>> $edges   Existing edges.
     * @param string                         $blocker UUID of the proposed blocking task.
     * @param string                         $blocked UUID of the proposed blocked task.
     *
     * @return bool True when the edge would form a cycle.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function wouldFormCycle(array $edges, string $blocker, string $blocked): bool
    {
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
     * @param array<int,array<string,mixed>> $edges      Edge list.
     * @param array<string,string>           $statusById Map of task UUID → status string.
     *
     * @return array<int,string> Sorted, de-duplicated UUIDs of blocked tasks.
     *
     * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
     */
    public function deriveBlockedTaskIds(array $edges, array $statusById): array
    {
        $blockedIds = [];

        foreach ($edges as $edge) {
            $blockerId = (string) ($edge['blocker'] ?? '');
            $blockedId = (string) ($edge['blocked'] ?? '');
            if ($blockerId === '' || $blockedId === '') {
                continue;
            }

            // Tolerant read: ignore an edge whose blocker task no longer resolves.
            if (array_key_exists($blockerId, $statusById) === false) {
                continue;
            }

            if (in_array($statusById[$blockerId], self::RESOLVED_STATUSES, true) === false) {
                $blockedIds[$blockedId] = true;
            }
        }

        $ids = array_keys($blockedIds);
        sort($ids);

        return $ids;

    }//end deriveBlockedTaskIds()
}//end class
