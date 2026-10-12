# Tasks: tasks-search-and-bulk

## 1. Search

- [x] 1.1 Add `matchesSearch(task, term)` to `src/utils/taskHelpers.js`. Verify: vitest spec (case, whitespace, description, key). Built: `matchesSearch` in `taskHelpers.js`; `tests/vitest/taskSearchBulk.spec.js`.
- [ ] 1.2 Search field in the board header of `src/views/ProjectBoard.vue`, applied in `visibleTasks`; Escape clears. Verify: Playwright e2e in `tests/e2e/kanban-board.spec.ts` types a term and sees only matching cards and adjusted counts. — built (search field in the board header, Escape clears, columns count the filtered cards); e2e not run: needs a live instance
- [ ] 1.3 The same field on the backlog list. Verify: Playwright e2e on the backlog. — built (search field on the backlog); e2e not run: needs a live instance

## 2. Bulk actions

- [ ] 2.1 Row selection and "Select all" on the backlog list. Verify: vitest mount test. — built (row checkboxes and Select all on the backlog); no mount test: the repo has no component-mount harness
- [x] 2.2 `bulkUpdateTasks(ids, patchOrOp)` in `src/store/projects.js` with a concurrency limit and a per-task result. Verify: vitest spec with one failing PATCH. Built: `bulkUpdateTasks` with a concurrency limit and a per-task result; vitest with one failing PATCH.
- [ ] 2.3 Bulk bar with Change status, Assign to, Priority and Labels (add or remove), and the result toast. Verify: Playwright e2e changes the status of three tasks. — built: Change status, Change priority, Assign to (with Unassigned) and Add or Remove label on the backlog bulk bar, with a result toast and failed tasks kept selected; vitest covers per-task patches and a failing write (`tests/vitest/taskSearchBulk.spec.js`); e2e not run: needs a live instance

## 3. Copy and verification

- [x] 3.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Built: new strings translated in all 36 locales; `npm run check:l10n` exits 0.
- [ ] 3.2 `openspec validate tasks-search-and-bulk --type change --strict` passes. — not run: openspec CLI not installed here
