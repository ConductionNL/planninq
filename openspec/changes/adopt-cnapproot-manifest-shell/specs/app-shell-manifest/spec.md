---
status: proposed
---

# Planninq App Shell Manifest Adoption

## Purpose

Planninq's frontend shell (navigation, router, admin settings chrome) runs on
the shared `@conduction/nextcloud-vue` `CnAppRoot` + manifest-v2 contract
(ADR-024/036/044) instead of a bespoke `App.vue` + `MainMenu.vue` +
`router/index.js` stack, so shell improvements shipped to the shared library
are inherited automatically instead of requiring a per-app hand-port.

**Cross-references**: ADR-022 (apps consume OR/shared abstractions),
ADR-024/036 (app manifest), ADR-037 (manifest fragments), ADR-044 (menu
architecture).

---

## ADDED Requirements

### Requirement: Manifest-Derived Navigation

Planninq SHALL derive its top-level navigation from `src/manifest.json`
`pages[]`/`menu[]` rendered by `CnAppRoot`, not from a hand-written
`NcAppNavigation` component.

#### Scenario: Navigation renders from the manifest

- **GIVEN** a logged-in user with planninq enabled
- **WHEN** the app shell mounts
- **THEN** the sidebar MUST show exactly the items declared in
  `manifest.json` `menu[]` (Dashboard, Projects, Documentation), in the
  declared order
- **AND** no `NcAppNavigationItem` markup MUST exist outside the shared
  `CnAppRoot` navigation renderer

### Requirement: Manifest-Derived Routing

Planninq SHALL derive its Vue Router routes from the manifest `pages[]` array
via `CnAppRoot`, not from a hand-written `router/index.js`.

#### Scenario: All five routes remain reachable

- **GIVEN** the manifest declares pages for Dashboard, Projects, ProjectDetail,
  ProjectBacklog, and TaskDetail
- **WHEN** a user navigates to `/`, `/projects`, `/projects/:id`,
  `/projects/:id/backlog`, or `/projects/:id/tasks/:taskId`
- **THEN** each URL MUST resolve to the same view component as before the
  adoption (no functionality loss, ADR-044 hard rule)
- @e2e planninq/tests/e2e/navigation.spec.ts

#### Scenario: Unknown route falls back to Dashboard

- **GIVEN** a user navigates to an undeclared path
- **WHEN** the manifest-derived router resolves the URL
- **THEN** the app MUST redirect to `/` exactly as the pre-adoption catch-all
  route did
- @e2e planninq/tests/e2e/navigation.spec.ts

### Requirement: Shared Admin Settings Shell

Planninq SHALL render its admin settings page through `CnAdminSettingsShell`
instead of a bespoke `AdminRoot.vue`, with the register/schema configuration
fields sourced from the same `SettingsController::index()` schema as before.

#### Scenario: Admin settings render and save through the shared shell

- **GIVEN** an admin user on the Nextcloud admin settings page
- **WHEN** the admin opens the Planninq settings section and changes
  `allow_project_creation`
- **THEN** the change MUST persist via the existing
  `POST /apps/planninq/api/settings` endpoint, unchanged in shape
- @e2e planninq/tests/e2e/admin-settings.spec.ts
