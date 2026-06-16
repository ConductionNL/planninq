# Design — Retrofit App Shell & Data Store

**Retrofit change.** Tasks describe retroactive annotation, not new implementation work.

## Context

Phase 3 of the planix retrofit pipeline (coverage-to-zero) annotated 49 of 55
frontend + 2 backend methods via Bucket 1 `@spec` tags (mapping to the archived
`retrofit-2026-05-24-annotate-planix` change) and `@spec exclude` markers for
framework glue. Six methods described real behavior with no owning REQ:

- `lib/Controller/DashboardController.php::page` / `::catchAll` — serve the SPA
  shell. No spec mentioned the SPA-serving controller; `dashboard-my-work.md`
  covers the KPI dashboard feature, not the app-shell controller.
- `src/store/modules/object.js::configure` / `::registerObjectType` /
  `::fetchObjects` — the generic OpenRegister object store module. No REQ owned it.
- `src/store/store.js::initializeStores` — boot-time wiring of the object stores.

## Why --cluster, not --extend

There is no existing capability for the application shell or the generic data
store. `projects.md`, `dashboard-my-work.md`, and `admin-user-settings.md` are
all feature specs; none own the foundational shell/store layer. Extending any of
them would mislocate infrastructure behavior inside a feature spec. A new
`app-shell-and-data-store` capability is the honest home.

## Why three REQs

The six methods split cleanly into three observable behaviors:

1. **SPA Page Serving** — backend controller renders the SPA for the root page
   and history-mode deep links (`page` + `catchAll`).
2. **Generic Object Store** — configurable, type-registered OpenRegister fetch
   plumbing (`configure` + `registerObjectType` + `fetchObjects`).
3. **Store Bootstrap** — the boot routine that wires both stores and primes
   settings before render (`initializeStores`).

Collapsing these would lose distinct guarantees (e.g. the deep-link delegation,
the unregistered-type guard); splitting further would inflate the spec.

## REQ ID convention

Slug-style (`REQ-SPA-Page-Serving`) to match planix's existing flat-file spec
convention used across the prior Bucket 1 annotations and the projects-backlog
retrofit.

## Frontmatter

Uses `retrofit: true` (new capability) to flag the cohort for Specter sync.

## Annotation scope

`@spec` tags land on: the two `DashboardController` methods (task-1), the three
`object.js` actions (task-2), and `initializeStores` (task-3). The PHP class
docblock keeps its existing license header; per-method `@spec` lines are added
inside each method's own docblock.

## What this change does NOT do

- No code-behavior changes — annotations only.
- Does not silently fix observed behavior. Note flagged: `fetchObjects` swallows
  network/parse errors and returns `[]` rather than surfacing them — this is the
  observed behavior and is specified as-is.

## Risk

Minimal. Spec-only new capability + six annotation docblocks.
