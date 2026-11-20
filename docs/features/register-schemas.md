# Register Schemas

Defines and registers the complete Planninq data model in OpenRegister.

## Overview

Planninq uses OpenRegister to store its data model. The `planninq_register.json` file defines 6 schemas and seed data that are automatically imported when the app is installed or upgraded.

## Schemas

- **task** — A work item with title, description, status, priority, assignee, dates, and labels
- **project** — A container for tasks with members, colors, and case references
- **column** — A kanban board column with WIP limits and ordering
- **timeEntry** — A time tracking record linked to a task
- **label** — A categorization tag with color coding
- **dependency** — A directed blocker → blocked edge between two tasks in the same project

## Seed Data

A fresh install creates only the five default labels: Bug, Feature, Docs, Design and Infrastructure.

Example projects come from the setup wizard's "Example data" step (`lib/Settings/planninq_mock_register.json`, loaded by `DemoDataService`). It adds three projects (Client Portal v2, Infrastructure Migration, Onboarding Automation) with their columns, phases, tasks, dependencies, logged time, risks, status reports, finance lines and portfolios. The admin who loads it becomes the owner and the assignee, and the dates are placed around the day it is loaded.

## Technical Details

- Schemas are defined in `lib/Settings/planninq_register.json`
- Import is triggered by the `InitializeSettings` repair step (declared in `appinfo/info.xml`)
- `SettingsService::loadConfiguration()` reads the JSON, parses it, and calls `ConfigurationService::importFromApp()`
- Import is idempotent — re-running does not create duplicates
- Required fields are validated by OpenRegister (e.g., task requires `title` and `status`)

## Specs

- [register-schemas spec](https://github.com/ConductionNL/planninq/blob/development/openspec/specs/register-schemas/spec.md)
