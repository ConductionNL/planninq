---
retrofit: true
---

# App Shell & Data Store — Retrofit Spec

This is a new capability describing the Planix application shell — the backend
controller that serves the single-page application and the generic frontend
OpenRegister object store that the shell boots. The behavior already ships on
`development`; this spec retroactively captures it because the SPA-serving
controller and the generic object-store module had no owning REQ (previously
unannotatable).

## Implementation Requirements

### Requirement: SPA Page Serving [MVP]

The system MUST serve the Planix single-page application from a backend
controller, both for the root page and for Vue history-mode deep links, so a
bookmarked or refreshed in-app URL resolves to the same SPA shell.

#### Scenario: Render the dashboard page

- GIVEN an authenticated Nextcloud user
- WHEN they request the Planix root page route
- THEN `DashboardController::page()` MUST return a `TemplateResponse` for the
  `index` template of the `planix` app
- AND the route MUST be annotated `@NoAdminRequired` and `@NoCSRFRequired` so
  any authenticated user can load the shell

#### Scenario: Serve the SPA for a deep link

- GIVEN an authenticated user navigates directly to an in-app route
  (e.g. `/projects/:id/backlog`) handled by Vue history mode
- WHEN the server receives the request
- THEN `DashboardController::catchAll()` MUST delegate to `page()` and return
  the same `index` `TemplateResponse`
- AND the client-side router MUST then resolve the deep-link route

#### Notes

- `catchAll()` is a pure delegation to `page()` — both return an identical
  shell; the actual routing happens client-side.

---

### Requirement: Generic OpenRegister Object Store [MVP]

The frontend MUST provide a configurable, type-registered Pinia object store
that reads object collections from OpenRegister, so feature stores can fetch
any registered schema/register pair without duplicating fetch plumbing.

#### Scenario: Configure base URLs

- GIVEN the object store is freshly created
- WHEN `configure({ baseUrl, schemaBaseUrl })` is called
- THEN the store MUST persist `baseUrl` and `schemaBaseUrl` for use by
  subsequent fetches

#### Scenario: Register an object type

- GIVEN the object store is configured
- WHEN `registerObjectType(type, schema, register)` is called
- THEN the store MUST record the `{ schema, register }` pair under `type`
- AND it MUST initialise an empty objects array for that type if none exists

#### Scenario: Fetch a typed collection

- GIVEN a type has been registered
- WHEN `fetchObjects(type, params)` is called
- THEN the store MUST issue a GET to `baseUrl` with `register`, `schema`, and
  any extra `params` as query parameters, including the Nextcloud request token
- AND on a successful response it MUST store and return `data.results` (or the
  raw body when `results` is absent)
- AND on an unregistered type it MUST warn and return an empty array without
  issuing a request
- AND on a network/parse error it MUST log the error and return an empty array
- AND it MUST toggle the per-type `loading` flag around the request

---

### Requirement: Store Bootstrap [MVP]

The system MUST run a single boot routine that wires the OpenRegister object
stores and primes settings before the UI renders, so views can assume a
configured data layer.

#### Scenario: Initialize stores on app boot

- GIVEN the Planix app (or admin settings root) is mounting
- WHEN `initializeStores()` runs
- THEN it MUST `configure()` the local object store with the OpenRegister
  objects and schemas base URLs
- AND it MUST `configure()` the shared `@conduction/nextcloud-vue` object store
  with the OpenRegister objects base URL
- AND it MUST await `settingsStore.fetchSettings()` so OpenRegister
  availability and admin flags are known before render
- AND it MUST return the settings and object store handles

#### Notes

- Two object stores are configured: the app-local `src/store/modules/object.js`
  store and the shared `@conduction/nextcloud-vue` store used by the projects
  store. Both point at the same OpenRegister objects endpoint.
