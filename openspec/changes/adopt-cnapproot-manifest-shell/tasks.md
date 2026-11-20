# Tasks: Adopt CnAppRoot + Manifest-v2 Shell

> APPLY STATUS (2026-11-14): the shell swap landed in #530 (`feat(shell): make
> planninq manifest-driven`, 2026-08-31): `src/App.vue` mounts `CnAppRoot`,
> `src/main.js` builds the routes from `manifest.pages`, `src/manifest.json`
> carries `pages[]` and `menu[]`, and `src/navigation/MainMenu.vue` and
> `src/router/index.js` are gone. This pass moves the admin settings onto
> `CnAdminSettingsShell` (3.1). What is left needs a live instance: the
> baseline and render comparison, the admin action check and the e2e smoke.
>
> 17 Nov (round 2): the e2e files the spec names are written:
> `tests/e2e/navigation.spec.ts` (the menu from `menu[]` in order, admin-only
> entries absent for a regular user, no navigation outside `CnAppRoot`, the five
> routes, the unknown-route fallback) and `tests/e2e/admin-settings.spec.ts`
> (`allow_project_creation` saved through `POST /api/settings` from the shared
> shell). They have not run yet; 4.2 stays open until they do.

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
- [x] 3.2 Confirm label-management admin action still works in the new shell — NEEDS LIVE INSTANCE. Confirmed live 2026-11-17 (Nextcloud 34.0.6, OpenRegister main 28ff9cf60): `tests/e2e/label-management.spec.ts` passes 5 of 5 on the admin page (list with usage counts, create, invalid colour refused, rename and recolour reach the task chips, delete through the usage warning).

## 4. Verification

- [ ] 4.1 Diff live rendering of all 5 routes + admin settings vs baseline — NEEDS LIVE INSTANCE.
- [ ] 4.2 e2e smoke — NEEDS LIVE INSTANCE. Run 2026-11-17: `tests/e2e/app-chrome.spec.ts` 4 of 7 pass; open: the footer test expects "Store" where the shell now says "Marketplace", the time report test expects "Minutes logged", which is absent without humaniq (plannedtimeentry-reads-humaniqs-hours), and the Store surface test times out on that same footer entry. 17 Nov: the spec now expects the shared label "Marketplace" and checks Minutes logged only where humaniq is installed; still to rerun on a live instance.
- [x] 4.3 vitest green + no orphaned imports — 112 files, 3204 tests green.

## 5. Quality gates

- [ ] 5.1 `composer check:strict` + `npm run lint` — pending the swap.
  - Ran 20 Nov on development 2c77f0c8 (the swap is in since #530): `npm run lint` 0 errors; `composer check:strict` lint, phpcs (warnings only) and PHPUnit (1307 tests OK) pass, but it stays red: phpmd reports 23 findings in code this change does not touch, phpstan reports 9 errors from stale OpenRegister stubs (`Bootstrap::aliasStoreController` in hydra-gates' `openregister-apphost-bootstrap.stub.php` and the old `LeafDescriptor` in `tests/stubs/openregister-apphost.stub.php`, where the real OpenRegister signatures take `LeafAdoption` and `LeafIdentity`), and psalm needs PHP 8.3.16 or newer. Left open until it is green.
- [ ] 5.2 18 hydra gates + gate-22 manifest validation — pending the manifest surface + gate runner.
  - Ran 20 Nov on development 2c77f0c8 with the hydra-gates runner: gate-22 manifest-validation PASS; 83 of 83 applicable gates ran, 74 PASS and 9 FAIL (no-admin-idor, modal-isolation, visual-coverage, semantic-controls, spec-anchor-existence, schema-property-titles, icon-vocabulary, openregister-dependency-shape, manifest-l10n-coverage). Left open until those are cleared or found to be outside this change.
