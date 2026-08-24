## 1. Confirm the exact gap

- [x] 1.1 Run `node tests/l10n/check-l10n-parity.js` at `HEAD` and confirm it still reports the same 13 missing keys across all 33 required non-Dutch, non-English locales (re-check in case a parallel change has partially closed it)
- [x] 1.2 Confirm the 13 missing keys are exactly: `Open`, `In Progress`, `Done`, `Cancelled`, `Low`, `Normal`, `High`, `Urgent`, `Due soon`, `Overdue`, `Assigned to: {user}`, `No tasks`, `Could not move the task. Please try again.` (all sourced from `src/components/TaskCard.vue` and `src/views/ProjectBoard.vue`)

## 2. Backfill translations (33 locale files)

- [x] 2.1 Add real (non-English-fallback) translations for the 13 keys to each required locale's `l10n/<locale>.json`: `de`, `fr`, `es`, `it`, `bg`, `hr`, `cs`, `da`, `et`, `fi`, `el`, `hu`, `ga`, `lv`, `lt`, `mt`, `pl`, `pt`, `ro`, `sk`, `sl`, `sv`, `sq`, `is`, `nb`, `sr`, `bs`, `mk`, `uk`, `be`, `ru`, `tr`, `ca`, `lb`, `rm`
- [x] 2.2 For the plural/placeholder key `"Assigned to: {user}"`, preserve the `{user}` placeholder token verbatim in every translation (do not translate or drop the token)
- [x] 2.3 Leave `l10n/en.json` (identity source) and `l10n/nl.json` (already at parity) untouched — en/nl untouched for the 13 keys (a separate new key `Move task to another column`, introduced by the companion kanban keyboard change, was added to every locale incl. en/nl to keep the now-enforced gate green)

## 3. Wire the gate into the pipeline

- [x] 3.1 Add `"check:l10n": "node tests/l10n/check-l10n-parity.js"` to `package.json` `scripts`
- [x] 3.2 Add a step running `npm run check:l10n` to the planninq CI workflow (new `l10n-parity` job in `.github/workflows/code-quality.yml`, node-20 container, runs `npm run check:l10n`) so a future missing/empty translation fails the pipeline. NOTE: the frontend lint/test run via the shared reusable `quality.yml` (not editable in-repo), so the gate is added as a planninq-owned sibling job in the same workflow file.
- [x] 3.3 Confirm `npm run check:l10n` exits 0 locally after step 2 is complete

## 4. Verify

- [x] 4.1 Re-run `node tests/l10n/check-l10n-parity.js` and confirm `EXIT=0` with the summary reporting 0 missing / 0 empty across all 36 required locales
- [~] 4.2 `openspec validate fix-l10n-parity-gate --strict` passes — DEFERRED: the openspec CLI is not installed in this worktree (no `node_modules/.bin/openspec`, no global). Spec/tasks are well-formed; validation to run in an environment with the CLI.
