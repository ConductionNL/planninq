# Tasks: tasks-assignment-priority-labels

## 1. Schema

- [ ] 1.1 Add optional `sharedWith` (array of uids) to the task schema in `lib/Settings/planninq_register.json`, with a title and description in both languages. Verify: PHPUnit register descriptor test; `npm run check:schema-l10n` exits 0.

## 2. People

- [ ] 2.1 Add a people picker (project members, avatar and display name) for `assignedTo` and `sharedWith` on `src/views/TaskDetail.vue` and in `src/dialogs/TaskFormDialog.vue`. Verify: Playwright e2e assigns a member and sees the avatar on the card.
- [ ] 2.2 Show avatars with display names on `src/components/TaskCard.vue` instead of the raw uid. Verify: vitest mount test.
- [ ] 2.3 Add `sharedWith` to the audience in `lib/Listener/TaskActivityListener.php`. Verify: PHPUnit test in `tests/Unit/Listener/`.

## 3. Priority

- [ ] 3.1 Priority select on TaskDetail saving through `updateTask`, and a "Priority" submenu in the card action menu of `src/views/ProjectBoard.vue`. Verify: vitest mount test for the menu; Playwright e2e changes priority from the board.

## 4. Labels

- [ ] 4.1 Label multi-select on TaskDetail writing `task.labels`. Verify: Playwright e2e attaches a label and filters the board by it.

## 5. Copy and verification

- [ ] 5.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 5.2 `openspec validate tasks-assignment-priority-labels --type change --strict` passes.
