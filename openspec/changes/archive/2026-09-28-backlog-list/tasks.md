# Tasks: backlog-list

## 1. List

- [x] 1.1 Replace the placeholder in `src/views/ProjectBacklog.vue` with a list of the project's column-less, not-done tasks in rank order, with an empty state "Nothing in the backlog". Verify: vitest spec on a `backlogTasks(tasks)` helper; Playwright e2e opens the backlog of a seeded project. Built: `src/views/ProjectBacklog.vue`, `src/utils/backlogHelpers.js` backlogTasks(). Tests: `tests/vitest/backlog.spec.js`; e2e `tests/e2e/backlog.spec.ts`.
- [x] 1.2 "New task" on the backlog creating a task without a column at the bottom. Verify: Playwright e2e. Built as an inline "New task" field on the page (there is no TaskFormDialog at HEAD); store action `createTask`. Tests: `backlog.spec.js` (newBacklogTask payload validated against the real task schema); e2e.

## 2. Rank, sort and filter

- [x] 2.1 Drag handle and "Move up" and "Move down" writing sparse `columnOrder`. Verify: vitest spec on the rank helper; Playwright e2e reorders and reloads. Built: drag a row onto another (rank sort only) and Move up / Move down, through `orderFor` and `orderPatchesForStep` in `src/utils/columnHelpers.js`. Tests: `backlog.spec.js` (ranking); e2e.
- [x] 2.2 Sort by rank, priority, due date or created, kept in the query string; dragging disabled when not sorted by rank. Verify: vitest spec on the comparators. Test: `backlog.spec.js` (sortBacklog).
- [x] 2.3 The board filter bar from `boards-filters` on the backlog, plus a "Cancelled" filter. Verify: Playwright e2e filters by priority. Built without `boards-filters` (not built yet): a priority filter and a Cancelled switch on the backlog, in the query string. Test: `backlog.spec.js` (filterBacklog, backlogTasks cancelled).

## 3. Moving

- [x] 3.1 "Move to board" with a column menu on a backlog row, and "Move to backlog" in the board card menu. Verify: Playwright e2e moves a task both ways. Built: backlog row menu lists the board columns; board card menu gains "Move to backlog" (`moveToBacklogPatch`). Tests: `backlog.spec.js`; e2e.

## 4. Spec hygiene, copy and verification

- [x] 4.1 At archive, retire the "Placeholder until task management is implemented" scenario of `openspec/specs/projects.md`. Verify: the archived spec no longer mentions the placeholder. Done in this change: the scenario is replaced in `openspec/specs/projects.md`.
- [x] 4.2 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Built in all 37 catalogues; also the three inherited board strings.
- [x] 4.3 `openspec validate backlog-list --type change --strict` passes. Run with openspec 1.12.0.
