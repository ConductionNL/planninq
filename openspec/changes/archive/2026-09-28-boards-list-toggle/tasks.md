# Tasks: boards-list-toggle

## 1. List view

- [x] 1.1 `boardListRows(tasks, columns)` in `src/utils/columnHelpers.js`: one row per card on the board, in lane order then card order, with its column. Verify: vitest spec in `tests/vitest/boardColumns.spec.js`. Red on 'boardListRows is not a function', then green.
- [x] 1.2 Board and List switch in `src/views/ProjectBoard.vue`, the view kept in `?view=list`, and a list table (title, column, priority, due date) whose rows open the task. Verify: Playwright e2e switches to the list, finds the board's cards, reloads and stays on the list. Spec file `tests/e2e/board-list.spec.ts`.

## 2. Copy and verification

- [x] 2.1 New strings in every catalogue the repo ships. Verify: `npm run check:l10n` exits 0. Four strings in all 37 catalogues.
- [x] 2.2 `openspec validate boards-list-toggle --type change --strict` passes.
