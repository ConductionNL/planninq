# Tasks: boards-card-display

## 1. Card colours

- [x] 1.1 Colour edge on `src/components/TaskCard.vue` (prop `edgeColour`, a 4 pixel inline-start border, `data-edge` for tests) from the first label or the priority token, chosen by `cardEdge()` in `src/utils/boardView.js`. Verify: vitest `tests/vitest/boardView.spec.js` "cardEdge" for each mode (the repo has no component-mount tooling, so the mode logic lives in the helper); Playwright `tests/e2e/board-view.spec.ts` checks the edge and the label chip.

## 2. Swimlanes

- [x] 2.1 `groupTasksBySwimlane(tasks, field, names)` helper with the "No value" group last, and `epicTitles()` for epic row names. Verify: vitest spec for assignee, priority and epic.
- [x] 2.2 Swimlane rows with collapsible headers "{name} ({count})" in `src/views/ProjectBoard.vue`. Verify: Playwright `tests/e2e/board-view.spec.ts` groups by assignee and collapses a row.
- [x] 2.3 Cross-row drag and the keyboard "Hand over to" menu PATCH the grouped field through `swimlanePatch()` (assignee through `responsiblePatch`, so the new responsible person leaves `sharedWith`); epic rows refuse; quick add in a row gives the card that row's value (`newCardFields()`). Verify: vitest spec on `swimlanePatch` and `newCardFields`; Playwright drags a card to another assignee row.

## 3. View menu and memory

- [x] 3.1 `src/components/BoardViewMenu.vue` with colour and grouping choices, stored per person and project by `lib/Service/BoardViewPreferenceService.php` in one user value `board_views` (project id to view, at most 100, unknown choices stored as none) through `POST /api/settings/user` `board_view`. Verify: PHPUnit `BoardViewPreferenceServiceTest` (per person and project, refusals, oldest dropped) and `SettingsServiceTest::testUpdateUserSettingsStoresTheBoardViewPerUser`; Playwright reloads and keeps the grouping.

## 4. Copy and verification

- [x] 4.1 New strings in all 36 locales. Verify: `npm run check:l10n` exits 0.
- [x] 4.2 `openspec validate boards-card-display --type change --strict` passes.
