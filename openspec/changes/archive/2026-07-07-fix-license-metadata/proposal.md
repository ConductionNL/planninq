---
kind: config
---

# Proposal: fix-license-metadata

## Summary

Correct a licence-metadata inconsistency: `appinfo/info.xml` declares
`<licence>agpl</licence>` while **every other artifact in the repository states
EUPL-1.2** — the `LICENSE` file (`EUROPEAN UNION PUBLIC LICENCE v. 1.2`),
`composer.json` (`"license": "EUPL-1.2"`), `publiccode.yml` (`license:
EUPL-1.2`), `README.md`, `project.md`, and the `@license EUPL-1.2` docblock on
every PHP file. The Nextcloud app manifest is the single source that mislabels
the app's licence, which is both a legal-accuracy defect (the manifest shown in
the App Store contradicts the shipped `LICENSE`) and a fleet-policy violation
(Conduction apps ship under EUPL-1.2).

## Motivation

Conduction's licensing policy is EUPL-1.2 for all apps. Planix's `LICENSE` file
is already the verbatim EUPL-1.2 text and all machine-readable licence fields
(`composer.json`, `publiccode.yml`, PHP SPDX/`@license` tags) agree — so the app
**is** EUPL-1.2 in substance. Only the Nextcloud manifest still carries the
scaffold default `agpl`, which the Nextcloud App Store surfaces to installing
admins. Correcting it removes a contradiction between the store listing and the
bundled licence.

The Nextcloud App Store `info.xsd` only accepts the `eupl` value for
`<licence>` from **Nextcloud 31 onward**. Planix currently declares
`<nextcloud min-version="28" max-version="34"/>`, so declaring `eupl` while
keeping `min-version="28"` would fail store validation. This change therefore
raises the floor to `min-version="31"` — the minimum that makes the honest
`eupl` declaration valid — dropping support for the already-EOL Nextcloud
28–30 line.

## Affected Projects

- [x] Project: `planix` — `appinfo/info.xml` `<licence>` value and
  `<nextcloud min-version>`; `<version>` bump. No code, no register, no UI, no
  frontend.

## Scope

### In Scope

- `appinfo/info.xml`: `<licence>agpl</licence>` → `<licence>eupl</licence>`.
- `appinfo/info.xml`: `<nextcloud min-version="28" max-version="34"/>` →
  `min-version="31"` (max-version unchanged) so the `eupl` value validates
  against the store `info.xsd`.
- `appinfo/info.xml`: `<version>` patch bump per the cache-bust convention.

### Out of Scope

- The `LICENSE` file (already EUPL-1.2 — unchanged).
- `composer.json`, `publiccode.yml`, README, PHP SPDX headers (already
  EUPL-1.2 — unchanged).
- Any code, register schema, or frontend change.
- Re-licensing (this is a correction to reflect the existing licence, not a
  relicense).

## Impact

- **Legal accuracy**: the App Store manifest matches the shipped `LICENSE`.
- **Compatibility**: drops Nextcloud 28–30 (all EOL); keeps 31–34. Planix's
  `min-version` was 28 only as a scaffold default — the app has no 28–30-only
  code path.
- **Risk**: minimal. A one-line enum correction plus a version-floor bump; no
  behaviour changes.

## Dependencies

None. Independent of the three in-flight changes (`adopt-apphost`,
`portal-identity`, `portal-contribution`) — none of them touch `<licence>` or
`min-version`.
