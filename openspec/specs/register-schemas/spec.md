# Spec: Register Schemas

**Capability:** register-schemas
**Status:** draft
**Change:** register-schemas
**Feature tier:** MVP

---

## Purpose

<!-- @e2e exclude pure-backend spec: all scenarios concern PHP file content inspection, OpenRegister API validation (HTTP responses), install-time repair steps, and version-skip logic — no browser UI surface exists for any scenario -->

This spec defines the requirements for the Planninq OpenRegister schema definitions. The register file declares the data model that all Planninq features are built upon. Correct schema definitions, seed data, and import behaviour are prerequisites for every other Planninq capability.

---
## Requirements
### Requirement: All 5 schemas defined [MVP]

The register file MUST declare exactly the schemas `task`, `project`, `column`, `timeEntry`, `label`, and `dependency` in `components/schemas` (six schemas after this change). The placeholder `example` schema MUST NOT be present.

(Modification: adds `dependency` to the previously exact set of 5; the register now declares 6 schemas. All existing scenarios of this requirement remain valid with the enlarged key set.)

#### Scenario: Register file contains required schemas

- GIVEN the file `lib/Settings/planninq_register.json` is loaded
- WHEN its `components.schemas` keys are enumerated
- THEN the keys MUST include `task`, `project`, `column`, `timeEntry`, `label`, and `dependency`
- AND the key `example` MUST NOT be present

#### Scenario: Task schema has required fields declared

- GIVEN the `task` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `title` and `status`
- AND the `status` property MUST declare the enum `["open","in_progress","blocked","done","cancelled"]` with default `"open"`

#### Scenario: Project schema has required fields declared

- GIVEN the `project` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `title` and `status`
- AND the `status` property MUST declare the enum `["active","archived","completed"]` with default `"active"`

#### Scenario: Column schema has required fields declared

- GIVEN the `column` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `title`, `project`, and `order`
- AND the `type` property MUST declare the enum `["active","done"]` with default `"active"`

#### Scenario: TimeEntry schema has required fields declared

- GIVEN the `timeEntry` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `task`, `user`, `duration`, and `date`

#### Scenario: Label schema has required fields declared

- GIVEN the `label` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `title` and `color`
- AND the `color` property MUST declare the default `"#4376FC"`

#### Scenario: Dependency schema has required fields declared

- GIVEN the `dependency` schema definition
- WHEN its `required` array is inspected
- THEN it MUST contain `blocker` and `blocked`
- AND both properties MUST be declared as `type: string` with `format: uuid`

### Requirement: Schema validation enforced by OpenRegister [MVP]

OpenRegister MUST enforce `required` constraints declared in each schema when objects are created or updated via the API.

#### Scenario: Task creation without title is rejected

- GIVEN the `task` schema is registered in OpenRegister
- WHEN the API receives a POST request to create a task without a `title` field
- THEN OpenRegister MUST return HTTP 400
- AND the response MUST contain a validation error referencing `title`

#### Scenario: Task creation with invalid status enum is rejected

- GIVEN the `task` schema is registered in OpenRegister
- WHEN the API receives a POST request with `status: "unknown"`
- THEN OpenRegister MUST return HTTP 400
- AND the response MUST contain a validation error referencing `status`

#### Scenario: Label creation without color defaults to #4376FC

- GIVEN the `label` schema is registered in OpenRegister
- WHEN the API receives a POST request with only a `title` field (no `color`)
- THEN OpenRegister MUST store the label with `color: "#4376FC"`
- AND the response MUST include the default value

---

### Requirement: Seed data loaded on install [MVP]

On a fresh Planninq install, the seed objects defined in the register file MUST be created in OpenRegister automatically.

#### Scenario: Seed objects present after fresh install

- GIVEN Planninq is installed for the first time on a Nextcloud instance
- WHEN the app is first activated (triggering `SettingsService` import)
- THEN at least 3 Label objects MUST exist in the `planninq` register
- AND at least 3 Project objects MUST exist
- AND at least 4 Column objects MUST exist
- AND at least 5 Task objects MUST exist
- AND at least 3 TimeEntry objects MUST exist

#### Scenario: Seed labels have correct colors

- GIVEN the seed data has been loaded
- WHEN the `Bug` label is retrieved via the OpenRegister API
- THEN its `color` field MUST be `"#E74C3C"`

#### Scenario: Seed tasks reference seed projects

- GIVEN the seed data has been loaded
- WHEN the task with slug `fix-login-redirect` is retrieved
- THEN its `project` field MUST reference the `client-portal-v2` project object
- AND its `column` field MUST reference the `portal-in-progress` column object

---

### Requirement: Idempotent import [MVP]

Re-importing the register file MUST NOT create duplicate schema definitions or duplicate seed objects.

#### Scenario: Re-import does not duplicate schemas

- GIVEN Planninq has been installed and the register has been imported once
- WHEN the app is reloaded and `SettingsService` runs the import again (same version)
- THEN the number of schemas in the `planninq` register MUST remain exactly 5
- AND no additional schema versions MUST be created

#### Scenario: Re-import does not duplicate seed objects

- GIVEN the seed data has been loaded on first install
- WHEN a re-import is triggered (e.g. by a version bump in a subsequent release)
- THEN the number of Label objects MUST NOT increase beyond the original seed count
- AND OpenRegister MUST use the slug as the idempotency key when upserting seed objects

---

### Requirement: Version-based skip logic [MVP]

The import MUST be skipped when the stored register version matches the file version, to avoid unnecessary processing on every app request.

#### Scenario: Import skipped when version unchanged

- GIVEN the `planninq` register is already stored in OpenRegister with version `0.2.0`
- AND the file `lib/Settings/planninq_register.json` declares version `0.2.0`
- WHEN `SettingsService` checks whether to import
- THEN the import MUST be skipped
- AND no API calls to OpenRegister MUST be made

#### Scenario: Import triggered when version bumped

- GIVEN the `planninq` register is stored with version `0.1.0`
- AND the file `lib/Settings/planninq_register.json` declares version `0.2.0`
- WHEN `SettingsService` checks whether to import
- THEN the import MUST be executed
- AND all 5 schemas MUST be upserted in OpenRegister
- AND the stored version MUST be updated to `0.2.0` after successful import

---

## Acceptance Criteria

- [ ] `lib/Settings/planninq_register.json` contains schemas `task`, `project`, `column`, `timeEntry`, `label` and no `example` schema
- [ ] Each schema has the correct `required` array and property definitions as specified in design.md
- [ ] Register file version is `0.2.0`
- [ ] `SettingsService` bumps trigger an import when `0.1.0` is stored and `0.2.0` is in the file
- [ ] After a fresh install, all seed objects are present and queryable via the OpenRegister API
- [ ] Re-importing does not create duplicate objects (verified by checking object count before and after a manual re-import)
- [ ] Task creation via the API without `title` returns HTTP 400
- [ ] Task creation with `status: "unknown"` returns HTTP 400
- [ ] `DeepLinkRegistrationListener` no longer references the `example` schema slug
