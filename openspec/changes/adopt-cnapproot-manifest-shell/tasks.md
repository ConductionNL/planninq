# Tasks: Adopt CnAppRoot + Manifest-v2 Shell

> APPLY STATUS (2026-11-14): the shell swap landed in #530 (`feat(shell): make
> planninq manifest-driven`, 2026-08-31): `src/App.vue` mounts `CnAppRoot`,
> `src/main.js` builds the routes from `manifest.pages`, `src/manifest.json`
> carries `pages[]` and `menu[]`, and `src/navigation/MainMenu.vue` and
> `src/router/index.js` are gone. This pass moves the admin settings onto
> `CnAdminSettingsShell` (3.1). What is left needs a live instance: the
> baseline and render comparison, the admin action check and the e2e smoke.

## 0. Baseline

- [ ] 0.1 Capture baseline screenshots + route list — NEEDS LIVE INSTANCE.

## 1. Manifest-v2 UI surface

- [x] 1.1 Extend `src/manifest.json` with `pages[]` — done in #530.
- [x] 1.2 Add a top-level `menu[]` reproducing the current 3 items — done in #530.
- [x] 1.3 Register the five existing views in a `registry` map (`kind: "page"`) — done in #530 (`src/registry.js`).
- [x] 1.4 Validate the manifest via gate-22 — `npm run check:manifest` (Ajv against app-manifest-v2 2.139.0): 0 errors.

## 2. Shell adoption

- [x] 2.1 Replace `src/App.vue` with `<CnAppRoot>` — done in #530.
- [x] 2.2 Delete `src/navigation/MainMenu.vue` — done in #530.
- [x] 2.3 Delete `src/router/index.js` — done in #530.
- [x] 2.4 Update `src/main.js` to bootstrap through `CnAppRoot` — done in #530 (routes from `manifest.pages`, `buildManifest`).

## 3. Admin settings shell

- [x] 3.1 Replace `src/settings.js` + `AdminRoot.vue` with `CnAdminSettingsShell` — `AdminRoot.vue` renders `Settings` inside `CnAdminSettingsShell` (title, version card, re-import through `/api/settings/load`, support footer); `settings.js` stays the mount. Test: `tests/vitest/adminRootShell.spec.js`.
- [ ] 3.2 Confirm label-management admin action still works in the new shell — NEEDS LIVE INSTANCE.

## 4. Verification

- [ ] 4.1 Diff live rendering of all 5 routes + admin settings vs baseline — NEEDS LIVE INSTANCE.
- [ ] 4.2 e2e smoke — NEEDS LIVE INSTANCE.
- [x] 4.3 vitest green + no orphaned imports — 112 files, 3204 tests green.

## 5. Quality gates

- [ ] 5.1 `composer check:strict` + `npm run lint` — pending the swap.
- [ ] 5.2 18 hydra gates + gate-22 manifest validation — pending the manifest surface + gate runner.
