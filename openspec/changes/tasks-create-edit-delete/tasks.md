# Tasks: tasks-create-edit-delete

## 1. Store

- [ ] 1.1 Add `createTask(projectId, data)` to `src/store/projects.js`: saves a task with defaults `status: open`, `priority: normal` and `reporter` set to the current uid, and returns it. Verify: a vitest spec in `tests/vitest/` asserts the payload and the defaults against a mocked object store.
- [ ] 1.2 Add `deleteTask(taskId)` that refuses when the task has time entries and otherwise deletes through the object store. Verify: vitest spec for both branches.

## 2. Dialogs

- [ ] 2.1 Create `src/dialogs/TaskFormDialog.vue` (title required, Markdown description with preview, status, priority) for create and edit; edit sends only changed fields through `updateTask`. Verify: vitest mount test that edit issues a PATCH with only the changed field.
- [ ] 2.2 Create `src/dialogs/TaskDeleteDialog.vue` with the logged-time branch offering "Cancel task". Verify: vitest mount test for both branches.

## 3. Board

- [ ] 3.1 Add a "New task" button to the board header and a quick-add field at the foot of each lane in `src/views/ProjectBoard.vue`; Enter creates the task in that lane and keeps focus. Verify: Playwright e2e in `tests/e2e/kanban-board.spec.ts` creates a task through quick add and sees the card.
- [ ] 3.2 Show a plain-text excerpt of the description on `src/components/TaskCard.vue`. Verify: vitest spec for the excerpt helper (strips Markdown, 140 characters).

## 4. Task page

- [ ] 4.1 Add "Edit" and "Delete task" actions to `src/views/TaskDetail.vue`; show "Delete task" only to the reporter, the project owner and admins. Verify: Playwright e2e edits a title and deletes a task without time.
- [ ] 4.2 Render the description with `NcRichText` and `use-markdown`. Verify: vitest mount test that `<script>` in a description renders as text.

## 5. Schema

- [ ] 5.1 Narrow the task schema's delete authorization in `lib/Settings/planninq_register.json` to reporter, project owner and admin. Verify: PHPUnit register descriptor test asserts the rule; live check that a plain member gets 403 on DELETE of another member's task.

## 6. Copy and verification

- [ ] 6.1 Add the new strings to `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 6.2 `openspec validate tasks-create-edit-delete --type change --strict` passes.
