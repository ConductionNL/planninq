# Change Proposal: register-schemas

**Change ID:** register-schemas
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

The Planix register file (`lib/Settings/planix_register.json`) was bootstrapped from a project template and still contains a single placeholder `example` schema. No real data model is registered with OpenRegister, which means:

- The tasks, projects, kanban, and time-tracking features cannot be developed or tested against real schemas.
- OpenRegister cannot validate or enforce the data model constraints described in ARCHITECTURE.md.
- Seed data cannot be loaded on install, leaving fresh deployments with an empty, unusable app.

This is the foundational prerequisite for all subsequent Planix feature work. Nothing else in the roadmap can be meaningfully implemented until the real schemas exist.

---

## What Changes

Replace the single `example` placeholder schema in `planix_register.json` with 5 production-ready schemas that fully encode the Planix data model:

| Schema | Schema.org type | Purpose |
|--------|----------------|---------|
| `task` | schema:Action / schema:PlanAction | Core unit of work, maps to iCalendar VTODO |
| `project` | schema:CreativeWork | Container for tasks and columns |
| `column` | schema:DefinedTerm | Kanban column belonging to a project |
| `timeEntry` | schema:QuantitativeValue | Time logged against a task |
| `label` | schema:DefinedTerm | Colour-tagged label applicable to tasks and projects |

Each schema includes:
- Full property definitions (type, description, required, enum, format, default)
- Version `0.1.0` to align with the initial register version
- Icons and slugs following the OpenRegister convention

Additionally, seed data (3–5 realistic objects per schema) will be defined per ADR-016 so that a fresh install starts with a working demonstration environment.

---

## Capabilities

### New capability
- **`register-schemas`** — defines and registers the complete Planix data model in OpenRegister; owned by this change

### Modified capabilities
- **`tasks`** — the Task schema reference becomes concrete (previously pointed to ARCHITECTURE.md with no registered schema)
- **`projects`** — the Project schema reference becomes concrete; Column and Label schemas are prerequisites for the project feature

---

## Impact

### Files changed
| File | Change |
|------|--------|
| `lib/Settings/planix_register.json` | Remove `example` schema; add `task`, `project`, `column`, `timeEntry`, `label` schemas with full property definitions and seed data |
| `lib/Service/SettingsService.php` | Bump register version (e.g. `0.1.0` → `0.2.0`) to trigger re-import on next app load |
| `lib/Listener/DeepLinkRegistrationListener.php` | Remove any reference to the `example` schema slug if present |

### Risk
Low. No UI or API code references the `example` schema by slug. Replacing it only affects OpenRegister's internal schema store.

### Dependencies
- OpenRegister `^v0.2.10` (already declared in `x-openregister`)
- No new Nextcloud server dependencies
