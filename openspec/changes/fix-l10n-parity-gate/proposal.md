---
kind: code
---

# Proposal: Fix the Failing l10n Parity Gate and Wire It Into CI

## Why

Planninq carries its own translation-completeness gate,
`tests/l10n/check-l10n-parity.js`, which asserts that every one of 33 required
European-language locale files under `l10n/*.json` translates every key
present in the English source (`l10n/en.json`) — a bar stricter than ADR-007's
company-wide minimum (English + Dutch only). Running it against `HEAD` today:

```
$ node tests/l10n/check-l10n-parity.js; echo "EXIT=$?"
l10n-parity [planninq]: 36 required locales; checked 1 translation set(s)
l10n-parity: FAIL — required language support is incomplete:
  • backend (.json) de: 13 missing key(s), 0 empty value(s) of 199
      missing: "Open"
      missing: "In Progress"
      missing: "Done"
      missing: "Cancelled"
      missing: "Low"
      missing: "Normal"
      missing: "High"
      missing: "Urgent"
      … +5 more missing
  [... identical 13-key gap repeats for all 33 required non-Dutch locales ...]
EXIT=1
```

All 33 required locales (`de`, `fr`, `es`, `it`, `bg`, `hr`, `cs`, `da`, `et`,
`fi`, `el`, `hu`, `ga`, `lv`, `lt`, `mt`, `pl`, `pt`, `ro`, `sk`, `sl`, `sv`,
`sq`, `is`, `nb`, `sr`, `bs`, `mk`, `uk`, `be`, `ru`, `tr`, `ca`, `lb`, `rm`)
are missing the same 13 keys — the task-status labels (`Open`, `In Progress`,
`Done`, `Cancelled`) and priority labels (`Low`, `Normal`, `High`, `Urgent`,
plus 5 more) introduced in `src/components/TaskCard.vue` (status/priority chip
`computed` maps) and never back-filled into `l10n/*.json`. `l10n/nl.json` is
at full parity (0 missing) — only the Dutch translation was kept current.

The gate is real and well-designed (its own docblock at
`tests/l10n/check-l10n-parity.js:1-38` explains exactly this failure mode:
*"a new English string ships and the other languages silently fall back to
English with a green pipeline — the app slowly stops 'fully supporting'
those languages"*) — but it is currently **invisible**:

```
$ grep -rn "check-l10n-parity" .github/workflows/*.yml package.json Makefile
(no matches)
```

Confirmed against `HEAD`:
- No `package.json` script invokes it (`grep -n '"scripts"' -A20 package.json`
  has no `l10n`/`check:l10n` entry).
- No planninq workflow under `.github/workflows/` runs it.
- `composer check:strict` / `npm run lint` (the gates that DO run in CI) never
  touch `l10n/*.json`.

So the script has been failing at `HEAD` with no CI signal since whichever PR
added the TaskCard status/priority chips — a real, silent regression that the
gate was purpose-built to prevent, undermined by never being wired up.

Per the fleet's `feedback_i18n-keys-english.md` rule and ADR-007, English is
the i18n source of truth; this change only restores parity in already-required
locale files and wires the existing gate into the pipeline — it does not
change any key names or add new user-facing strings.

## What Changes

- Add the 13 missing keys (see list above, full set in `tasks.md`) with real
  translations to all 33 required-locale `l10n/*.json` files (not merely
  copies of the English value — the parity gate permits identical values
  for genuine cognates/proper nouns, but these are ordinary short words with
  real translations in every target language).
- Add an `npm run check:l10n` script to `package.json` that runs
  `node tests/l10n/check-l10n-parity.js`.
- Wire `npm run check:l10n` into the planninq CI workflow (same job that runs
  `npm run lint` / `npm test`) so a future missing-key regression fails the
  pipeline instead of shipping silently.
- No source code changes — `src/components/TaskCard.vue` keys are unchanged;
  this only backfills the locale files and adds the enforcement wiring.

**Not BREAKING**: additive-only (new translation values + new CI step).
