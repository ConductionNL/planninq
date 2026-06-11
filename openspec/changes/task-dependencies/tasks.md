# Tasks: Task Dependencies

## 1. Schema

- [ ] Add the `dependency` schema to `lib/Settings/planix_register.json`: `blocker` + `blocked` (task UUIDs, both required); register-schemas spec count moves 5 → 6 (no `example` schema reappears).
- [ ] Verify idempotent install: register import applies the new schema; re-import neither duplicates nor resets it.
- [ ] PHPUnit: static assertion on `planix_register.json` — `dependency` present with required `blocker`/`blocked`, schema list exactly `task, project, column, timeEntry, label, dependency`.

## 2. Backend (DependencyService + endpoints)

- [ ] `lib/Service/DependencyService.php`: `create(blocker, blocked)` with the validation chain — no self-edge, both tasks exist and share a project, caller is a project member (IDOR guard), no duplicate edge, no cycle (visited-set DFS over the project's edges; 422 error names the path) — then save via ObjectService; `delete(id)` with project-member guard.
- [ ] Controller + `appinfo/routes.php`: POST/DELETE dependency routes with explicit auth posture (`#[NoAdminRequired]` + the per-object membership guard in the body — gate-7 no-admin-idor).
- [ ] Extend the task delete flow (and move-to-another-project flow) to cascade-remove all edges where the task participates.
- [ ] PHPUnit: each rejection branch, diamond-graph legality (A→B, A→C, B→D, C→D passes), cycle path message, cascades on delete and move.

## 3. Frontend

- [ ] Task detail: Dependencies section with "Blocked by" and "Blocks" lists (task title + status chip, remove action) and a same-project task picker to add an edge; inline 422 error display (cycle/duplicate messages).
- [ ] Board: load the project's dependency edges with the board; derive `isBlocked` (any blocker not `done`/`cancelled`; unresolvable blocker UUIDs ignored; visited-set safe); compact "Blocked" badge on the kanban card consistent with the due-date badge styling; blocked banner with open-blocker list on task detail.
- [ ] Reads via the OR API directly (ADR-022) — only create/delete hit the planix endpoints.
- [ ] Vitest: `isBlocked` derivation (open blocker → true, done/cancelled blockers → false, dangling edge ignored, cycle artifact terminates), picker excludes self and other-project tasks, lists render both directions.

## 4. Integration tests

- [ ] Newman (`tests/integration/*.postman_collection.json`): create edge → listed from both ends; self-edge 422; duplicate 422; A→B, B→C then C→A → 422 with path; non-member → 403; delete blocker task → edge gone and blocked task no longer blocked.
- [ ] Playwright e2e (UI only): add "Blocked by" from task detail → both lists update; board card shows Blocked badge; complete the blocker → badge clears; creating a cycle via the picker shows the inline error. Reference the unexcluded scenarios from the spec deltas (gate-19).

## 5. i18n, quality, docs

- [ ] i18n: nl translations for section labels, badge text, picker, and validation/cycle error messages (English source strings as keys).
- [ ] Run `composer check:strict` + hydra gates (gate-5 route-auth, gate-7 no-admin-idor on the membership guard, gate-17). Fix any pre-existing quality issues encountered.
- [ ] Update `docs/FEATURES.md` — "Task dependencies" and "Blocked task indicators" rows (V1 → implemented).

## 6. Spec sync

- [ ] On archive: create `openspec/specs/task-dependencies.md` from the delta; apply the MODIFIED requirement to `openspec/specs/register-schemas/spec.md` (6 schemas); fold the blocked-indicator requirement into `openspec/specs/kanban-board.md`; update the `tasks.md` Notes line (dependencies no longer "V1 will be").
