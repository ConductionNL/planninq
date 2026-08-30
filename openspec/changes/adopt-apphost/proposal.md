---
kind: code
---

# Proposal: Planninq Adopts OpenRegister AppHost (Simplest Adoption — The Recipe)

## Problem

Planninq carries the full petstore boilerplate stack even though it is the smallest, youngest app in the fleet. The 2026-06-12 fleet observability catalogue shows planninq's entire hand-written observability surface is already engine-default behaviour:

- **Health today**: a single database `SELECT 1` check (`HealthController`, 93 lines) — exactly the AppHost engine's default when no descriptors are declared.
- **Metrics today**: only `planninq_info` and `planninq_up` (`MetricsController`, 96 lines) — both **implicit** in the engine, never declared.
- **Net effect**: planninq's entire observability PHP disappears with **zero descriptors required**. The engine's design doc names planninq explicitly as a zero-descriptor default app.

Beyond observability, planninq ships ~1,160 lines of drifted petstore plumbing (Dashboard/Settings controllers, SettingsService, AdminSettings, SettingsSection, InitializeSettings repair step, DeepLinkRegistrationListener) that the AppHost generics replace one-for-one.

Because planninq has **no domain residue in any boilerplate file** — its only domain PHP is `ProjectController` — this is the **simplest possible AppHost adoption in the fleet**. This change doubles as the **adoption-recipe reference** for the other 17 `adopt-apphost` changes: if a step isn't needed here, it's the app-specific part elsewhere.

## Proposed Change

Adopt both halves of the AppHost (`apphost-observability-engine` + `apphost-boilerplate-controllers`):

1. **Manifest**: create a minimal `src/manifest.json` (planninq is Tier 0 today — no manifest exists) with an `observability` block containing only `health.checks: [{"type": "database"}]` for explicit parity, and an **empty metrics array** (implicit `planninq_info`/`planninq_up` come free). Optionally include one worked-example metric — `planninq_projects_total` via `{"kind": "objectCount", "register": "planninq", "schema": "project"}` — purely as recipe documentation; it is not required for parity.
2. **Bootstrap**: shrink `Application.php` to `AppHost\Bootstrap::register($context, 'planninq')`. The Bootstrap registers container aliases under `OCA\Planninq\...` for every generic class, so `info.xml` (repair steps, admin settings classes) stays **unchanged**.
3. **Routes**: replace `appinfo/routes.php` body with `return \OCA\OpenRegister\AppHost\Routes::standard($extra)` where `$extra` carries the three domain routes (`project#checkCreatePolicy`, `project#create`, `project#leaveProject`). Probe/scrape/SPA URLs do not change.
4. **Delete the boilerplate** (all replaced by AppHost generics via alias):
   - `lib/Controller/HealthController.php` (93 lines) → engine default
   - `lib/Controller/MetricsController.php` (96 lines) → engine implicit info/up
   - `lib/Controller/DashboardController.php` (75 lines) → `GenericDashboardController`
   - `lib/Controller/SettingsController.php` (137 lines) → `GenericSettingsController`
   - `lib/Service/SettingsService.php` (383 lines) → `AppHostSettingsService`
   - `lib/Settings/AdminSettings.php` (88 lines) → `GenericAdminSettings`
   - `lib/Sections/SettingsSection.php` (86 lines) → `GenericSettingsSection`
   - `lib/Repair/InitializeSettings.php` (106 lines) → `GenericInitializeSettings` (reads `lib/Settings/planninq_register.json` by appId)
   - `lib/Listener/DeepLinkRegistrationListener.php` (102 lines) → `GenericDeepLinkRegistrationListener`

   **Untouched (domain)**: `lib/Controller/ProjectController.php` (398 lines), `lib/Settings/planninq_register.json`, `lib/Migration/`, all frontend code.

### OR-dependency note (verified)

`appinfo/info.xml` declares **no** OpenRegister dependency (only nextcloud + php), so per the fleet catalogue rule the `{"type": "orAvailable", "severity": "degraded"}` health check is **not** required for parity and is omitted from the SHALL surface. Planninq is nonetheless functionally a thin OR client (`SettingsService::isOpenRegisterAvailable()`, DeepLink event); adding `orAvailable` as a degraded check is a recommended optional improvement and is listed as such in tasks — it never flips the endpoint to 503 under the default `adr006` policy.

## Impact

- **Deleted**: ~1,166 lines of boilerplate PHP (~70% of `lib/` + `routes.php`); `Application.php` shrinks to ~20 lines, `routes.php` to one statement plus three domain routes.
- **Added**: minimal `src/manifest.json` (planninq's first — gate-22 manifest validation starts applying).
- **Unchanged**: all route names/URLs, `info.xml`, ProjectController, register JSON, frontend.
- **Risk**: minimal — no imperative residue exists, so no provider escape hatch is needed; parity is asserted by baseline diff + the OR AppHost Newman contract collection.

## Dependencies

Chained on the OpenRegister changes `apphost-observability-engine` and `apphost-boilerplate-controllers` (see `hydra.json`). Reference sibling: `openregister-adopt-apphost` (dogfood adoption).
