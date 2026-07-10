## 1. Confirm the exact gap

- [ ] 1.1 Run `node tests/l10n/check-l10n-parity.js` at `HEAD` and confirm it still reports the same 13 missing keys across all 33 required non-Dutch, non-English locales (re-check in case a parallel change has partially closed it)
- [ ] 1.2 Confirm the 13 missing keys are exactly: `Open`, `In Progress`, `Done`, `Cancelled`, `Low`, `Normal`, `High`, `Urgent`, `Due soon`, `Overdue`, `Assigned to: {user}`, `No tasks`, `Could not move the task. Please try again.` (all sourced from `src/components/TaskCard.vue` and `src/views/ProjectBoard.vue`)

## 2. Backfill translations (33 locale files)

- [ ] 2.1 Add real (non-English-fallback) translations for the 13 keys to each required locale's `l10n/<locale>.json`: `de`, `fr`, `es`, `it`, `bg`, `hr`, `cs`, `da`, `et`, `fi`, `el`, `hu`, `ga`, `lv`, `lt`, `mt`, `pl`, `pt`, `ro`, `sk`, `sl`, `sv`, `sq`, `is`, `nb`, `sr`, `bs`, `mk`, `uk`, `be`, `ru`, `tr`, `ca`, `lb`, `rm`
- [ ] 2.2 For the plural/placeholder key `"Assigned to: {user}"`, preserve the `{user}` placeholder token verbatim in every translation (do not translate or drop the token)
- [ ] 2.3 Leave `l10n/en.json` (identity source) and `l10n/nl.json` (already at parity) untouched

## 3. Wire the gate into the pipeline

- [ ] 3.1 Add `"check:l10n": "node tests/l10n/check-l10n-parity.js"` to `package.json` `scripts`
- [ ] 3.2 Add a step running `npm run check:l10n` to the planix CI workflow, in the same job/stage that runs `npm run lint` / `npm test`, so a future missing/empty translation fails the pipeline
- [ ] 3.3 Confirm `npm run check:l10n` exits 0 locally after step 2 is complete

## 4. Verify

- [ ] 4.1 Re-run `node tests/l10n/check-l10n-parity.js` and confirm `EXIT=0` with the summary reporting 0 missing / 0 empty across all 36 required locales
- [ ] 4.2 `openspec validate fix-l10n-parity-gate --strict` passes
