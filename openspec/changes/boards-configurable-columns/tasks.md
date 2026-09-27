# Tasks: boards-configurable-columns

## 1. Schema and defaults

- [ ] 1.1 Add optional `status` to the column schema and narrow column create, update and delete to the project owner and admins in `lib/Settings/planninq_register.json`. Verify: PHPUnit register test; `npm run check:schema-l10n` exits 0.
- [ ] 1.2 Provide `default_columns` as initial state mapped to column objects, and read it in `getDefaultColumns`. Verify: PHPUnit test on the mapping; vitest spec on `getDefaultColumns` with and without state.

## 2. Repair

- [ ] 2.1 Repair step in `lib/Repair/` that creates missing columns and assigns a column to every non-cancelled task without one. Verify: PHPUnit test over a fixture with every status, run twice to prove idempotence.

## 3. Board

- [ ] 3.1 Fetch the project's columns in `src/views/ProjectBoard.vue` and group tasks by `column`, sorted by `columnOrder`. Verify: vitest spec on a new `groupTasksByColumn` helper.
- [ ] 3.2 Moving a card writes `column`, `columnOrder`, the mapped status and `completedAt` for a done column, with rollback on failure. Verify: vitest spec on the patch builder; Playwright e2e moves a card to Done and reloads.
- [ ] 3.3 WIP count and warning style with text in the lane header. Verify: vitest mount test over and under the limit.
- [ ] 3.4 Drag within a lane and "Move up" and "Move down" in the card menu. Verify: Playwright e2e reorders two cards and reloads.

## 4. Column management

- [ ] 4.1 Owner-only lane header menu and "Add column". Verify: Playwright e2e adds, renames and removes a column.
- [ ] 4.2 "Columns" tab in `src/components/ProjectSettingsSidebar.vue`, including remove-with-target and the last-done-column guard. Verify: vitest mount test for the guard.

## 5. Copy and verification

- [ ] 5.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 5.2 `openspec validate boards-configurable-columns --type change --strict` passes.
