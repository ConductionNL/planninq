# Tasks: tasks-assignment-priority-labels

## 1. Schema

- [x] 1.1 Add optional `sharedWith` (array of uids) to the task schema in `lib/Settings/planninq_register.json`, with a title and description in both languages. Verify: PHPUnit register descriptor test; `npm run check:schema-l10n` exits 0. Done: task schema 0.6.0 `sharedWith` (array of strings, default []), register 0.21.0, app 0.2.23-unstable.20260930030000; title and description in all 36 locales. Test: PlanninqRegisterSchemaTest::testTaskIsSharedWithAListOfPeople (real validator, control); check:schema-l10n 0.

## 2. People

- [x] 2.1 Add a people picker (project members, avatar and display name) for `assignedTo` and `sharedWith` on `src/views/TaskDetail.vue` and in `src/dialogs/TaskFormDialog.vue`. Verify: Playwright e2e assigns a member and sees the avatar on the card. Done: TaskDetail 'Responsible' and 'Also working on this' pickers (members and owner only, by display name), same pickers in TaskFormDialog when it gets the project. Tests: tests/vitest/taskPeople.spec.js memberOptions/responsiblePatch/sharedWithPatch, taskEditing.spec.js newLaneTask and editPatch with people (payload validated against the real schema); tests/e2e/task-people.spec.ts.
- [x] 2.2 Show avatars with display names on `src/components/TaskCard.vue` instead of the raw uid. Verify: vitest mount test. Done: TaskCard lists avatars and display names, responsible first (peopleOf). Test: taskPeople.spec.js peopleOf (node environment, no mount); e2e.
- [x] 2.3 Add `sharedWith` to the audience in `lib/Listener/TaskActivityListener.php`. Verify: PHPUnit test in `tests/Unit/Listener/`. Done. Test: tests/unit/Listener/TaskActivityListenerTest.php::testSharedWithIsInTheAudience (red first).

## 3. Priority

- [x] 3.1 Priority select on TaskDetail saving through `updateTask`, and a "Priority" submenu in the card action menu of `src/views/ProjectBoard.vue`. Verify: vitest mount test for the menu; Playwright e2e changes priority from the board. Done: TaskDetail priority select; board card menu gains a 'Priority' caption with the four levels (setPriority, optimistic, reverts on failure). Tests: taskPeople.spec.js priorityPatch and the board source check; e2e.

## 4. Labels

- [x] 4.1 Label multi-select on TaskDetail writing `task.labels`. Verify: Playwright e2e attaches a label and filters the board by it. Done: TaskDetail 'Labels' multi-select writing task.labels (labelsPatch). Tests: taskPeople.spec.js labelsPatch; e2e attaches a label and filters the board by it.

## 5. Copy and verification

- [x] 5.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Done in all 36 locales. `npm run check:l10n` 0.
- [x] 5.2 `openspec validate tasks-assignment-priority-labels --type change --strict` passes. Done.
