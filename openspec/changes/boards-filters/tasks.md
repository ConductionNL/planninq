# Tasks: boards-filters

## 1. Model

- [ ] 1.1 `src/utils/boardFilter.js` with the filter model, `matchesFilter`, and query-string encode and decode. Verify: vitest spec for every dimension, both operators, "Me" and "Unassigned", and a round trip through the query string.

## 2. Filter bar

- [ ] 2.1 `src/components/BoardFilterBar.vue` replacing the label chips in `src/views/ProjectBoard.vue`, with the count line and "Clear filters". Verify: Playwright e2e filters by "Assignee is not Me" and by priority urgent, then reloads and sees the same cards.

## 3. Saved filters

- [ ] 3.1 Add the `boardFilter` schema with its authorization to `lib/Settings/planninq_register.json`. Verify: PHPUnit register test; `npm run check:schema-l10n` exits 0.
- [ ] 3.2 Save, apply, rename and delete saved filters from a menu in the bar; share with the project. Verify: Playwright e2e saves a shared filter as one member and applies it as another.

## 4. Copy and verification

- [ ] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.2 `openspec validate boards-filters --type change --strict` passes.
