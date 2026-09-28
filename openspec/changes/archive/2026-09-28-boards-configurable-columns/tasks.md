# Tasks: boards-configurable-columns

## 1. Schema and defaults

- [x] 1.1 Add optional `status` to the column schema and narrow column create, update and delete to the project owner and admins in `lib/Settings/planninq_register.json`. Verify: PHPUnit register test; `npm run check:schema-l10n` exits 0. Built: the owner rule lives in `lib/Listener/ColumnOwnerGuardListener.php` on the pre-save and pre-delete events (OpenRegister cannot match a rule against another object's owner); the schema keeps its members rule. Tests: `tests/unit/Listener/ColumnOwnerGuardListenerTest.php`, `tests/unit/Service/BoardColumnServiceTest.php` (column payload against the real schema).
- [x] 1.2 Provide `default_columns` as initial state mapped to column objects, and read it in `getDefaultColumns`. Verify: PHPUnit test on the mapping; vitest spec on `getDefaultColumns` with and without state. Built: no page controller exists (the AppHost serves the page), so `ProjectController::create` makes the columns on the server through `BoardColumnService::createDefaultColumns`. Tests: `BoardColumnServiceTest`, `ProjectControllerTest::testCreateSucceedsForNonAdminWhenPolicyAllowsAll`.

## 2. Repair

- [x] 2.1 Repair step in `lib/Repair/` that creates missing columns and assigns a column to every non-cancelled task without one. Verify: PHPUnit test over a fixture with every status, run twice to prove idempotence. Built: `lib/Repair/AssignBoardColumns.php` over `BoardColumnService::assignColumns`. Tests: `tests/unit/Repair/AssignBoardColumnsTest.php`, `BoardColumnServiceTest::testAssignColumnsPlacesTasksByStatusAndIsIdempotent`.

## 3. Board

- [x] 3.1 Fetch the project's columns in `src/views/ProjectBoard.vue` and group tasks by `column`, sorted by `columnOrder`. Verify: vitest spec on a new `groupTasksByColumn` helper. Test: `tests/vitest/boardColumns.spec.js` (groupTasksByColumn).
- [x] 3.2 Moving a card writes `column`, `columnOrder` and the mapped status, with rollback on failure. Verify: vitest spec on the patch builder; Playwright e2e moves a card to Done and reloads. Test: `boardColumns.spec.js` (buildMovePatch); e2e `tests/e2e/board-columns.spec.ts`.
- [x] 3.2b `lib/Listener/TaskCompletionListener.php`: stamp `completedAt` when status becomes done and clear it when status leaves done, on create and update, registered in `lib/AppInfo/Application.php`. Verify: PHPUnit tests in `tests/Unit/Listener/` for enter, leave, unrelated update, and a create with status done. Tests: `tests/unit/Listener/TaskCompletionListenerTest.php`; wiring from the caller in `tests/unit/AppInfo/BoardColumnWiringTest.php`.
- [x] 3.3 WIP count and warning style with text in the lane header. Verify: vitest mount test over and under the limit. Test: `boardColumns.spec.js` (wipState); the vitest suite runs in node without a DOM, so the helper is tested rather than a mount.
- [x] 3.4 Drag within a lane and "Move up" and "Move down" in the card menu. Verify: Playwright e2e reorders two cards and reloads. Test: `boardColumns.spec.js` (orderPatchesForStep); e2e `board-columns.spec.ts`.

## 4. Column management

- [x] 4.1 Owner-only lane header menu and "Add column". Verify: Playwright e2e adds, renames and removes a column. Built: `src/components/ColumnActions.vue`, `src/dialogs/ColumnEditDialog.vue`. Tests: e2e `board-columns.spec.ts`; `boardColumns.spec.js` (columnPayload against the real schema).
- [x] 4.2 "Columns" tab in `src/components/ProjectSettingsSidebar.vue`, including remove-with-target and the last-done-column guard. Verify: vitest mount test for the guard. Built: `src/components/ColumnSettingsList.vue`, `src/dialogs/ColumnRemoveDialog.vue`. Test: `boardColumns.spec.js` (canRemoveColumn); no DOM mount in this vitest setup.

## 5. Copy and verification

- [x] 5.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Built in all 37 catalogues.
- [x] 5.2 `openspec validate boards-configurable-columns --type change --strict` passes. Run with openspec 1.12.0.
