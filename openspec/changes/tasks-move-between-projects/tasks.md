# Tasks: tasks-move-between-projects

## 1. Server

- [x] 1.1 Make `lib/Listener/TaskDependencyCleanupListener.php` react to `ObjectUpdatingEvent` on a task whose `project` changes and call `removeEdgesForTask()`; register it in `lib/AppInfo/Application.php`. Verify: PHPUnit test in `tests/Unit/Listener/` for a project change and for an unrelated update. Built: `TaskDependencyCleanupListener` now also handles `ObjectUpdatingEvent` and is registered for it in `Application.php`; PHPUnit `testAProjectChangeRemovesTheEdges` and `testAnUnrelatedUpdateKeepsTheEdges`.

## 2. Task move

- [x] 2.1 `moveTaskToProject(taskId, targetProjectId)` in `src/store/projects.js`: PATCH project, clear column and order, move subtasks, clear non-member people. Verify: vitest spec with a mocked fetch. Built: `moveTaskToProject` with `moveTaskPatch`; covered in `tests/vitest/taskMove.spec.js`.
- [ ] 2.2 "Move to project" dialog (`src/dialogs/TaskMoveDialog.vue`) on TaskDetail and the card menu, listing links to be removed and people to be cleared. Verify: Playwright e2e moves a task and finds it in the target's backlog. — built (`TaskMoveDialog.vue`, a Move to project button on TaskDetail for top-level tasks); not on the card menu; the e2e is not run: needs a live instance
- [ ] 2.3 "Logged under {project}" note for entries booked on another project on TaskDetail. Verify: vitest mount test. — built (`timeLoggedElsewhere`, a note on TaskDetail; helper covered in `taskMove.spec.js`); no mount test: the repo has no component-mount harness

## 3. Column move and copy

- [ ] 3.1 Column header menu actions "Move column to project" and "Copy column to project" on the column-driven board. Verify: vitest spec for the store actions; Playwright e2e copies a column. — built in the lane header menu (`copyColumnToProject`, `moveColumnToProject`, store tests in `taskMove.spec.js`); the menu is owner-only like the other column actions; the e2e is not run: needs a live instance

## 4. Copy and verification

- [x] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Built: new strings translated in all 36 locales; `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate tasks-move-between-projects --type change --strict` passes. — not run: openspec CLI not installed here
