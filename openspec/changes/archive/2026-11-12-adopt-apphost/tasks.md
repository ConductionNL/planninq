# Tasks: Planninq Adopts OpenRegister AppHost

## 0. Baseline

- [ ] 0.1 Capture baseline on a seeded dev instance: `curl /apps/planninq/api/health` JSON + `/apps/planninq/api/metrics` Prometheus text + `/api/settings` JSON; store as fixtures for the parity diff

## 1. Manifest observability block (minimal)

- [ ] 1.1 Create `src/manifest.json` (planninq is Tier 0 — no manifest exists yet) with an `observability` block: `health.checks: [{"type": "database"}]`, `metrics: []` (implicit `planninq_info`/`planninq_up` come from the engine)
- [ ] 1.2 (Optional, recipe-documentation value) add the worked-example metric `planninq_projects_total` → `{"kind": "objectCount", "register": "planninq", "schema": "project"}`; and/or the optional `{"type": "orAvailable", "severity": "degraded"}` health check — both intentional non-parity additions, document if added
- [ ] 1.3 Validate via ManifestService diagnostics (no errors)

## 2. Bootstrap/Routes wiring + deletions

- [ ] 2.1 Shrink `lib/AppInfo/Application.php` to `\OCA\OpenRegister\AppHost\Bootstrap::register($context, 'planninq')` (~20 lines); verify the Bootstrap aliases cover the DeepLink listener registration currently done by hand
- [ ] 2.2 Replace `appinfo/routes.php` with `return \OCA\OpenRegister\AppHost\Routes::standard($extra)`, passing the three domain routes (`project#checkCreatePolicy`, `project#create`, `project#leaveProject`) via `$extra`; route names/URLs identical (incl. SPA catch-all ordering)
- [ ] 2.3 Delete `lib/Controller/HealthController.php`, `lib/Controller/MetricsController.php`, `lib/Controller/DashboardController.php`, `lib/Controller/SettingsController.php`, `lib/Service/SettingsService.php`, `lib/Settings/AdminSettings.php`, `lib/Sections/SettingsSection.php`, `lib/Repair/InitializeSettings.php`, `lib/Listener/DeepLinkRegistrationListener.php` — `ProjectController.php`, `planninq_register.json`, `lib/Migration/` stay
- [ ] 2.4 Confirm `info.xml` needs no change (repair-step + admin-settings class names resolve to generics via Bootstrap aliases); sweep remaining references (unit tests, `@spec` tags, docs)

## 3. Verification

- [ ] 3.1 Diff live `/api/health`, `/api/metrics`, `/api/settings` output vs the 0.1 baseline: identical shape, metric names, types, labels (document intentional deltas only if 1.2 options were added)
- [ ] 3.2 OR AppHost Newman contract collection green against planninq's endpoints; existing `tests/integration/planninq.postman_collection.json` green
- [ ] 3.3 e2e smoke green: dashboard SPA loads at `/apps/planninq/`, deep-link/catch-all route renders, admin settings section shows register/schema config; existing unit suite green
- [ ] 3.4 Fresh-install check: `occ app:enable planninq` runs the generic repair step and seeds the register from `lib/Settings/planninq_register.json`

## 4. Docs

- [ ] 4.1 Update planninq docs (`docs/ARCHITECTURE.md` + feature docs): planninq now runs on the AppHost; mark this change as the fleet adoption-recipe reference (simplest adoption — zero observability descriptors required)

## 5. Quality gates

- [ ] 5.1 `composer check:strict` green (PHPCS, PHPMD, Psalm, PHPStan) — fix any pre-existing issues encountered
- [ ] 5.2 18 hydra gates green; gate-22 manifest validation green against the new `src/manifest.json`
