---
kind: code
---

# Proposal: Planix Adopts the Shared `CnAppRoot` Shell + Manifest-v2 UI

## Why

Planix already adopted the OpenRegister AppHost for its backend (observability,
settings, repair — `openspec/changes/adopt-apphost/`), but its **frontend shell
is 100% bespoke** and does not consume any part of the `@conduction/nextcloud-vue`
manifest-driven UI that the rest of the fleet has standardised on (ADR-024,
superseded by ADR-036 "Universal Widget Manifest v2"; ADR-044 "Menu
architecture").

Verified against HEAD:

- `src/App.vue:1-45` hand-rolls the top-level shell: `<NcContent>` +
  `<MainMenu>` + `<router-view>` + a manually-managed `activeSidebar` outlet.
  There is no `<CnAppRoot>` anywhere in the app (`grep -rl "CnAppRoot" .`
  under the app root returns zero matches).
- `src/navigation/MainMenu.vue:1-73` hand-writes `<NcAppNavigation>` /
  `<NcAppNavigationItem>` markup with a hardcoded 3-item list (Dashboard,
  Projects, Documentation link) instead of rendering from a manifest `pages[]`
  + `menu-layout.json`.
- `src/router/index.js:1-36` hand-rolls `vue-router` routes instead of the
  shared manifest-driven router that `CnAppRoot` derives from `pages[]`.
- `src/settings.js` + `src/views/settings/AdminRoot.vue` hand-roll the admin
  settings page instead of consuming `CnAdminSettingsShell`.
- `src/manifest.json` exists but is **exclusively the AppHost observability/
  deep-link manifest** (ADR-040) — it carries `observability` and `deepLinks`
  blocks only. It has no `pages[]`, `widgets[]`, or `menu` block, so
  `gate-manifest-validates` (ADR-036) and gate-22 (manifest validation,
  ADR-024) have nothing to check; planix is invisible to both gates today.
- `find src/manifest.d` — no such directory. Planix has not adopted the
  ADR-037 fragment pipeline, which ADR-044 names as the **hard prerequisite**
  for `buildManifest()` / the settings-foldout / cards-collapse pattern.

ADR-036 states the shared shell "is itself an absorbed abstraction — every
app consumes the shared shell rather than hand-rolling router / sidebar /
dependency-check" (ADR-022 §"Related"). ADR-036 records five apps already
running manifest-v2 in production (decidesk, pipelinq, procest,
zaakafhandelapp, softwarecatalog) and ADR-044 records 9+ apps on the shared
`buildManifest` pipeline. Planix is the outlier: the smallest, youngest app in
the fleet is also the only one carrying a full parallel implementation of the
shell OR already provides generically — the exact anti-pattern ADR-022
identifies ("Duplicate sidebar tab systems... An app registering its own
object-sidebar tabs outside the integration registry").

This is not a hypothetical: it means every future shell improvement shipped to
`@conduction/nextcloud-vue` (dependency-check phase, first-time-setup phase
per ADR-042, dynamic per-tenant menus, named-view sidebar) has to be
hand-ported into planix instead of being inherited for free.

## What Changes

- Add a manifest-v2 UI surface to `src/manifest.json` (or split it into
  `src/manifest.d/*.json` fragments per ADR-037): `pages[]` for Dashboard
  (`type: "dashboard"`) and Projects (`type: "index"`), `menu[]`, and a
  `widgets[]` array per page — replacing the hand-written route list and
  nav items 1:1 (no functionality loss, ADR-044 Decision 5 / hard rule).
- Replace `src/App.vue`'s hand-rolled shell with `<CnAppRoot :manifest="..."
  :registry="...">` from `@conduction/nextcloud-vue`, keeping the existing
  `OpenRegister is required` empty-state as a registry-driven dependency-check
  slot (`CnDependencyMissing`) rather than bespoke markup.
- Delete `src/navigation/MainMenu.vue` and `src/router/index.js` — both
  superseded by `CnAppRoot`'s manifest-derived navigation + router.
- Replace `src/settings.js` + `src/views/settings/AdminRoot.vue` with
  `CnAdminSettingsShell`, keeping the existing register/schema settings
  fields as a `config-fields` section driven by the schema already returned
  by `SettingsController::index()`.
- Register planix's five existing views (`Dashboard`, `ProjectList`,
  `ProjectBoard`, `ProjectBacklog`, `TaskDetail`) in the `registry` prop with
  `kind: "page"` (escape hatch, per ADR-036 Decision 3) as an interim step;
  follow-up specs may decompose `ProjectBoard`/`ProjectBacklog` into typed
  `detail` pages with `widgets[]` once the kanban-board and task-collaboration
  specs are revisited.
- **BREAKING (internal only)**: `src/main.js` bootstrap changes from a bare
  `new Vue({ pinia, router, render: h => h(App) })` to the `CnAppRoot`
  bootstrap helper; no route URLs, deep links, or backend contracts change.

## Impact

- Deleted: `src/navigation/MainMenu.vue` (~73 lines), `src/router/index.js`
  (~36 lines), bespoke settings bootstrap.
- Added: `pages[]`/`menu[]`/`widgets[]` block in the manifest; `registry` prop
  wiring in `src/main.js`.
- Unchanged: all URLs (`/`, `/projects`, `/projects/:id`, etc.), all backend
  routes/controllers, `ProjectController`/`LabelController`/
  `DependencyController`, `lib/Settings/planix_register.json`.
- Enables (not required by this change): ADR-044's five-menu navigation
  layout (see the companion change `adopt-five-menu-navigation-ia`, which
  depends on this one) and, later, ADR-042's first-time-setup wizard.

## Dependencies

None — this change is self-contained and can land ahead of
`adopt-five-menu-navigation-ia`, which depends on it (see that change's
`hydra.json`).
