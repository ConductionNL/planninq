# Tasks: Adopt CnAppRoot + Manifest-v2 Shell

## 0. Baseline

- [ ] 0.1 Capture baseline screenshots + route list (`/`, `/projects`,
      `/projects/:id`, `/projects/:id/backlog`, `/projects/:id/tasks/:taskId`)
      and the admin settings page render on a seeded dev instance, for parity
      comparison.

## 1. Manifest-v2 UI surface

- [ ] 1.1 Extend `src/manifest.json` (or introduce `src/manifest.d/*.json` +
      `require.context` collection per ADR-037) with `pages[]`: `Dashboard`
      (`type: "dashboard"`), `Projects` (`type: "index"`, `object-table`
      widget), `ProjectDetail` (`type: "custom"` initially, `_note:
      "kanban board — pending typed-page decomposition"`), `ProjectBacklog`
      and `TaskDetail` similarly.
- [ ] 1.2 Add a top-level `menu[]` reproducing the current 3 items exactly
      (Dashboard, Projects, Documentation external link) — no functionality
      loss (ADR-044 hard rule).
- [ ] 1.3 Register the five existing view components under a `registry` map
      with `kind: "page"` for each.
- [ ] 1.4 Validate the manifest via `ManifestService` diagnostics / gate-22 —
      zero errors.

## 2. Shell adoption

- [ ] 2.1 Replace `src/App.vue`'s markup with `<CnAppRoot>`, passing the
      manifest + registry; keep the `OpenRegister is required` message as the
      `CnDependencyMissing` slot content.
- [ ] 2.2 Delete `src/navigation/MainMenu.vue`; confirm `CnAppRoot`'s
      manifest-derived nav renders the same 3 items with the same icons.
- [ ] 2.3 Delete `src/router/index.js`; confirm `CnAppRoot`'s manifest-derived
      router serves the same 5 URLs, including the `*` catch-all → `/`
      redirect behaviour.
- [ ] 2.4 Update `src/main.js` to bootstrap through `CnAppRoot` instead of a
      bare `new Vue(...)`.

## 3. Admin settings shell

- [ ] 3.1 Replace `src/settings.js` + `src/views/settings/AdminRoot.vue` with
      `CnAdminSettingsShell`, driven by the schema already returned from
      `SettingsController::index()` (`default_columns`,
      `allow_project_creation`, `due_reminder_lead_hours`).
- [ ] 3.2 Confirm the label-management admin action (`LabelController`) still
      renders and functions inside the new settings shell.

## 4. Verification

- [ ] 4.1 Diff live rendering of all 5 routes + admin settings vs the 0.1
      baseline: identical URLs, identical nav items, identical admin fields.
- [ ] 4.2 e2e smoke green: dashboard loads, project list loads, project board
      loads, task detail loads, admin settings section renders and saves.
- [ ] 4.3 Existing unit/vitest suite green; no orphaned imports of the
      deleted `MainMenu.vue` / `router/index.js`.

## 5. Quality gates

- [ ] 5.1 `composer check:strict` + `npm run lint` green.
- [ ] 5.2 18 hydra gates green; gate-22 manifest validation green against the
      extended `src/manifest.json`.
