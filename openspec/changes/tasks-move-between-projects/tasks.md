# Tasks: tasks-move-between-projects

## 1. Server

- [x] 1.1 Make `lib/Listener/TaskDependencyCleanupListener.php` react to `ObjectUpdatingEvent` on a task whose `project` changes and call `removeEdgesForTask()`; register it in `lib/AppInfo/Application.php`. Verify: PHPUnit test in `tests/Unit/Listener/` for a project change and for an unrelated update. Done in `tests/unit/Listener/TaskDependencyCleanupListenerTest.php` (a move, an update within the project, a refused move, another app's object); the listener-table golden is re-recorded with the new subscription.

## 2. Task move

- [x] 2.1 `moveTaskToProject(taskId, targetProjectId)` in `src/store/projects.js`: PATCH project, clear column and order, move subtasks, clear non-member people. Verify: vitest spec with a mocked fetch. `moveTaskToProject(task, target, subtasks)` over `src/utils/taskMove.js`; `tests/vitest/taskMove.spec.js`.
- [ ] 2.2 "Move to project" dialog (`src/dialogs/TaskMoveDialog.vue`) on TaskDetail and the card menu, listing links to be removed and people to be cleared. Verify: Playwright e2e moves a task and finds it in the target's backlog. The dialog is built and opens from TaskDetail and from the card menu on the board (`card-move-to-project`); only the e2e, which needs a live instance, is left open.
- [x] 2.3 "Logged under {project}" note for entries booked on another project on TaskDetail. Verify: vitest mount test. `timeLoggedElsewhere()` feeds the note; covered in `tests/vitest/taskMove.spec.js` (the repo's vitest has no DOM to mount in).

## 3. Column move and copy

- [ ] 3.1 Column header menu actions "Move column to project" and "Copy column to project" on the column-driven board. Verify: vitest spec for the store actions; Playwright e2e copies a column. Built: `ColumnActions` offers both on the board header, `src/dialogs/ColumnMoveDialog.vue` picks a project the person owns, and `moveColumnToProject` / `copyColumnToProject` in `src/store/projects.js` append the column last on the target board (helpers in `src/utils/taskMove.js`); `tests/vitest/columnMove.spec.js`. Only the e2e, which needs a live instance, is left open.

## 4. Copy and verification

- [x] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate tasks-move-between-projects --type change --strict` passes.
