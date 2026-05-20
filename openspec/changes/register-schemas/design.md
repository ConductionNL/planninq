# Design: Register Schemas

## Summary

Completes `lib/Settings/planix_register.json` with five validated OpenRegister schemas and realistic Dutch seed data. This register file is the foundational data layer that all other Planix features build upon — no other change can ship without these schemas being correctly defined.

## Motivation

Planix owns no database tables. Every object (task, project, column, time entry, label) is stored in OpenRegister as a JSON object validated against a schema declared in `planix_register.json`. Without complete, correct schema definitions OpenRegister cannot enforce required fields or enum constraints, and without seed data a fresh install presents users with an empty, unusable app.

## Approach

- `lib/Settings/planix_register.json` is an OpenAPI 3.0.0 document with `x-openregister` extensions
- `SettingsService::loadConfiguration()` reads the file and delegates to `ConfigurationService::importFromApp(appId, data, version, force)`
- `ConfigurationService` compares the stored version against `info.version`; if they match, import is skipped with no API calls
- When imported, schemas are upserted by `slug` and seed objects are upserted by `@self.slug`
- After import, `SettingsService::ensureRegisterPublicAccess()` sets `publicWrite=0 / publicRead=0` directly in the DB so the register is accessible only to authenticated Nextcloud users

## Register File Structure

```
lib/Settings/planix_register.json
├── openapi: "3.0.0"
├── info
│   ├── title: "Planix Register"
│   └── version: "0.2.0"          ← drives skip-logic comparison
├── x-openregister
│   ├── type: "application"
│   ├── app: "planix"
│   └── openregister: "^v0.2.10"
└── components
    ├── registers
    │   └── planix                 ← register definition
    ├── schemas
    │   ├── task
    │   ├── project
    │   ├── column
    │   ├── timeEntry
    │   └── label
    └── objects [ ... ]            ← seed data (upserted by @self.slug)
```

---

## Schema Definitions

### task

Maps to iCalendar `VTODO` (RFC 5545) and `schema:PlanAction`.

| Property | Type | Required | Constraints | iCalendar / Schema.org |
|---|---|---|---|---|
| title | string | ✓ | — | SUMMARY / name |
| description | string | — | — | DESCRIPTION / description |
| status | string | ✓ | enum: `open`, `in_progress`, `blocked`, `done`, `cancelled`; default: `open` | STATUS / actionStatus |
| priority | string | — | enum: `low`, `normal`, `high`, `urgent`; default: `normal` | PRIORITY / — |
| project | string (uuid) | — | ref to project object | RELATED-TO / isPartOf |
| column | string (uuid) | — | ref to column; `null` = backlog | — |
| columnOrder | integer | — | sort order within column; default: `0` | — |
| assignedTo | string | — | Nextcloud user UID | ATTENDEE / agent |
| dueDate | string (date) | — | ISO 8601 date | DUE / endTime |
| startDate | string (date) | — | ISO 8601 date | DTSTART / startTime |
| estimatedDuration | integer | — | minutes | DURATION / duration |
| percentComplete | integer | — | 0–100; default: `0` | PERCENT-COMPLETE / — |
| labels | array[uuid] | — | linked Label UUIDs; default: `[]` | CATEGORIES / — |
| parent | string (uuid) | — | parent task (sub-task); nullable | RELATED-TO / — |
| calendarEventUid | string | — | CalDAV VTODO UID; nullable | UID / identifier |
| completedAt | string (date-time) | — | ISO 8601; nullable | COMPLETED / — |
| zaakUuid | string (uuid) | — | Procest zaak reference; nullable | — |

---

### project

Maps to `schema:CreativeWork`. Receives task references via `caseReference` from Procest.

| Property | Type | Required | Constraints |
|---|---|---|---|
| title | string | ✓ | — |
| description | string | — | — |
| status | string | ✓ | enum: `active`, `archived`, `completed`; default: `active` |
| color | string | — | pattern: `^#[0-9A-Fa-f]{6}$` |
| icon | string | — | emoji or MDI icon name |
| members | array[string] | — | Nextcloud user UIDs; default: `[]` |
| defaultAssignee | string | — | Nextcloud user UID |
| caseReference | string (uuid) | — | Procest case UUID; nullable |
| labels | array[uuid] | — | Label UUIDs available in this project; default: `[]` |

---

### column

Maps to `schema:DefinedTerm`. Belongs to exactly one project; defines a kanban stage.

| Property | Type | Required | Constraints |
|---|---|---|---|
| title | string | ✓ | — |
| project | string (uuid) | ✓ | ref to project |
| order | integer | ✓ | 0-based display order; default: `0` |
| wipLimit | integer | — | work-in-progress limit; `null` = unlimited |
| color | string | — | pattern: `^#[0-9A-Fa-f]{6}$` |
| type | string | — | enum: `active`, `done`; default: `active` |

`type: done` signals to the frontend that tasks moved here are automatically marked `status: done`.

---

### timeEntry

Maps to `schema:QuantitativeValue`. Records time logged by a user against a task.

| Property | Type | Required | Constraints |
|---|---|---|---|
| task | string (uuid) | ✓ | ref to task |
| user | string | ✓ | Nextcloud user UID |
| duration | integer | ✓ | minutes logged |
| date | string (date) | ✓ | ISO 8601 date of work |
| description | string | — | optional work note |

---

### label

Maps to `schema:DefinedTerm`. Cross-project colour-coded tags applied to tasks and projects.

| Property | Type | Required | Constraints |
|---|---|---|---|
| title | string | ✓ | — |
| color | string | ✓ | pattern: `^#[0-9A-Fa-f]{6}$`; default: `"#4376FC"` |
| description | string | — | when to use this label |

---

## Seed Data

Seed objects are declared in `components/objects` and upserted by `@self.slug` on every import that detects a version bump. All seed data uses Dutch IT-team context.

### Labels (5 objects)

| Slug | Title | Color | Beschrijving |
|---|---|---|---|
| `bug` | Bug | `#E74C3C` | Iets is kapot en moet gerepareerd worden |
| `feature` | Feature | `#4376FC` | Nieuwe functionaliteit |
| `docs` | Docs | `#27AE60` | Documentatie-update |
| `design` | Design | `#9B59B6` | UI/UX-werk |
| `infrastructure` | Infrastructure | `#F39C12` | DevOps, deployment en serverbeheer |

### Projects (3 objects)

| Slug | Title | Status | Beschrijving |
|---|---|---|---|
| `client-portal-v2` | Client Portal v2 | active | Herontwerp en herbouw van het zelfservice-klantportaal met NL Design System componenten |
| `infrastructure-migration` | Infrastructure Migration | active | Migratie van on-premise diensten naar beheerd Kubernetes-cluster vóór Q3 |
| `onboarding-automation` | Onboarding Automation | active | Automatisering van onboarding-workflows voor nieuwe medewerkers via n8n en Nextcloud |

### Columns (12 objects, 4 per project)

| Slug | Project | Title | Order | Type | WIP-limiet |
|---|---|---|---|---|---|
| `portal-todo` | client-portal-v2 | To Do | 0 | active | — |
| `portal-in-progress` | client-portal-v2 | In Progress | 1 | active | 3 |
| `portal-review` | client-portal-v2 | Review | 2 | active | 2 |
| `portal-done` | client-portal-v2 | Done | 3 | done | — |
| `infra-todo` | infrastructure-migration | To Do | 0 | active | — |
| `infra-in-progress` | infrastructure-migration | In Progress | 1 | active | 3 |
| `infra-review` | infrastructure-migration | Review | 2 | active | 2 |
| `infra-done` | infrastructure-migration | Done | 3 | done | — |
| `onboard-todo` | onboarding-automation | To Do | 0 | active | — |
| `onboard-in-progress` | onboarding-automation | In Progress | 1 | active | 3 |
| `onboard-review` | onboarding-automation | Review | 2 | active | 2 |
| `onboard-done` | onboarding-automation | Done | 3 | done | — |

### Tasks (5 objects)

| Slug | Title | Project | Column | Status | Toegewezen aan |
|---|---|---|---|---|---|
| `fix-login-redirect` | Fix login page redirect bug | client-portal-v2 | portal-in-progress | in_progress | jdoe |
| `design-dashboard-widgets` | Design dashboard widget layout | client-portal-v2 | portal-todo | open | mvanderberg |
| `write-api-docs` | Write REST API documentation | client-portal-v2 | portal-todo | open | jdoe |
| `k8s-namespace-setup` | Set up production Kubernetes namespaces | infrastructure-migration | infra-todo | open | ksmits |
| `onboarding-n8n-workflow` | Build n8n onboarding workflow | onboarding-automation | onboard-todo | open | admin |

`fix-login-redirect` is the seed task that acceptance scenarios reference: it MUST land in `portal-in-progress` (column) under `client-portal-v2` (project).

### Time Entries (3 objects)

| Slug | Task | Gebruiker | Duur | Datum | Beschrijving |
|---|---|---|---|---|---|
| `te-fix-login-2026-04-01` | fix-login-redirect | jdoe | 90 min | 2026-04-01 | Redirect-probleem getraceerd naar ontbrekende `redirect_uri` state-parameter in OAuth middleware |
| `te-fix-login-2026-04-02` | fix-login-redirect | jdoe | 60 min | 2026-04-02 | Oplossing geïmplementeerd en unit test geschreven |
| `te-k8s-2026-04-01` | k8s-namespace-setup | ksmits | 45 min | 2026-04-01 | Namespace YAML-manifests opgesteld voor review |

---

## Import Mechanism

```
App activated / repair step
  └─► SettingsService::loadConfiguration(force=false)
        ├─ isOpenRegisterAvailable() === false → return error (no import)
        ├─ Read: lib/Settings/planix_register.json
        ├─ Parse JSON → configData
        ├─ configVersion = configData['info']['version']   ("0.2.0")
        └─► ConfigurationService::importFromApp(appId, data, configVersion, force)
              ├─ storedVersion === configVersion AND force===false → SKIP (return empty)
              ├─ Upsert schemas by slug (task, project, column, timeEntry, label)
              ├─ Upsert objects by @self.slug (idempotent)
              └─ Store configVersion in IAppConfig
        └─► SettingsService::ensureRegisterPublicAccess()
              └─ UPDATE openregister_registers SET public_write=0, public_read=0
                 WHERE slug='planix'
```

### Version skip logic

| Stored version | File version | Result |
|---|---|---|
| `0.2.0` | `0.2.0` | Skip — no API calls |
| `0.1.0` | `0.2.0` | Import — all 5 schemas + objects upserted |
| _(not set)_ | `0.2.0` | Import — first install |

### Idempotency key

Seed objects use `@self.slug` as their idempotency key. Running the import twice with the same file version is prevented by the version skip. Running it after a version bump upserts objects — it never inserts duplicates, because `ConfigurationService` looks up existing objects by slug before deciding whether to insert or update.

---

## File Locations

| File | Purpose |
|---|---|
| `lib/Settings/planix_register.json` | OpenAPI 3.0.0 register definition with schemas and seed data |
| `lib/Service/SettingsService.php` | Reads the file and calls ConfigurationService; exposes `loadConfiguration()` |
| `lib/Listener/DeepLinkRegistrationListener.php` | Must NOT reference the `example` schema slug |
