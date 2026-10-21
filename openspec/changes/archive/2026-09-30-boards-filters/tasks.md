# Tasks: boards-filters

## 1. Model

- [x] 1.1 `src/utils/boardFilter.js` with the filter model, `matchesFilter`, and query-string encode and decode. Verify: vitest spec for every dimension, both operators, "Me" and "Unassigned", and a round trip through the query string.

## 2. Filter bar

- [x] 2.1 `src/components/BoardFilterBar.vue` replacing the label chips in `src/views/ProjectBoard.vue`, with the count line and "Clear filters". Verify: Playwright e2e filters by "Assignee is not Me" and by priority urgent, then reloads and sees the same cards.

## 3. Saved filters

- [x] 3.1 Add the `boardFilter` schema with its authorization to `lib/Settings/planninq_register.json`. Verify: PHPUnit register test; `npm run check:schema-l10n` exits 0.
- [x] 3.2 Save, apply, rename and delete saved filters from a menu in the bar; share with the project. Verify: Playwright `tests/e2e/board-filters.spec.ts` saves, applies and deletes a filter as its owner; the second-member half needs a second account CI lacks (exclude note in the spec), covered by the read-rule and owner-listener PHPUnit tests and the Newman folder "Board Filters".

## 4. Copy and verification

- [x] 4.1 New strings in `l10n/en.json`, `l10n/nl.json` and the other 34 locales. Verify: `npm run check:l10n` exits 0.
- [x] 4.2 `openspec validate boards-filters --type change --strict` passes.
