# Tasks: Adopt CnAppRoot + Manifest-v2 Shell

> APPLY STATUS (this session): DEFERRED as a whole. This change is a full
> replacement of planix's working hand-rolled shell (App.vue + router +
> MainMenu + settings bootstrap) with the shared `CnAppRoot` / manifest-v2
> render pipeline. The CnAppRoot / registry / page-types / menu-layout /
> integration-registry wiring is intricate (see the pipelinq/decidesk
> reference App.vue + main.js), and a manifest that *builds* can still render
> a blank shell at runtime — the exact failure mode the house rules warn
> against. Verifying the manifest-driven render requires a live Nextcloud +
> OpenRegister instance, which is not available in this isolated worktree (and
> the house rule forbids deploying to the shared dev instance). Rather than
> ship an unverifiable shell swap or add inert/dead manifest config, this
> change is left for an environment where the render can be driven and
> compared against the baseline. Confirmed available prerequisites:
> `CnAppRoot`, `CnAdminSettingsShell`, `CnDependencyMissing`, `buildManifest`
> are all exported by `@conduction/nextcloud-vue@1.0.0-beta.144`.

## 0. Baseline

- [ ] 0.1 Capture baseline screenshots + route list — NEEDS LIVE INSTANCE.

## 1. Manifest-v2 UI surface

- [ ] 1.1 Extend `src/manifest.json` with `pages[]` — DEFERRED: an unconsumed
      `pages[]`/`widgets[]` block is inert until `CnAppRoot` is wired (task 2),
      and a malformed block fails gate-22, so this is done together with the
      shell swap under live verification rather than shipped blind.
- [ ] 1.2 Add a top-level `menu[]` reproducing the current 3 items — DEFERRED (with 1.1).
- [ ] 1.3 Register the five existing views in a `registry` map (`kind: "page"`) — DEFERRED (with 1.1).
- [ ] 1.4 Validate the manifest via gate-22 — NEEDS the manifest surface (1.1) + the gate runner.

## 2. Shell adoption

- [ ] 2.1 Replace `src/App.vue` with `<CnAppRoot>` — DEFERRED: needs live render verification.
- [ ] 2.2 Delete `src/navigation/MainMenu.vue` — DEFERRED (blocked on 2.1 rendering equivalently).
- [ ] 2.3 Delete `src/router/index.js` — DEFERRED (blocked on 2.1).
- [ ] 2.4 Update `src/main.js` to bootstrap through `CnAppRoot` — DEFERRED (blocked on 2.1).

## 3. Admin settings shell

- [ ] 3.1 Replace `src/settings.js` + `AdminRoot.vue` with `CnAdminSettingsShell` — DEFERRED: needs live render verification.
- [ ] 3.2 Confirm label-management admin action still works in the new shell — NEEDS LIVE INSTANCE.

## 4. Verification

- [ ] 4.1 Diff live rendering of all 5 routes + admin settings vs baseline — NEEDS LIVE INSTANCE.
- [ ] 4.2 e2e smoke — NEEDS LIVE INSTANCE.
- [ ] 4.3 vitest green + no orphaned imports — pending the swap.

## 5. Quality gates

- [ ] 5.1 `composer check:strict` + `npm run lint` — pending the swap.
- [ ] 5.2 18 hydra gates + gate-22 manifest validation — pending the manifest surface + gate runner.
