# Tasks: boards-card-display

## 1. Card colours

- [ ] 1.1 Colour edge on `src/components/TaskCard.vue` from the first label or the priority token, controlled by a prop. Verify: vitest mount test for each mode.

## 2. Swimlanes

- [ ] 2.1 `groupTasksBySwimlane(tasks, field)` helper with the "No value" group last. Verify: vitest spec for assignee, priority and epic.
- [ ] 2.2 Swimlane rows with collapsible headers and counts in `src/views/ProjectBoard.vue`. Verify: Playwright e2e groups by assignee and collapses a row.
- [ ] 2.3 Cross-row drag and keyboard targets PATCH the grouped field; epic rows refuse. Verify: vitest spec on the patch builder; Playwright e2e drags a card to another assignee row.

## 3. View menu and memory

- [ ] 3.1 `src/components/BoardViewMenu.vue` with colour and grouping choices, stored as a per-user preference per project. Verify: PHPUnit test on the preference key in `SettingsService`; Playwright e2e reloads and keeps the grouping.

## 4. Copy and verification

- [ ] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate boards-card-display --type change --strict` passes.
