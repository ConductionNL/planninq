---
status: proposed
---

# Planix AppHost Adoption

## Purpose

Planix's observability, dashboard, settings, and install plumbing run on the OpenRegister AppHost (engine defaults + generic controllers) with endpoint-level parity, leaving only domain code (`ProjectController`) in the app. As the fleet's simplest adoption, this spec is the reference recipe for the other `adopt-apphost` changes.

**Cross-references**: `openregister/openspec/changes/apphost-observability-engine/`, `openregister/openspec/changes/apphost-boilerplate-controllers/`

---

## Requirements

### Requirement: Declarative Observability with Zero Required Descriptors

Planix SHALL serve `/apps/planix/api/health` and `/apps/planix/api/metrics` through the AppHost observability engine, declared via at most a minimal `observability` block in `src/manifest.json` (health: single `database` check; metrics: empty — implicit `planix_info`/`planix_up` only), with response shape, auth posture, and Prometheus exposition format identical to the pre-adoption hand-written controllers.

#### Scenario: Health endpoint parity

- **GIVEN** a healthy instance with planix enabled
- **WHEN** `GET /apps/planix/api/health` is called
- **THEN** the response MUST be HTTP 200 with `status = "ok"` and `checks.database = "ok"` in the standard shape, matching the pre-adoption baseline
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics endpoint serves implicit info/up only

- **GIVEN** planix is enabled and no metric descriptors are declared in the manifest
- **WHEN** `GET /apps/planix/api/metrics` is called by an admin
- **THEN** the output MUST be Prometheus text exposition format 0.0.4 containing exactly `planix_info{version,php_version,nextcloud_version}` and `planix_up 1`, matching the pre-adoption baseline
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics endpoint rejects non-admin access

- **GIVEN** a non-admin authenticated user
- **WHEN** `GET /apps/planix/api/metrics` is called
- **THEN** the request MUST be rejected by the framework admin requirement (ADR-006 posture unchanged by the adoption)
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

### Requirement: Boilerplate Served by AppHost Generics

Planix SHALL delete its hand-written `DashboardController`, `SettingsController`, `SettingsService`, `AdminSettings`, `SettingsSection`, `InitializeSettings`, and `DeepLinkRegistrationListener`, and serve the equivalent behaviour through AppHost generic classes wired by `Bootstrap::register` container aliases and `Routes::standard`, with all route names, URLs, and `info.xml` class references unchanged.

#### Scenario: Dashboard SPA loads through the generic controller

- **GIVEN** a logged-in user with planix enabled
- **WHEN** the user navigates to `/apps/planix/` and to a deep link such as `/apps/planix/projects/some-project`
- **THEN** the planix SPA MUST render on both URLs (index route and catch-all) exactly as before the adoption

#### Scenario: Admin settings render register and schema configuration

- **GIVEN** an admin user on the Nextcloud admin settings page
- **WHEN** the admin opens the Planix settings section
- **THEN** the section MUST render with the app version and the register/schema configuration resolved by the generic settings service (OpenRegister availability reflected as before)

#### Scenario: Settings API round-trip via the generic controller

- **GIVEN** an admin session
- **WHEN** `GET /apps/planix/api/settings` is called, a value is changed via `POST /apps/planix/api/settings`, and config is re-seeded via `POST /apps/planix/api/settings/load`
- **THEN** each endpoint MUST respond with the same shape and semantics as the pre-adoption baseline
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Fresh install seeds the register through the generic repair step

- **GIVEN** a Nextcloud instance with OpenRegister enabled and planix not yet installed
- **WHEN** `occ app:enable planix` runs
- **THEN** the `OCA\Planix\Repair\InitializeSettings` step (aliased to the AppHost generic) MUST import `lib/Settings/planix_register.json` and configure the planix register and schemas, identical to the pre-adoption install
- @e2e exclude install-time occ plumbing — covered by the OR AppHost Newman contract collection and the fresh-install task check, no UI surface

### Requirement: Domain Surface Untouched

The adoption SHALL NOT modify planix's domain code: `ProjectController` and its three routes (`check-create-policy`, project create proxy, leave-project) MUST keep their existing behaviour, auth posture, and URLs, appended to `Routes::standard` via the `$extra` parameter.

#### Scenario: Project creation policy still enforced after adoption

- **GIVEN** an instance where `allow_project_creation` is disabled for the requesting user
- **WHEN** `POST /apps/planix/api/projects` is called
- **THEN** the request MUST be rejected by the server-side policy check exactly as before the adoption
- @e2e exclude API-only endpoint — covered by the existing planix Newman collection
