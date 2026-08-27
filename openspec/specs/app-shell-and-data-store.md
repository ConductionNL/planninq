---
retrofit: true
---

# App Shell & Data Store Specification

**Status**: done

**Standards**: Nextcloud OCP\AppFramework (TemplateResponse), OpenRegister objects API, Pinia
**Feature tier**: MVP

**OpenSpec changes:**
- [retrofit-2026-05-25-app-shell-and-data-store](../changes/archive/retrofit-2026-05-25-app-shell-and-data-store/) — reverse-spec of the SPA-serving controller and the generic OpenRegister object store

## Purpose

This capability describes the Planninq application shell — the backend controller
that serves the single-page application (including Vue history-mode deep links)
and the generic frontend OpenRegister object store that the shell boots. It is
the foundational layer that every feature spec (`projects`, `dashboard-my-work`,
`admin-user-settings`) sits on top of: the SPA must be served, and a configured
data store must exist, before any feature renders.

## Data Model

No OpenRegister entities of its own. The object store is generic plumbing over
the OpenRegister objects API, parameterised by `register` + `schema` per
registered type.

## Implementation Requirements

### Requirement: SPA Page Serving [MVP]

The system MUST serve the Planninq single-page application from a backend
controller, both for the root page and for Vue history-mode deep links, so a
bookmarked or refreshed in-app URL resolves to the same SPA shell.

#### Scenario: Render the dashboard page

- GIVEN an authenticated Nextcloud user
- WHEN they request the Planninq root page route
- THEN `DashboardController::page()` MUST return a `TemplateResponse` for the
  `index` template of the `planninq` app
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

- GIVEN the Planninq app (or admin settings root) is mounting
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
- `fetchObjects` swallows network/parse errors and returns an empty array
  rather than surfacing them — specified as observed behavior, flagged for
  future tightening.

## Acceptance Criteria

- [ ] `DashboardController::page()` returns a `TemplateResponse` for the `index` template
- [ ] `DashboardController::catchAll()` delegates to `page()` for deep links
- [ ] `object.js` store exposes `configure`, `registerObjectType`, `fetchObjects`
- [ ] `fetchObjects` returns `[]` for unregistered types and on error
- [ ] `initializeStores()` configures both object stores and awaits settings fetch
