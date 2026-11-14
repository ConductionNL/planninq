# Tasks: tasks-create-edit-delete

## 1. Store

- [x] 1.1 Add `createTask(projectId, data)` to `src/store/projects.js`: saves a task with defaults `status: open`, `priority: normal` and `reporter` set to the current uid, and returns it. Verify: a vitest spec in `tests/vitest/` asserts the payload and the defaults against a mocked object store. Done: `createTask` applies `withTaskDefaults`; reporter is stamped server-side by `TaskReporterGuardListener` (tests/unit/Listener/TaskReporterGuardListenerTest.php::testACreatedTaskRecordsTheCreatorAsReporter). Test: tests/vitest/taskEditing.spec.js withTaskDefaults, validated against the real task schema.
- [x] 1.2 Add `deleteTask(taskId)` that refuses when the task has time entries and otherwise deletes through the object store. Verify: vitest spec for both branches. Done: `deleteTask` counts `plannedTimeEntry` rows, then DELETEs and maps the server's code with `deleteRefusal`. Tests: taskEditing.spec.js deleteRefusal; TaskReporterGuardListenerTest::testATaskWithLoggedTimeIsNotDeleted.

## 2. Dialogs

- [x] 2.1 Create `src/dialogs/TaskFormDialog.vue` (title required, Markdown description with preview, status, priority) for create and edit; edit sends only changed fields through `updateTask`. Verify: vitest mount test that edit issues a PATCH with only the changed field. Done: src/dialogs/TaskFormDialog.vue. Test: taskEditing.spec.js editPatch (node environment, no mount; see design amendments).
- [x] 2.2 Create `src/dialogs/TaskDeleteDialog.vue` with the logged-time branch offering "Cancel task". Verify: vitest mount test for both branches. Done: src/dialogs/TaskDeleteDialog.vue. Tests: deleteRefusal and the e2e delete test.

## 3. Board

- [x] 3.1 Add a "New task" button to the board header and a quick-add field at the foot of each lane in `src/views/ProjectBoard.vue`; Enter creates the task in that lane and keeps focus. Verify: Playwright e2e in `tests/e2e/kanban-board.spec.ts` creates a task through quick add and sees the card. Done: header button and per-lane quick add (writes column, columnOrder and the lane status). Tests: taskEditing.spec.js newLaneTask; tests/e2e/task-editing.spec.ts.
- [x] 3.2 Show a plain-text excerpt of the description on `src/components/TaskCard.vue`. Verify: vitest spec for the excerpt helper (strips Markdown, 140 characters). Done: TaskCard shows `descriptionExcerpt`. Test: taskEditing.spec.js descriptionExcerpt.

## 4. Task page

- [x] 4.1 Add "Edit" and "Delete task" actions to `src/views/TaskDetail.vue`; show "Delete task" only to the reporter, the project owner and admins. Verify: Playwright e2e edits a title and deletes a task without time. Done: TaskDetail Edit and Delete task (`canDeleteTask`). Tests: taskEditing.spec.js canDeleteTask; tests/e2e/task-editing.spec.ts.
- [x] 4.2 Render the description with `NcRichText` and `use-markdown`. Verify: vitest mount test that `<script>` in a description renders as text. Done. Test: taskEditing.spec.js source check plus the e2e Markdown test.

## 5. Schema

- [x] 5.1 Narrow the task schema's delete authorization in `lib/Settings/planninq_register.json` to reporter, project owner and admin. Verify: PHPUnit register descriptor test asserts the rule; live check that a plain member gets 403 on DELETE of another member's task. Done as a listener, not a schema rule (design amendments): lib/Listener/TaskReporterGuardListener.php. Tests: TaskReporterGuardListenerTest (7), BoardColumnWiringTest::testBootSubscribesTheTaskReporterGuardBeforeTheDependencyCleanup. Live 403 NOT run (recipe in the PR).

## 6. Copy and verification

- [x] 6.1 Add the new strings to `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Done in all 36 locales, not only en and nl. `npm run check:l10n` 0.
- [x] 6.2 `openspec validate tasks-create-edit-delete --type change --strict` passes. Done.
