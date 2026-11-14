# Tasks: tasks-search-and-bulk

## 1. Search

- [x] 1.1 Add `matchesSearch(task, term)` to `src/utils/taskHelpers.js`. Verify: vitest spec (case, whitespace, description, key).
- [x] 1.2 Search field in the board header of `src/views/ProjectBoard.vue`, applied in `visibleTasks`; Escape clears. Verify: Playwright e2e in `tests/e2e/kanban-board.spec.ts` types a term and sees only matching cards and adjusted counts.
- [x] 1.3 The same field on the backlog list. Verify: Playwright e2e on the backlog.

## 2. Bulk actions

- [x] 2.1 Row selection and "Select all" on the backlog list. Verify: vitest mount test.
- [x] 2.2 `bulkUpdateTasks(ids, patchOrOp)` in `src/store/projects.js` with a concurrency limit and a per-task result. Verify: vitest spec with one failing PATCH.
- [x] 2.3 Bulk bar with Change status, Assign to, Priority and Labels (add or remove), and the result toast. Verify: Playwright e2e changes the status of three tasks.

## 3. Copy and verification

- [x] 3.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 3.2 `openspec validate tasks-search-and-bulk --type change --strict` passes.

## Build notes (2026-11-14)

- 1.2, 1.3: a search field in the board header and in the backlog toolbar, applied with
  `matchesSearch` in `visibleTasks` and the backlog `rows`; Escape clears it. The Playwright
  checks were not run.
- 2.1: a checkbox per backlog row and "Select all" for the visible rows; a task a search or filter
  hides is never part of a bulk change (`src/utils/bulkHelpers.js`). This repo has no component
  mount setup for vitest (node environment), so the selection is verified through the helper
  specs in `tests/vitest/bulkHelpers.spec.js` rather than a mount test.
- 2.3: `BacklogBulkBar.vue` with Status, Assign to, Priority, Add label and Remove label; the
  backlog applies the change through `bulkUpdateTasks`, skips tasks it would not alter, reports
  the updated and failed counts once and keeps the failed tasks selected. The Playwright check
  was not run.
- 3.1: ten new strings in all 37 catalogues (`npm run check:l10n` passes); plurals are avoided
  with "Selected: {count}"-style messages.
- Left: 3.2 (`openspec validate`, needs the CLI) and the Playwright runs.
