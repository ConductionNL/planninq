# Tasks: fix-license-metadata

## 1. Correct the manifest licence value

- [ ] `appinfo/info.xml`: change `<licence>agpl</licence>` to
  `<licence>eupl</licence>` (matching the shipped `LICENSE` file, EUPL-1.2).
  **[config]**

## 2. Raise the Nextcloud version floor for `eupl` validity

- [ ] `appinfo/info.xml`: change `<nextcloud min-version="28" max-version="34"/>`
  to `<nextcloud min-version="31" max-version="34"/>` — the store `info.xsd`
  accepts the `eupl` licence value only from Nextcloud 31. Leave `max-version`
  at 34. **[config]**

## 3. Cache-bust version bump

- [ ] `appinfo/info.xml`: bump `<version>` per the immutable-asset cache-bust
  convention (patch bump from the current value). **[config]**

## 4. Verify

- [ ] Confirm no other file needs editing: `LICENSE`, `composer.json`,
  `publiccode.yml`, `README.md`, and PHP `@license` docblocks already declare
  EUPL-1.2 — assert they are unchanged. **[config]**
- [ ] `xmllint --noout appinfo/info.xml` (or `composer run lint` if wired) to
  confirm the manifest still validates against `info.xsd`. **[config]**
- [ ] Grep the tree for any remaining `agpl`/`AGPL` occurrence that refers to
  planix's own licence (dependency-allowlist references in README are about
  dependencies and stay). **[config]**
