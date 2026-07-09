# Register Schemas

Defines and registers the complete Planix data model in OpenRegister.

## Overview

Planix uses OpenRegister to store its data model. The `planix_register.json` file defines 6 schemas and seed data that are automatically imported when the app is installed or upgraded.

## Schemas

- **task** — A work item with title, description, status, priority, assignee, dates, and labels
- **project** — A container for tasks with members, colors, and case references
- **column** — A kanban board column with WIP limits and ordering
- **timeEntry** — A time tracking record linked to a task
- **label** — A categorization tag with color coding
- **dependency** — A directed blocker → blocked edge between two tasks in the same project

## Seed Data

Fresh installs include demo data:
- 5 labels: Bug, Feature, Docs, Design, Infrastructure
- 3 projects: Client Portal v2, Infrastructure Migration, Onboarding Automation
- 12 columns: 4 per project (To Do, In Progress, Review, Done)
- 5 tasks with realistic assignments
- 3 time entries

## Technical Details

- Schemas are defined in `lib/Settings/planix_register.json`
- Import is triggered by the `InitializeSettings` repair step (declared in `appinfo/info.xml`)
- `SettingsService::loadConfiguration()` reads the JSON, parses it, and calls `ConfigurationService::importFromApp()`
- Import is idempotent — re-running does not create duplicates
- Required fields are validated by OpenRegister (e.g., task requires `title` and `status`)

## Specs

- [register-schemas spec](https://codeberg.org/Conduction/planix/src/branch/development/openspec/specs/register-schemas/spec.md)
