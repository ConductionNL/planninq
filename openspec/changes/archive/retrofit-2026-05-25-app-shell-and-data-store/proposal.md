# Retrofit — app-shell-and-data-store

Describes observed behavior of 6 methods under `app-shell-and-data-store` as 3 new REQs. Code already exists — this change retroactively specifies it.

## Affected code units

- `lib/Controller/DashboardController.php::page()` — serves the SPA index template
- `lib/Controller/DashboardController.php::catchAll()` — serves the SPA for Vue history-mode deep links
- `src/store/modules/object.js::configure()` — sets the OpenRegister base URLs on the generic object store
- `src/store/modules/object.js::registerObjectType()` — registers a schema/register pair under a type key
- `src/store/modules/object.js::fetchObjects()` — fetches a typed object collection from OpenRegister
- `src/store/store.js::initializeStores()` — boot-time wiring of both object stores plus settings fetch

## Approach

- For each method: describe observed inputs, outputs, pre/postconditions, failure modes.
- Draft REQs that match the shipped behavior (not aspirational).
- Group the behavior into one capability: the foundational app shell that serves the
  single-page application and the generic OpenRegister data store that the shell boots.

Three REQs:
- **REQ-SPA-Page-Serving** — the backend controller that renders the SPA shell for the root page and for Vue history-mode deep links.
- **REQ-Generic-Object-Store** — the configurable, type-registered OpenRegister object store module that fetches typed collections.
- **REQ-Store-Bootstrap** — the boot routine that configures both object stores against OpenRegister and primes settings before the UI renders.

Source: openspec/coverage-report.md generated 2026-05-25. See the retrofit playbook.
