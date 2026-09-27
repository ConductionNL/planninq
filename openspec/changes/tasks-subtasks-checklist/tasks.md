# Tasks: tasks-subtasks-checklist

## 1. Schema

- [ ] 1.1 Add optional `checklist` (array of `{ id, text, done }`) to the task schema. Verify: PHPUnit register descriptor test; `npm run check:schema-l10n` exits 0.

## 2. Subtasks

- [ ] 2.1 Subtasks section on `src/views/TaskDetail.vue`: list children, "2 of 5 done", add field calling `createTask` with `parent`; hidden on a task that has a parent. Verify: Playwright e2e adds two subtasks and sees them on the board with the parent chip.
- [ ] 2.2 Parent chip on `src/components/TaskCard.vue`. Verify: vitest mount test.

## 3. Checklist

- [ ] 3.1 Checklist section on TaskDetail (add, tick, reorder by keyboard and drag, remove) and a "3/5" count on the card. Verify: vitest spec for the array operations; Playwright e2e ticks an item.

## 4. Rollups

- [ ] 4.1 Subtask estimate and logged-time sums in the TaskDetail time section, as a pure helper. Verify: vitest spec for the helper.

## 5. Duplicate and delete

- [ ] 5.1 "Duplicate" on the task page and in the card menu, copying the task, checklist (unticked) and subtasks. Verify: vitest spec on the store action with a mocked object store.
- [ ] 5.2 Parent branch in `src/dialogs/TaskDeleteDialog.vue`. Verify: vitest mount test for both choices.

## 6. Copy and verification

- [ ] 6.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 6.2 `openspec validate tasks-subtasks-checklist --type change --strict` passes.
