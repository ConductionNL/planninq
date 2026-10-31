# Tasks: tasks-move-between-projects

## 1. Server

- [ ] 1.1 Make `lib/Listener/TaskDependencyCleanupListener.php` react to `ObjectUpdatingEvent` on a task whose `project` changes and call `removeEdgesForTask()`; register it in `lib/AppInfo/Application.php`. Verify: PHPUnit test in `tests/Unit/Listener/` for a project change and for an unrelated update.

## 2. Task move

- [ ] 2.1 `moveTaskToProject(taskId, targetProjectId)` in `src/store/projects.js`: PATCH project, clear column and order, move subtasks, clear non-member people. Verify: vitest spec with a mocked fetch.
- [ ] 2.2 "Move to project" dialog (`src/dialogs/TaskMoveDialog.vue`) on TaskDetail and the card menu, listing links to be removed and people to be cleared. Verify: Playwright e2e moves a task and finds it in the target's backlog.
- [ ] 2.3 "Logged under {project}" note for entries booked on another project on TaskDetail. Verify: vitest mount test.

## 3. Column move and copy

- [ ] 3.1 Column header menu actions "Move column to project" and "Copy column to project" on the column-driven board. Verify: vitest spec for the store actions; Playwright e2e copies a column.

## 4. Copy and verification

- [ ] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate tasks-move-between-projects --type change --strict` passes.
