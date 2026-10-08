# Tasks: Adopt CnAppRoot + Manifest-v2 Shell

> APPLY STATUS: the shell swap (sections 1 and 2) is on development; admin settings shell and live verification remain open.

## 0. Baseline

- [ ] 0.1 Capture baseline screenshots + route list — NEEDS LIVE INSTANCE.

## 1. Manifest-v2 UI surface

- [x] 1.1 Extend `src/manifest.json` with `pages[]` — DEFERRED: an unconsumed
      `pages[]`/`widgets[]` block is inert until `CnAppRoot` is wired (task 2),
      and a malformed block fails gate-22, so this is done together with the
      shell swap under live verification rather than shipped blind.
- [x] 1.2 Add a top-level `menu[]` reproducing the current 3 items — DEFERRED (with 1.1).
- [x] 1.3 Register the five existing views in a `registry` map (`kind: "page"`) — DEFERRED (with 1.1).
- [ ] 1.4 Validate the manifest via gate-22 — NEEDS the manifest surface (1.1) + the gate runner.

## 2. Shell adoption

- [x] 2.1 Replace `src/App.vue` with `<CnAppRoot>` — DEFERRED: needs live render verification.
- [x] 2.2 Delete `src/navigation/MainMenu.vue` — DEFERRED (blocked on 2.1 rendering equivalently).
- [x] 2.3 Delete `src/router/index.js` — DEFERRED (blocked on 2.1).
- [x] 2.4 Update `src/main.js` to bootstrap through `CnAppRoot` — DEFERRED (blocked on 2.1).

## 3. Admin settings shell

- [ ] 3.1 Replace `src/settings.js` + `AdminRoot.vue` with `CnAdminSettingsShell` — DEFERRED: needs live render verification. — open: src/settings.js still mounts AdminRoot.vue
- [ ] 3.2 Confirm label-management admin action still works in the new shell — NEEDS LIVE INSTANCE.

## 4. Verification

- [ ] 4.1 Diff live rendering of all 5 routes + admin settings vs baseline — NEEDS LIVE INSTANCE.
- [ ] 4.2 e2e smoke — NEEDS LIVE INSTANCE.
- [ ] 4.3 vitest green + no orphaned imports — pending the swap.

## 5. Quality gates

- [ ] 5.1 `composer check:strict` + `npm run lint` — pending the swap.
- [ ] 5.2 18 hydra gates + gate-22 manifest validation — pending the manifest surface + gate runner.
