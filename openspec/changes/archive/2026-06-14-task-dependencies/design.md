# Design: Task Dependencies

## Summary

Directed dependency edges between tasks of the same project, stored as a new two-field `dependency` schema in OpenRegister; planix owns the one thing OR cannot: keeping the graph acyclic. Reads are plain OR API; writes route through a `DependencyService` that validates (self/duplicate/cross-project/cycle) before saving. The kanban card "Blocked" badge is derived client-side from edges + blocker statuses.

## Entity shape

Per the reservation in `tasks.md` Notes ("a separate `dependency` entity linking two tasks"):

```jsonc
// components.schemas.dependency
{
  "blocker": { "type": "string", "format": "uuid", "description": "UUID of the task that blocks" },
  "blocked": { "type": "string", "format": "uuid", "description": "UUID of the task that is blocked" },
  "required": ["blocker", "blocked"]
}
```

- One row per directed edge; "blocks" vs "is-blocked-by" is the same edge read from either end — no `type` field needed (the tasks.md note's "type" collapses into direction).
- No project field on the edge: project scoping is derived from the tasks (both must be in the same project at create time) — avoids a denormalized field drifting when a task moves project (see Move interplay below).

## Why a planix endpoint for writes (gate-17 analysis)

OR validates field shapes, not graph properties. Cycle prevention requires reading the existing edge set and walking it transactionally with the insert decision — domain logic, exactly what ADR-022 leaves in the leaf app. The endpoints therefore:

- `POST /dependencies` `{blocker, blocked}` → validation chain: distinct UUIDs (no self-edge) → both tasks exist and share a project (project-member check on the caller — IDOR guard) → edge not already present → **no path `blocker ←… ← blocked`** (DFS with visited set over the project's edges; if found, 422 with the path rendered: `"would create a cycle: Fix login → Deploy → QA sign-off → Fix login"`) → save via ObjectService.
- `DELETE /dependencies/{id}` → caller is a member of the edge's project → delete.

Reads (per-task "blocked by"/"blocks" lists, board edge set) never touch these endpoints — frontend queries the OR API directly.

Known race: two concurrent creates can each pass DFS and jointly close a cycle. Accepted at team scale; mitigated by tolerant reads (below). Not worth a lock table for MVP.

## Blocked badge derivation (frontend)

- Board load fetches the project's dependency edges alongside tasks (one extra OR list query).
- `isBlocked(task) = ∃ edge(blocker → task) where blocker.status ∉ {done, cancelled}`.
- Tolerant by construction: an edge whose blocker UUID doesn't resolve in the loaded task set is ignored (covers out-of-band deletes); derivation uses a visited set so a pathological cycle (race artifact) cannot loop.
- Badge UI: compact "Blocked" pill with link-variant icon on the card (next to the priority/due-date chips, consistent with the in-flight `task-due-date-warning` badge styling) and a fuller banner on the task detail listing the open blockers.
- Derived only — never written to `status`. A user may still set `status: blocked` manually; the two signals coexist (the enum is intent, the badge is fact).

## Interplay with existing behavior

| Existing rule | Interaction |
|---|---|
| Task delete cascades TimeEntries (`tasks.md`) | Cascade extends to all edges where the task is `blocker` or `blocked` |
| Move task to another project clears `column` (`tasks.md`) | Edges to tasks left behind become cross-project → invalid; the move flow removes the moved task's edges (same cascade), keeping the same-project invariant true by construction |
| WIP limit is soft (`kanban-board.md`) | Blocked indicator follows the same philosophy: signal, never a hard gate on drag |
| `status: blocked` enum value | Untouched; badge is independent and derived |

## Future-proofing notes

- "Task became unblocked" notification: once the OR engine grows relation-aware triggers it becomes an ADR-031 rule; until then explicitly out of scope (same engine-gap family as `due-date-reminder-dispatch`'s date-threshold note).
- Cross-project dependencies, relates-to/duplicates types, and Gantt rendering would extend the same edge schema — the two-field shape does not block them.

## Testing strategy

- **PHPUnit:** validation chain order and each rejection (self, duplicate, cross-project, non-member caller, cycle with path message); DFS correctness on diamond graphs (A→B, A→C, B→D, C→D is legal — not a cycle); delete cascade on task delete and project move; static schema assertion (`dependency` present, required fields).
- **Newman (API):** create edge → 201 and listed from both tasks; self-edge → 422; duplicate → 422; cycle (A→B, B→C, then C→A) → 422 naming the path; non-member create → 403; delete task → its edges gone. API/contract assertions in Newman, not Playwright.
- **Playwright (UI only):** task detail → add "Blocked by" via picker → both lists update; board card shows the Blocked badge; complete the blocker → badge disappears after refresh; attempt to add a cycle in the picker flow → inline error shown.
