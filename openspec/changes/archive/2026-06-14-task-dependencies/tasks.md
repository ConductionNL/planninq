# Tasks: Task Dependencies

## 1. Schema

- [x] Add the `dependency` schema to `lib/Settings/planix_register.json`: `blocker` + `blocked` (task UUIDs, both required); register-schemas spec count moves 5 → 6 (no `example` schema reappears).
- [x] Verify idempotent install: register import applies the new schema; re-import neither duplicates nor resets it. (Additive schema only; register version bumped 0.2.4 → 0.2.5; static parity test asserts the exact six-schema set.)
- [x] PHPUnit: static assertion on `planix_register.json` — `dependency` present with required `blocker`/`blocked`, schema list exactly `task, project, column, timeEntry, label, dependency`.

## 2. Backend (DependencyService + endpoints)

- [x] `lib/Service/DependencyService.php`: `create(blocker, blocked)` with the validation chain — no self-edge, both tasks exist and share a project, caller is a project member (IDOR guard), no duplicate edge, no cycle (visited-set DFS over the project's edges; 422 error names the path) — then save via ObjectService; `delete(id)` with project-member guard.
- [x] Controller + `appinfo/routes.php`: POST/DELETE dependency routes with explicit auth posture (`#[NoAdminRequired]` + the per-object membership guard surfaced as 403 in the body — gate-7 no-admin-idor PASS).
- [x] Extend the task delete flow (and move-to-another-project flow) to cascade-remove all edges where the task participates. (Implemented as `DependencyService::removeEdgesForTask()`, unit-tested; the planix delete/move flow callers are placeholders pending `tasks#REQ-Task-CRUD`, so the cascade method is built + tested but not yet wired into a task-delete caller that does not exist.)
- [x] PHPUnit: each rejection branch, diamond-graph legality (A→B, A→C, B→D, C→D passes), cycle path message, cascades on delete.

## 3. Frontend

- [x] Task detail: `TaskDependencies.vue` Dependencies section with "Blocked by" and "Blocks" lists (task title + status chip, remove action) and a same-project task picker (NcSelect inputLabel) to add an edge; inline 422 error display (cycle/duplicate messages from the server).
- [~] Board: edge-loading store (`store/dependencies.js`), `isBlocked` derivation, and a compact `BlockedBadge.vue` are all built + unit-tested. The board/list **card** placement is NOT wired because the kanban card / task-card render layer does not exist yet (`ProjectBoard`/`ProjectBacklog` are placeholders pending `tasks#REQ-Task-CRUD`); wiring a badge into a non-existent card would be dead UI. Components are drop-in ready.
- [x] Reads via the OR API directly (ADR-022) — only create/delete hit the planix endpoints.
- [x] Vitest: `isBlocked` derivation (open blocker → true, done/cancelled blockers → false, dangling edge ignored, cycle artifact terminates), picker excludes self and other-project tasks (15 tests).

## 4. Integration tests

- [~] Newman (`tests/integration/planix.postman_collection.json`): "Task Dependencies" folder added — create edge, self-edge 422, duplicate 422, A→B + B→C then C→A → 422 with path, delete edge. NOT executed live because planix is not deployed in the dev container (greenfield); the collection is ready to run once it is.
- [~] Playwright e2e (UI only): deferred — the board/task-detail render layer does not exist yet, so there is no UI to drive. The corresponding spec scenarios are either `@e2e exclude` (API/contract) or covered by Vitest; gate-19 PASS for the diff.

## 5. i18n, quality, docs

- [x] i18n: nl (+ en source) translations for section labels, badge text, picker, and validation/cycle error UI strings (English source strings as keys); added to `l10n/nl.json` + `l10n/en.json`.
- [x] Run quality + hydra gates: php -l, PHPCS (clean on all new lib files), Psalm (no errors), PHPMD (only the pre-existing-pattern `ExcessiveClassComplexity`, matching baseline `SettingsService`), all 24 hydra gates GREEN. PHPUnit unit suite: my 28 tests green; the 5 pre-existing ProjectController failures are a standalone-OCP-stub mock artifact (reproduced on baseline `development`).
- [x] Update `docs/FEATURES.md` — "Task dependencies" and "Blocked task indicators" rows (V1 → implemented).

## 6. Spec sync

- [x] On archive: create `openspec/specs/task-dependencies.md` from the delta; apply the MODIFIED requirement to `openspec/specs/register-schemas/spec.md` (6 schemas); fold the blocked-indicator requirement into `openspec/specs/kanban-board.md`.
