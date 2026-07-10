# i18n Locale Completeness

**Spec refs**: ADR-007 (i18n, hydra/openspec/architecture) — English source of truth, en/nl minimum parity
**Standards**: ISO 639-1

## ADDED Requirements

### Requirement: Required-locale translation parity is enforced in CI

Every key present in `l10n/en.json` MUST have a non-empty, real translation in
every locale file listed as "required" by `tests/l10n/check-l10n-parity.js`
(the official language of every EU country plus Russian and Turkish — 33
locales beyond English/Dutch). The parity check MUST run as part of the
planix CI pipeline (via an `npm run check:l10n` script), not merely exist as
an unwired local script, so a newly-added English string that is not
back-filled into the required locales fails the pipeline instead of shipping
silently.

**Feature tier**: MVP

#### Scenario: New English key is added without a translation

- GIVEN a developer adds a new `t('planix', '…')` call introducing a key not
  yet present in any locale file
- WHEN `npm run check:l10n` runs in CI
- THEN the pipeline MUST fail, naming the missing key and every required
  locale that lacks it

#### Scenario: All required locales are at parity

- GIVEN every required locale's `l10n/<locale>.json` contains a non-empty
  translation for every key in `l10n/en.json`
- WHEN `npm run check:l10n` runs
- THEN it MUST exit 0

#### Scenario: A locale carries an English-fallback value for a translatable key

- GIVEN a required locale is missing a translation for a key that has a real
  equivalent in that language (not a cognate/proper noun/acronym)
- WHEN the parity check runs
- THEN the key MUST NOT be silently accepted merely because a same-valued
  key exists — the backfill in this change replaces the placeholder gap with
  an actual translation, not a copy of the English string
