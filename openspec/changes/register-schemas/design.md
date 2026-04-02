# Design: register-schemas

**Change ID:** register-schemas
**Status:** draft
**Created:** 2026-04-02

---

## Context

Planix uses OpenRegister as its data persistence layer. The app declares its schemas in `lib/Settings/planix_register.json`, which is an OpenAPI 3.0.0 document with `x-openregister` extensions (per ADR-013). On every app load, `SettingsService` checks the stored register version against the file version; if they differ, it re-imports the file, which upserts schemas and inserts any seed data.

The current file contains only a placeholder `example` schema. All 5 real schemas must be declared before any feature work can begin.

---

## Goals

- Replace the placeholder with 5 production-ready schemas that accurately reflect ARCHITECTURE.md.
- Provide 3–5 seed objects per schema so fresh installs are immediately functional.
- Keep all schemas in a single register file (one file per register, per ADR-013 convention).
- Trigger automatic re-import on install via a version bump.

## Non-Goals

- UI changes (no Vue or PHP controller changes).
- Schema migrations for existing data (no production data exists yet).
- Adding the `zaakUuid` integration logic — that belongs to `procest-integration`.

---

## Decisions

### Decision 1: All 5 schemas in a single `planix_register.json` file

**Options considered:**
1. Single file (chosen)
2. One file per schema, merged at import time

**Rationale:** All other Conduction apps keep their schemas in one register file. OpenRegister's import process expects a single file path per register declaration. Splitting would require custom loader logic and deviates from the established pattern.

### Decision 2: Schema slugs use camelCase matching property names

`task`, `project`, `column`, `timeEntry`, `label` — matching OpenRegister's existing convention in openregister and opencatalogi.

### Decision 3: `labels` field stores OpenRegister object UUIDs, not raw slugs

Tasks and projects reference Label objects by UUID (as stored by OpenRegister) rather than by slug string. This allows labels to be renamed without updating all referencing objects. The `labels` array property type is `string` (UUID strings at runtime).

### Decision 4: `zaakUuid` and `caseReference` kept as plain strings (not references)

Procest integration is out of scope for this change. These fields are declared as nullable strings so the schema is complete, but no referential validation is enforced at this stage.

### Decision 5: Register version bumped from `0.1.0` to `0.2.0`

A minor bump signals a non-breaking schema addition. The `SettingsService` version-check logic uses string inequality to trigger re-import, so any change in the version string suffices.

---

## Schema Definitions

### task (schema:Action / schema:PlanAction)

```json
{
  "slug": "task",
  "icon": "CheckboxMarkedOutline",
  "version": "0.1.0",
  "title": "Task",
  "description": "A discrete unit of work. Maps to iCalendar VTODO (RFC 5545) and schema:PlanAction.",
  "type": "object",
  "required": ["title", "status"],
  "properties": {
    "title":             { "type": "string",  "description": "Short title of the task" },
    "description":       { "type": "string",  "description": "Detailed description" },
    "status":            { "type": "string",  "description": "Current lifecycle status", "enum": ["open","in_progress","blocked","done","cancelled"], "default": "open" },
    "priority":          { "type": "string",  "description": "Priority level", "enum": ["low","normal","high","urgent"], "default": "normal" },
    "project":           { "type": "string",  "description": "UUID of the parent Project", "format": "uuid" },
    "zaakUuid":          { "type": "string",  "description": "UUID of a linked Procest zaak", "format": "uuid", "nullable": true },
    "column":            { "type": "string",  "description": "UUID of the kanban Column (null = backlog)", "format": "uuid", "nullable": true },
    "columnOrder":       { "type": "integer", "description": "Sort order within the column", "default": 0 },
    "assignedTo":        { "type": "string",  "description": "Nextcloud user UID of the assignee" },
    "dueDate":           { "type": "string",  "description": "Due date in ISO 8601 format", "format": "date" },
    "startDate":         { "type": "string",  "description": "Start date in ISO 8601 format", "format": "date" },
    "estimatedDuration": { "type": "integer", "description": "Estimated effort in minutes" },
    "percentComplete":   { "type": "integer", "description": "Completion percentage (0–100)", "minimum": 0, "maximum": 100, "default": 0 },
    "labels":            { "type": "array",   "description": "UUIDs of linked Label objects", "items": { "type": "string", "format": "uuid" }, "default": [] },
    "parent":            { "type": "string",  "description": "UUID of the parent Task (sub-task support)", "format": "uuid", "nullable": true },
    "calendarEventUid":  { "type": "string",  "description": "UID of the linked NC Tasks / CalDAV VTODO event", "nullable": true },
    "completedAt":       { "type": "string",  "description": "ISO 8601 datetime when the task was completed", "format": "date-time", "nullable": true }
  }
}
```

### project (schema:CreativeWork)

```json
{
  "slug": "project",
  "icon": "FolderOutline",
  "version": "0.1.0",
  "title": "Project",
  "description": "A container grouping related tasks and kanban columns. Maps to schema:CreativeWork.",
  "type": "object",
  "required": ["title", "status"],
  "properties": {
    "title":           { "type": "string", "description": "Project name" },
    "description":     { "type": "string", "description": "Purpose and scope of the project" },
    "status":          { "type": "string", "description": "Project lifecycle status", "enum": ["active","archived","completed"], "default": "active" },
    "color":           { "type": "string", "description": "Hex colour code for visual identification", "pattern": "^#[0-9A-Fa-f]{6}$" },
    "icon":            { "type": "string", "description": "Emoji or MDI icon name" },
    "members":         { "type": "array",  "description": "Nextcloud user UIDs of project members", "items": { "type": "string" }, "default": [] },
    "defaultAssignee": { "type": "string", "description": "Nextcloud user UID who receives new tasks by default" },
    "caseReference":   { "type": "string", "description": "UUID of a linked Procest case", "format": "uuid", "nullable": true },
    "labels":          { "type": "array",  "description": "UUIDs of Label objects available within this project", "items": { "type": "string", "format": "uuid" }, "default": [] }
  }
}
```

### column (schema:DefinedTerm)

```json
{
  "slug": "column",
  "icon": "ViewColumnOutline",
  "version": "0.1.0",
  "title": "Column",
  "description": "A kanban column belonging to a project. Maps to schema:DefinedTerm.",
  "type": "object",
  "required": ["title", "project", "order"],
  "properties": {
    "title":    { "type": "string",  "description": "Column heading (e.g. 'In Progress')" },
    "project":  { "type": "string",  "description": "UUID of the parent Project", "format": "uuid" },
    "order":    { "type": "integer", "description": "Left-to-right display order (0-based)", "default": 0 },
    "wipLimit": { "type": "integer", "description": "Work-in-progress limit (null = unlimited)", "nullable": true },
    "color":    { "type": "string",  "description": "Hex colour code for the column header", "pattern": "^#[0-9A-Fa-f]{6}$" },
    "type":     { "type": "string",  "description": "Functional type: active columns hold in-flight work; done columns auto-complete tasks", "enum": ["active","done"], "default": "active" }
  }
}
```

### timeEntry (schema:QuantitativeValue)

```json
{
  "slug": "timeEntry",
  "icon": "TimerOutline",
  "version": "0.1.0",
  "title": "Time Entry",
  "description": "Time logged by a user against a task. Maps to schema:QuantitativeValue.",
  "type": "object",
  "required": ["task", "user", "duration", "date"],
  "properties": {
    "task":        { "type": "string",  "description": "UUID of the related Task", "format": "uuid" },
    "user":        { "type": "string",  "description": "Nextcloud user UID of the person who logged the time" },
    "duration":    { "type": "integer", "description": "Time logged in minutes" },
    "date":        { "type": "string",  "description": "Date the work was performed (ISO 8601)", "format": "date" },
    "description": { "type": "string",  "description": "Optional note about the work performed" }
  }
}
```

### label (schema:DefinedTerm)

```json
{
  "slug": "label",
  "icon": "TagOutline",
  "version": "0.1.0",
  "title": "Label",
  "description": "A colour-coded tag applicable to tasks and projects. Maps to schema:DefinedTerm.",
  "type": "object",
  "required": ["title", "color"],
  "properties": {
    "title":       { "type": "string", "description": "Label display name" },
    "color":       { "type": "string", "description": "Hex colour for the label chip", "pattern": "^#[0-9A-Fa-f]{6}$", "default": "#4376FC" },
    "description": { "type": "string", "description": "Optional explanation of when to use this label" }
  }
}
```

---

## Seed Data (ADR-016)

Seed data represents a fictional IT consultancy, **Meridian Digital**, running internal projects. Objects are realistic enough to demonstrate all features on a fresh install. Each object uses the `@self` envelope pattern.

### Labels (5 objects)

```json
{ "@self": { "register": "planix", "schema": "label", "slug": "bug" },
  "title": "Bug", "color": "#E74C3C", "description": "Something is broken and needs fixing" }

{ "@self": { "register": "planix", "schema": "label", "slug": "feature" },
  "title": "Feature", "color": "#4376FC", "description": "New functionality" }

{ "@self": { "register": "planix", "schema": "label", "slug": "docs" },
  "title": "Docs", "color": "#27AE60", "description": "Documentation update" }

{ "@self": { "register": "planix", "schema": "label", "slug": "design" },
  "title": "Design", "color": "#9B59B6", "description": "UI/UX work" }

{ "@self": { "register": "planix", "schema": "label", "slug": "infrastructure" },
  "title": "Infrastructure", "color": "#F39C12", "description": "DevOps, deployment, and server work" }
```

### Projects (3 objects)

```json
{ "@self": { "register": "planix", "schema": "project", "slug": "client-portal-v2", "id": "00000000-0000-4000-a000-000000000001" },
  "title": "Client Portal v2", "description": "Redesign and rebuild the self-service client portal using NL Design System components.", "status": "active", "color": "#4376FC", "icon": "🌐", "members": ["admin", "jdoe", "mvanderberg"], "defaultAssignee": "jdoe" }

{ "@self": { "register": "planix", "schema": "project", "slug": "infrastructure-migration", "id": "00000000-0000-4000-a000-000000000002" },
  "title": "Infrastructure Migration", "description": "Migrate on-premise services to managed Kubernetes cluster before Q3.", "status": "active", "color": "#F39C12", "icon": "☁️", "members": ["admin", "ksmits"], "defaultAssignee": "ksmits" }

{ "@self": { "register": "planix", "schema": "project", "slug": "onboarding-automation", "id": "00000000-0000-4000-a000-000000000003" },
  "title": "Onboarding Automation", "description": "Automate new-employee onboarding workflows via n8n and Nextcloud.", "status": "active", "color": "#27AE60", "icon": "🤝", "members": ["admin", "jdoe", "mvanderberg", "ksmits"], "defaultAssignee": "admin" }
```

### Columns (12 objects — 4 per project)

Each project gets the default four-column board layout so testers see a full board immediately on every project.

**Client Portal v2:**
```json
{ "@self": { "register": "planix", "schema": "column", "slug": "portal-todo" },
  "title": "To Do", "project": "00000000-0000-4000-a000-000000000001", "order": 0, "wipLimit": null, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "portal-in-progress" },
  "title": "In Progress", "project": "00000000-0000-4000-a000-000000000001", "order": 1, "wipLimit": 3, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "portal-review" },
  "title": "Review", "project": "00000000-0000-4000-a000-000000000001", "order": 2, "wipLimit": 2, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "portal-done" },
  "title": "Done", "project": "00000000-0000-4000-a000-000000000001", "order": 3, "wipLimit": null, "type": "done" }
```

**Infrastructure Migration:**
```json
{ "@self": { "register": "planix", "schema": "column", "slug": "infra-todo" },
  "title": "To Do", "project": "00000000-0000-4000-a000-000000000002", "order": 0, "wipLimit": null, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "infra-in-progress" },
  "title": "In Progress", "project": "00000000-0000-4000-a000-000000000002", "order": 1, "wipLimit": 3, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "infra-review" },
  "title": "Review", "project": "00000000-0000-4000-a000-000000000002", "order": 2, "wipLimit": 2, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "infra-done" },
  "title": "Done", "project": "00000000-0000-4000-a000-000000000002", "order": 3, "wipLimit": null, "type": "done" }
```

**Onboarding Automation:**
```json
{ "@self": { "register": "planix", "schema": "column", "slug": "onboard-todo" },
  "title": "To Do", "project": "00000000-0000-4000-a000-000000000003", "order": 0, "wipLimit": null, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "onboard-in-progress" },
  "title": "In Progress", "project": "00000000-0000-4000-a000-000000000003", "order": 1, "wipLimit": 3, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "onboard-review" },
  "title": "Review", "project": "00000000-0000-4000-a000-000000000003", "order": 2, "wipLimit": 2, "type": "active" }

{ "@self": { "register": "planix", "schema": "column", "slug": "onboard-done" },
  "title": "Done", "project": "00000000-0000-4000-a000-000000000003", "order": 3, "wipLimit": null, "type": "done" }
```

**UUID reference table for seed data cross-references:**

| Object | Hard-coded UUID |
|--------|----------------|
| Project: client-portal-v2 | `00000000-0000-4000-a000-000000000001` |
| Project: infrastructure-migration | `00000000-0000-4000-a000-000000000002` |
| Project: onboarding-automation | `00000000-0000-4000-a000-000000000003` |
| Column: portal-todo | `00000000-0000-4000-b000-000000000001` |
| Column: portal-in-progress | `00000000-0000-4000-b000-000000000002` |
| Column: portal-review | `00000000-0000-4000-b000-000000000003` |
| Column: portal-done | `00000000-0000-4000-b000-000000000004` |
| Column: infra-todo | `00000000-0000-4000-b000-000000000005` |
| Column: infra-in-progress | `00000000-0000-4000-b000-000000000006` |
| Column: infra-review | `00000000-0000-4000-b000-000000000007` |
| Column: infra-done | `00000000-0000-4000-b000-000000000008` |
| Column: onboard-todo | `00000000-0000-4000-b000-000000000009` |
| Column: onboard-in-progress | `00000000-0000-4000-b000-000000000010` |
| Column: onboard-review | `00000000-0000-4000-b000-000000000011` |
| Column: onboard-done | `00000000-0000-4000-b000-000000000012` |
| Label: bug | `00000000-0000-4000-c000-000000000001` |
| Label: feature | `00000000-0000-4000-c000-000000000002` |
| Label: docs | `00000000-0000-4000-c000-000000000003` |
| Label: design | `00000000-0000-4000-c000-000000000004` |
| Label: infrastructure | `00000000-0000-4000-c000-000000000005` |
| Task: fix-login-redirect | `00000000-0000-4000-d000-000000000001` |
| Task: design-dashboard-widgets | `00000000-0000-4000-d000-000000000002` |
| Task: write-api-docs | `00000000-0000-4000-d000-000000000003` |
| Task: k8s-namespace-setup | `00000000-0000-4000-d000-000000000004` |
| Task: onboarding-n8n-workflow | `00000000-0000-4000-d000-000000000005` |

### Tasks (5 objects)

```json
{ "@self": { "register": "planix", "schema": "task", "slug": "fix-login-redirect" },
  "title": "Fix login page redirect bug", "description": "After OAuth login the user is redirected to / instead of the originally requested page.", "status": "in_progress", "priority": "high", "project": "00000000-0000-4000-a000-000000000001", "column": "00000000-0000-4000-b000-000000000002", "columnOrder": 0, "assignedTo": "jdoe", "dueDate": "2026-04-10", "labels": ["00000000-0000-4000-c000-000000000001"], "percentComplete": 40 }

{ "@self": { "register": "planix", "schema": "task", "slug": "design-dashboard-widgets" },
  "title": "Design dashboard widget layout", "description": "Create Figma mockups for the new widget-based dashboard using NL Design System tokens.", "status": "open", "priority": "normal", "project": "00000000-0000-4000-a000-000000000001", "column": "00000000-0000-4000-b000-000000000001", "columnOrder": 0, "assignedTo": "mvanderberg", "dueDate": "2026-04-18", "labels": ["00000000-0000-4000-c000-000000000004"] }

{ "@self": { "register": "planix", "schema": "task", "slug": "write-api-docs" },
  "title": "Write REST API documentation", "description": "Document all public endpoints in OpenAPI 3.0.0 format and publish to developer portal.", "status": "open", "priority": "normal", "project": "00000000-0000-4000-a000-000000000001", "column": "00000000-0000-4000-b000-000000000001", "columnOrder": 1, "assignedTo": "jdoe", "labels": ["00000000-0000-4000-c000-000000000003"], "estimatedDuration": 240 }

{ "@self": { "register": "planix", "schema": "task", "slug": "k8s-namespace-setup" },
  "title": "Set up production Kubernetes namespaces", "description": "Create namespaces, resource quotas, and network policies for prod environment.", "status": "open", "priority": "urgent", "project": "00000000-0000-4000-a000-000000000002", "column": "00000000-0000-4000-b000-000000000005", "columnOrder": 0, "assignedTo": "ksmits", "dueDate": "2026-04-07", "labels": ["00000000-0000-4000-c000-000000000005"] }

{ "@self": { "register": "planix", "schema": "task", "slug": "onboarding-n8n-workflow" },
  "title": "Build n8n onboarding workflow", "description": "Create the automated onboarding flow: create NC account, add to groups, send welcome email, generate Planix project.", "status": "open", "priority": "normal", "project": "00000000-0000-4000-a000-000000000003", "column": "00000000-0000-4000-b000-000000000009", "columnOrder": 0, "assignedTo": "admin", "estimatedDuration": 180, "labels": ["00000000-0000-4000-c000-000000000002"] }
```

### Time Entries (3 objects)

```json
{ "@self": { "register": "planix", "schema": "timeEntry", "slug": "te-fix-login-2026-04-01" },
  "task": "00000000-0000-4000-d000-000000000001", "user": "jdoe", "duration": 90, "date": "2026-04-01", "description": "Traced redirect issue to missing redirect_uri state parameter in OAuth middleware." }

{ "@self": { "register": "planix", "schema": "timeEntry", "slug": "te-fix-login-2026-04-02" },
  "task": "00000000-0000-4000-d000-000000000001", "user": "jdoe", "duration": 60, "date": "2026-04-02", "description": "Implemented fix and wrote unit test." }

{ "@self": { "register": "planix", "schema": "timeEntry", "slug": "te-k8s-2026-04-01" },
  "task": "00000000-0000-4000-d000-000000000004", "user": "ksmits", "duration": 45, "date": "2026-04-01", "description": "Drafted namespace YAML manifests for review." }
```

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Hard-coded UUIDs in seed data may conflict with OpenRegister's UUID generation | Low | Seed UUIDs use the `00000000-0000-4000-xxxx-xxxxxxxxxxxx` range which is unlikely to collide with random UUIDv4 values. Slug-based idempotency ensures re-import doesn't create duplicates. |
| Re-import on version bump deletes and recreates schemas | Low | OpenRegister upserts on slug — existing objects are not deleted unless the schema defines `deleteOrphans: true`. Confirm this behaviour in OpenRegister source. |
| `zaakUuid` field stores UUID but Procest integration is deferred | Low | Field is declared nullable and optional. No business logic references it in this change. |

---

## Resolved Questions

1. **Cross-object references in seed data** — Resolved: use hard-coded UUIDs (see UUID reference table above). OpenRegister does not support `@ref:` notation in seed data.
2. **Version-based skip logic** — Resolved: yes, `SettingsService` uses version comparison to skip unchanged versions. The bump from `0.1.0` to `0.2.0` triggers re-import.
3. **Column seeding scope** — Resolved: seed columns for all 3 projects (12 columns total) so testers see a full board on every project immediately.
