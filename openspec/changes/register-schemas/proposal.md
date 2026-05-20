# Proposal: Register Schemas

## Summary

Define the five core OpenRegister schemas (`task`, `project`, `column`, `timeEntry`, `label`) in `lib/Settings/planix_register.json`, remove the placeholder `example` schema, add realistic seed data, and ensure the `SettingsService` import is idempotent and skipped when the stored version matches the file version.

## Motivation

Planix is a thin-client app built entirely on OpenRegister. Without correct schema definitions no other feature can store or validate data. The current register file may contain a placeholder `example` schema that has no use in production and must be removed. More importantly, the five real schemas — task, project, column, timeEntry, and label — must be fully declared with required fields, enum constraints, and defaults so that OpenRegister enforces validation automatically on every create/update request.

Seed data is equally critical: a fresh install with no objects gives users no context for how the app works. Providing realistic Dutch IT-team demo data lets teams evaluate and understand Planix immediately after install, and gives developers a working board to test against from day one.

## Affected Projects

- [x] Project: `planix` — Register file `lib/Settings/planix_register.json` and import service `lib/Service/SettingsService.php`

## Scope

### In Scope

- Define all 5 schemas (`task`, `project`, `column`, `timeEntry`, `label`) with complete property definitions, required fields, enums, and defaults in `planix_register.json`
- Remove the placeholder `example` schema from `components/schemas`
- Add seed data: 5 labels, 3 projects, 12 columns (4 per project), 5 tasks, 3 time entries — all with Dutch IT-team context
- Set register file version to `0.2.0` in `info.version`
- Verify `SettingsService::loadConfiguration()` passes the file version to `ConfigurationService::importFromApp()` for skip-logic
- Verify `ConfigurationService` upserts seed objects by slug (idempotent re-import)
- Remove any `example` schema reference from `DeepLinkRegistrationListener`

### Out of Scope

- Frontend UI changes — no Vue components are touched
- New API endpoints — OpenRegister handles CRUD automatically once schemas are registered
- Schema migrations for data already stored in existing OpenRegister instances
- Nextcloud Calendar / VTODO sync (separate feature)
- WIP limit enforcement logic (separate kanban-board feature)

## Approach

The register file `lib/Settings/planix_register.json` is an OpenAPI 3.0.0 document with OpenRegister extensions. It is read by `SettingsService::loadConfiguration()` and passed to `ConfigurationService::importFromApp()`:

1. Replace the placeholder `example` schema with the five real schemas, each with full property tables matching the ADR-000 data model
2. Add a `components/objects` array with seed data in the OpenRegister import format (`@self.slug` as idempotency key)
3. Set `info.version` to `0.2.0` so that instances running `0.1.0` trigger a re-import
4. Confirm `ConfigurationService::importFromApp` uses slug as idempotency key — no code changes needed if confirmed by reading the OpenRegister source
5. Update `DeepLinkRegistrationListener` to remove any `example` slug reference

No new PHP classes are required. The change is almost entirely declarative (JSON register file).

## New Dependencies

None

## Impact

- `lib/Settings/planix_register.json` — complete update of `components/schemas` and addition of `components/objects`
- `lib/Service/SettingsService.php` — verify version skip logic; no functional changes expected
- `lib/Listener/DeepLinkRegistrationListener.php` — remove `example` schema reference if present

## Cross-Project Dependencies

None — this change is self-contained within Planix. The Procest bridge (`zaakUuid` on task, `caseReference` on project) is already declared as a nullable field in the task and project schemas.

## Risks

### Risk 1: Existing objects in development OpenRegister instances
**Severity:** Low — **Mitigation:** Schema upsert via `ConfigurationService` does not drop existing objects. Seed objects are upserted by `@self.slug`; existing objects with the same slug are updated in place, not duplicated.

### Risk 2: OpenRegister version compatibility
**Severity:** Low — **Mitigation:** The register file declares `"openregister": "^v0.2.10"`. CI runs against the OpenRegister ref specified in `app-config.json` `additionalCiApps`.

### Risk 3: ConfigurationService idempotency assumption
**Severity:** Medium — **Mitigation:** Before closing this change, verify by reading `ConfigurationService::importFromApp` in the OpenRegister source that slug-based upsert is the actual behaviour. If not, a thin wrapper in `SettingsService` can implement it.

## Rollback Strategy

Revert the register file to the previous version. No database schema changes are involved — OpenRegister stores all data as JSON objects. Rolling back will not delete existing objects; it will simply stop the schemas from being re-imported on the next version bump.
