# Tasks: tasks-subtasks-checklist

## 1. Schema

- [x] 1.1 Add optional `checklist` (array of `{ id, text, done }`) to the task schema. Verify: PHPUnit register descriptor test; `npm run check:schema-l10n` exits 0. Done: task schema 0.7.0 `checklist` (array; items require id, text, done; nested properties left undeclared for gate-51), register 0.22.0, app 0.2.23-unstable.20260930050000; title and description in 36 locales. Test: PlanninqRegisterSchemaTest::testTaskKeepsAChecklist (real validator, control).

## 2. Subtasks

- [x] 2.1 Subtasks section on `src/views/TaskDetail.vue`: list children, "2 of 5 done", add field calling `createTask` with `parent`; hidden on a task that has a parent. Verify: Playwright e2e adds two subtasks and sees them on the board with the parent chip. Done: TaskDetail Subtasks section (children from the project's tasks, '{done} of {total} done', add field; hidden on a subtask). A subtask lands in the parent's lane. Tests: tests/vitest/taskBreakdown.spec.js newSubtask (validated against the real schema) and subtaskProgress; tests/e2e/task-breakdown.spec.ts.
- [x] 2.2 Parent chip on `src/components/TaskCard.vue`. Verify: vitest mount test. Done: TaskCard 'Part of {title}' chip (board passes parentTitle). Test: e2e (node vitest has no mount).

## 3. Checklist

- [x] 3.1 Checklist section on TaskDetail (add, tick, reorder by keyboard and drag, remove) and a "3/5" count on the card. Verify: vitest spec for the array operations; Playwright e2e ticks an item. Done: TaskDetail Checklist (add, tick, move up/down by menu, drag, remove; the whole array is PATCHed) and the '3/5' chip on the card. Tests: taskBreakdown.spec.js checklist operations and checklistCount; e2e ticks an item.

## 4. Rollups

- [x] 4.1 Subtask estimate and logged-time sums in the TaskDetail time section, as a pure helper. Verify: vitest spec for the helper. Done: `subtaskRollup` fed by the project's time entries; 'Subtasks: {estimate} estimated, {logged} logged' and 'Total estimate: {total}'. Test: taskBreakdown.spec.js subtaskRollup; e2e.

## 5. Duplicate and delete

- [x] 5.1 "Duplicate" on the task page and in the card menu, copying the task, checklist (unticked) and subtasks. Verify: vitest spec on the store action with a mocked object store. Done: store `duplicateTask` (duplicatePayload: title 'Copy of {title}', open, description, priority, labels, lane, unticked checklist; no dates, people, time, key, reporter), from TaskDetail and the card menu. Tests: tests/vitest/taskTreeStore.spec.js (real pinia store, object store and fetch replaced; red without the actions), taskBreakdown.spec.js duplicatePayload; e2e.
- [x] 5.2 Parent branch in `src/dialogs/TaskDeleteDialog.vue`. Verify: vitest mount test for both choices. Done: TaskDeleteDialog asks 'Keep subtasks as separate tasks' or 'Delete subtasks too' (store `deleteTaskTree`; nothing is deleted when any task that would go has logged time). Tests: taskTreeStore.spec.js both modes and the refusal; e2e keeps the subtasks.

## 6. Copy and verification

- [x] 6.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Done in 36 locales. `npm run check:l10n` 0.
- [x] 6.2 `openspec validate tasks-subtasks-checklist --type change --strict` passes. Done.
