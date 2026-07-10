---
status: proposed
---

# Planix Beta Cross-Surface Alignment

## Purpose

Planix's code, product page, and docs SHALL present the same canonical feature vocabulary, version, and dependency list, so a prospective admin reading conduction.nl/apps/planix, the Nextcloud App Store listing, or planix.conduction.nl gets a consistent, code-verified picture of what the app does before installing it in beta.

---

## Requirements

### Requirement: Canonical Feature Vocabulary Across Surfaces

`appinfo/info.xml` (EN + NL descriptions), the product page (`conduction-website/src/pages/apps/planix.mdx` + its NL mirror), and `docs/features/` SHALL name the same shipped feature set, drawn from `lib/Settings/planix_register.json` and `lib/Controller/*`/`src/views/*`: kanban board with WIP limits, backlog management, time tracking, task dependencies, projects, dashboard/My Work, labels, and Procest integration.

#### Scenario: info.xml feature bullets match shipped code

- **GIVEN** `appinfo/info.xml`'s EN and NL `<description>` "Key Features" lists
- **WHEN** compared against `lib/Settings/planix_register.json` schemas and `src/views/*`
- **THEN** every bullet MUST name a feature verifiable in code (no generic scaffold-internals bullets, no unverified claims)
- @e2e exclude Static metadata file, no runtime surface — verified by direct code comparison in this change's proposal.md

#### Scenario: Product page exists and matches info.xml language

- **GIVEN** a visitor navigates to conduction.nl/apps/planix
- **WHEN** the page renders
- **THEN** it MUST exist (not 404), show status "Beta", and its FeatureList items MUST use the same feature names as `appinfo/info.xml`
- @e2e exclude Marketing page with no app-side test harness — verified via WebFetch in this change's tasks.md

### Requirement: Declared OpenRegister Dependency

Because Planix is a documented OpenRegister thin client (`openspec/app-config.json`: `requiresOpenRegister: true`; all task/project/column/timeEntry/label/dependency data lives in OpenRegister), `appinfo/info.xml` SHALL declare that dependency to installers, consistent with the `procest`/`pipelinq` fleet convention (an XSD-comment inside `<dependencies>` plus a "Requires: OpenRegister" line in both language descriptions).

#### Scenario: info.xml names OpenRegister as a prerequisite

- **GIVEN** `appinfo/info.xml`
- **WHEN** an admin reads the EN or NL description before installing
- **THEN** it MUST state that OpenRegister is required, with a link to the OpenRegister App Store listing
- @e2e exclude Static metadata file — verified by direct file inspection

### Requirement: Docs Match the Register Schema

`planix/docs/features/register-schemas.md` and `docs/features/README.md` SHALL state the accurate schema count and list from `lib/Settings/planix_register.json`, and SHALL NOT claim shipped UI capabilities (e.g. subtasks) that have no corresponding frontend code.

#### Scenario: Schema count and list match the register JSON

- **GIVEN** `lib/Settings/planix_register.json` defines 6 schemas (task, project, column, timeEntry, label, dependency)
- **WHEN** `docs/features/register-schemas.md` and `docs/features/README.md` are read
- **THEN** both MUST list all 6 schemas and state the count as 6
- @e2e exclude Docs-only content, no runtime surface — verified by direct file comparison

#### Scenario: No unverified feature claims in docs

- **GIVEN** a docs page claims a task-management capability
- **WHEN** cross-checked against `src/` for a corresponding UI/store implementation
- **THEN** claims without an implementation MUST be removed or explicitly marked as roadmap (not "shipped")
- @e2e exclude Docs-only content — verified by direct code/docs comparison (removed "subtasks" claim, no subtask UI exists)
