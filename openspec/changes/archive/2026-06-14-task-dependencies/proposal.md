# Proposal: Task Dependencies

## Summary

Add blocks / is-blocked-by dependencies between tasks: a `dependency` schema (directed edge `blocker → blocked`), a Dependencies section on the task detail to add/remove links, **server-side cycle detection** so the dependency graph stays a DAG, and a "Blocked" indicator on kanban cards while any blocker is unresolved. The data model already reserves this — `tasks.md` Notes: "Task dependencies (V1) will be a separate `dependency` entity linking two tasks with a type (blocks/is-blocked-by)" and `kanban-board.md` lists "Blocked task indicators" — but nothing specs it. This is the category-defining flow feature (Jira, Linear, Plane, OpenProject all have it; Deck does not), chosen as the wave-2 expansion pick.

## Motivation

Planix's positioning is flow-based work management for dev/IT teams ("between Deck's simplicity and Jira's complexity"). Flow management without dependencies is incomplete: WIP limits tell you a column is full, but only blocked-by relations tell you *why* work cannot move. FEATURES.md tiers "Task dependencies (blocks / is-blocked-by)" V1 with justification "Flow management" and "Blocked task indicators" V1 — and the task schema's existing `status: blocked` enum value currently has no structured cause. Among the V1 candidates (recurring tasks, milestones, swimlanes), dependencies have the highest leverage: they complete the kanban story, give `status: blocked` meaning, and the spec's own notes already committed to the entity shape.

## Affected Projects

- [x] Project: `planix` — `dependency` schema, DependencyService with cycle detection, task detail Dependencies section, board card blocked indicator
- [ ] Project: `openregister` — none (plain schema + objects; the graph validation is planix domain logic)

## Scope

### In Scope

- New `dependency` schema in `planix_register.json`: `blocker` (task UUID, required), `blocked` (task UUID, required) — one row per directed edge
- Add/remove dependencies from the task detail: "Blocked by" and "Blocks" lists with a task picker scoped to the same project
- **Cycle detection (server-side):** creating an edge that would make the dependency graph cyclic MUST be rejected with an explanatory error naming the conflict; self-dependencies and duplicate edges also rejected — enforced in a planix `DependencyService` endpoint, not in the frontend alone
- Blocked indicator: a task with ≥ 1 blocker not in status `done`/`cancelled` shows a "Blocked" badge on its kanban card and task detail; the badge clears when the last open blocker completes
- Cascade on task delete: deleting a task removes all dependency edges it participates in (extends the existing delete behavior, like TimeEntries)

### Out of Scope

- Hard blocking of moves — moving a blocked task into Done is allowed (planix philosophy: soft signals, like the WIP soft limit); the indicator is informational
- Automatic `status: blocked` writes — the status enum stays user-controlled; the badge is derived, not persisted
- Other dependency types (relates-to, duplicates, finish-to-start scheduling) and cross-project dependencies — V2 candidates
- Gantt/timeline rendering of the dependency graph
- A "task became unblocked" notification — a natural ADR-031 follow-up once the OR engine supports relation-aware triggers (same family as the date-threshold gap noted in `due-date-reminder-dispatch`)

## Approach

Storage is two-field OR objects: edge `(blocker, blocked)` in a new `dependency` schema, both UUID references to tasks in the same project. Reads (lists, badge derivation) come straight from the OR API per ADR-022.

Writes go through one planix endpoint, because edge creation needs graph validation OR cannot do:

- `DependencyService::create(blocker, blocked)` — rejects self-edges, duplicate edges, cross-project edges, and any edge where `blocked` already reaches `blocker` through existing edges (DFS over the project's dependency edges; the error names the offending path, e.g. "would create a cycle: A → B → C → A"). On success, saves the edge via ObjectService.
- `DependencyService::delete(id)` — removes an edge (project-member gated like task edits).
- Task delete cascade: the existing task-delete flow also removes all edges where the task is `blocker` or `blocked`.

Validation-then-save is real domain logic, so the endpoint is not a gate-17 pass-through. The board derives "blocked" client-side from the loaded edges + task statuses (no extra requests: edges for the project load with the board).

## New Dependencies

None (no new composer/npm packages).

## Cross-Project Dependencies

None blocking. Unlike `due-date-reminder-dispatch`, nothing here needs OR engine extensions — plain schema + objects + planix domain logic.

## Impact

- `lib/Settings/planix_register.json` — new `dependency` schema (register-schemas spec moves from 5 to 6 schemas)
- `lib/Service/DependencyService.php`, `lib/Controller/` + `appinfo/routes.php` — create/delete endpoints with cycle detection (explicit auth posture, project-member guard — no IDOR)
- Task delete flow — cascade now covers dependency edges (in addition to TimeEntries)
- `src/views/` task detail — Dependencies section ("Blocked by" / "Blocks" lists + picker)
- `src/views/ProjectBoard.vue` + task card component — "Blocked" badge derivation
- `openspec/specs/` — new `task-dependencies` capability; `register-schemas` MODIFIED (schema list); `kanban-board` gains the blocked-indicator requirement

## Risks

### Risk 1: Cycle check races with concurrent edge creation
**Severity:** Low–Medium — two simultaneous creates could each pass DFS and jointly form a cycle. **Mitigation:** acceptable at planix team scale; the spec requires detection at read time too (badge derivation tolerates a cycle without infinite loops — visited-set DFS), and a follow-up integrity check can prune; documented as a known limit in design.md.

### Risk 2: Badge derivation cost on large boards
**Severity:** Low — edges load once per project; derivation is O(edges) with memoization per render. Board pagination/scale work is an existing concern, not new here.

### Risk 3: Orphaned edges from out-of-band task deletes
**Severity:** Low — tasks deleted via the OR API directly (bypassing planix's delete flow) could leave edges. **Mitigation:** badge derivation ignores edges whose blocker no longer resolves; the cascade covers the in-app path; spec pins the tolerant-read behavior.

## Rollback Strategy

Remove the Dependencies section, badge, endpoints, and the `dependency` schema from `planix_register.json`. Existing edge objects become inert without the schema's consumers; a re-import without the schema leaves task data untouched. No task fields are modified by this change (`status` semantics unchanged), so rollback cannot corrupt tasks.
