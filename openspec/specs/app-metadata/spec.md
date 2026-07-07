# app-metadata Specification

## Purpose
TBD - created by archiving change fix-license-metadata. Update Purpose after archive.
## Requirements
### Requirement: The manifest declares the app's true EUPL-1.2 licence

`appinfo/info.xml` `<licence>` MUST declare EUPL-1.2 — the licence the app is actually
distributed under (the `LICENSE` file, `composer.json`, `publiccode.yml`, README, and
every PHP `@license` docblock already say EUPL-1.2). The manifest MUST NOT continue to
declare the scaffold-default `agpl`. Because Nextcloud's App-Store `info.xsd` only accepts
the EUPL licence value from Nextcloud 31 onward, the manifest MUST also raise
`<nextcloud min-version>` to `31` (dropping the already-EOL 28–30 line) so the honest EUPL
declaration validates, and MUST bump `<version>` per the cache-bust convention. The exact
EUPL token used MUST be the one the deployed Nextcloud `app-info.xsd` accepts at
`min-version="31"`.

#### Scenario: The manifest licence matches the shipped LICENSE and validates

- **WHEN** the manifest is validated against `info.xsd` and the repository's licence files
- **THEN** `info.xml` `<licence>` MUST declare EUPL-1.2 (the value accepted by the store xsd at `min-version="31"`)
- **AND** `<nextcloud min-version>` MUST be `31` (so the EUPL value validates), `max-version` unchanged at `34`
- **AND** the declared licence MUST match `LICENSE` / `composer.json` / `publiccode.yml` / the PHP `@license` headers (all EUPL-1.2)

#### Scenario: No AGPL self-licence reference remains

- **WHEN** the tree is grepped for the app's own licence declaration
- **THEN** no `agpl`/`AGPL` value MUST remain that refers to planix's own licence (dependency-allowlist mentions of other licences are unaffected)

@e2e exclude manifest-licence consistency + xsd validity is a static metadata check, not a UI flow.

