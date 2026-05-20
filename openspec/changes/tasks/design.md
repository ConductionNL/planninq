# Design: Tasks Specification

## Summary

Introduce the `Task` entity as the core unit of work in Planix. Tasks are stored as OpenRegister objects under the `planix` register and `task` schema. The frontend delivers kanban board (drag-and-drop), backlog list (bulk actions), search, and per-task detail with sidebar.

## Context

Planix already has a `Project` schema and a `Column` schema (from the `register-schemas` change). The kanban board view and backlog view are scaffolded but render no data — no Task schema exists yet. This change fills that gap by defining the Task entity, wiring up a thin REST controller + service layer, and building the three frontend views (board, backlog, detail) that operate on tasks.

## Goals / Non-Goals

**Goals:**
- Define the `Task` schema with all properties specified in the context brief
- Implement Task CRUD via `TaskController` + `TaskService`
- Enforce business rules: default status/priority, `completedAt` auto-set on done, column-clear on project move, WIP soft-limit warning
- Dispatch `task_assigned` Nextcloud notification respecting `notify_assigned` user setting
- Deliver board, backlog, search, bulk-update, and task detail in the frontend
- Include 5 Dutch seed objects for QA and browser test support

**Non-Goals:**
- Sub-task UI (V1 — `parent` field is stored but no nested task view)
- CalDAV VTODO sync (V1 — `calendarEventUid` stored as back-reference only)
- Task dependency linking (V1 — separate `dependency` entity)
- Due date warning badges (separate change: `task-due-date-warning`)
- Time entry tracking (separate change)

## Decisions

### Decision 1: Single OpenRegister schema for Task

All task data is stored as OpenRegister objects. No custom Entity/Mapper. The `Task` schema lives in `planix_register.json` alongside `Project` and `Column`. Cross-entity references (project, column, parent) use OpenRegister `relations` — not foreign keys.

**Rationale:** Consistent with ADR-001 (data layer). OpenRegister provides CRUD, audit trail, search, and file attachment for free. A custom mapper would duplicate platform capability.

### Decision 2: `completedAt` set by service, not client

`TaskService::save()` checks if the target column has `type: done`. If so, it sets `status = 'done'` and `completedAt = now()` server-side, regardless of what the client sends. The client cannot set `completedAt` directly.

**Rationale:** Ensures `completedAt` is always authoritative and prevents backdating. Aligns with iCalendar VTODO `COMPLETED` field semantics.

### Decision 3: WIP limit is a soft limit (warn, not block)

When a task is dropped into a column that already has `wipLimit` tasks, the backend saves the task and the frontend shows a `CnStatusBadge` warning on the column header. The backend never returns a 4xx for WIP overflow.

**Rationale:** Context brief explicitly states "soft limit, not blocked". Hard limits would break drag-and-drop UX and require compensating rollback logic in the frontend.

### Decision 4: Column cleared on project move

`TaskService::save()` detects when `project` changes (comparing previous vs new value). If the project changes, `column` is set to `null` and `columnOrder` to `0`, placing the task in the new project's backlog.

**Rationale:** A column belongs to a project. A task assigned to Column X of Project A has no valid column in Project B. Silently clearing prevents an orphan column reference.

### Decision 5: Bulk operations via CnMassActionBar + ObjectService

Bulk status and bulk assignee updates use `ObjectService::saveObjects()` (batch save). The `selectionPlugin` on the task store tracks selected task IDs. `CnMassActionBar` renders the floating bulk action bar.

**Rationale:** The platform provides this pattern at zero cost. Custom bulk-update endpoints would duplicate ObjectService capability.

### Decision 6: Drag-and-drop via vue-draggable

Kanban column drag-and-drop uses `vue-draggable` (already in the Nextcloud ecosystem). On `@end`, the store dispatches a single `PATCH /api/tasks/{id}` with both `column` and `columnOrder`.

**Rationale:** Vue-draggable is the standard within the @nextcloud/vue ecosystem. The atomic patch avoids partial-update races.

## Schema Definition

Schema slug: `task` | Register: `planix`

| Property | Type | Required | Default | Notes |
|---|---|---|---|---|
| `title` | string | Yes | — | iCalendar `SUMMARY`, schema.org `name` |
| `description` | string | No | — | iCalendar `DESCRIPTION` |
| `status` | enum | Yes | `open` | open / in_progress / blocked / done / cancelled |
| `priority` | enum | No | `normal` | low / normal / high / urgent |
| `project` | relation (Project) | No | — | OpenRegister relation |
| `column` | relation (Column) | No | null | null = backlog |
| `columnOrder` | integer | No | 0 | position within column |
| `assignedTo` | string | No | — | Nextcloud user UID |
| `dueDate` | date | No | — | iCalendar `DUE` |
| `startDate` | date | No | — | iCalendar `DTSTART` |
| `estimatedDuration` | integer | No | — | minutes; iCalendar `ESTIMATED-DURATION` |
| `percentComplete` | integer (0–100) | No | 0 | iCalendar `PERCENT-COMPLETE` |
| `labels` | string[] | No | [] | tag chips on card |
| `parent` | relation (Task) | No | null | V1 sub-task parent |
| `calendarEventUid` | string | No | null | back-ref to NC Tasks VTODO |
| `completedAt` | datetime | No | null | set by service when status → done |

VNG InterneTaak mapping:
- `title` → `gevraagdeHandeling`
- `assignedTo` → `toegewezenAanGebruikersnaam`
- `dueDate` → `gevraagdeDatum`
- `completedAt` → `afhandelingsdatum`

## Reuse Analysis

The following OpenRegister and @conduction/nextcloud-vue capabilities are used directly — no custom reimplementation:

| Capability | Platform component | Custom code |
|---|---|---|
| CRUD (create/read/update/delete) | `ObjectService.saveObject()` / `deleteObject()` | None |
| Batch save (bulk ops) | `ObjectService.saveObjects()` | None |
| List with search + filter | `useListView` composable + `CnFilterBar` | None |
| Schema-driven create/edit form | `CnFormDialog` | None |
| Delete confirmation dialog | `CnDeleteDialog` | None |
| Bulk action bar | `CnMassActionBar` | None |
| Object sidebar (files, audit, notes) | `CnObjectSidebar` | None |
| Nextcloud notifications | `NotificationService` | `task_assigned` event type + `notify_assigned` guard |
| Pinia task store | `createObjectStore('tasks')` | `selectionPlugin`, `relationsPlugin` |
| Column reference fetch | `relationsPlugin` `fetchUses` | None |
| Task search | `IndexService` (full-text via OpenRegister) | None |

Custom business logic built on top:
- `TaskService::notifyAssigned()` — self-assignment guard + `notify_assigned` user setting check
- `TaskService::handleColumnMove()` — `completedAt` auto-set + WIP soft-limit evaluation
- `TaskService::handleProjectMove()` — column-clear logic
- `TaskCard.vue` — custom kanban card layout (avatar, priority color, label chips, due date)

## Seed Data

Five realistic Dutch task objects for the `planix` register, `task` schema. These are loaded via `importFromApp()` on install and are idempotent (matched by slug).

### Seed 1: Homepage redesign

```json
{
  "@self": {
    "register": "planix",
    "schema": "task",
    "slug": "task-homepage-redesign"
  },
  "title": "Homepage redesign uitwerken",
  "description": "Nieuwe visuele stijl voor de homepage van de gemeentelijke website op basis van het NL Design System.",
  "status": "in_progress",
  "priority": "high",
  "assignedTo": "jan.devries",
  "dueDate": "2026-06-15",
  "startDate": "2026-05-20",
  "estimatedDuration": 480,
  "percentComplete": 35,
  "labels": ["frontend", "design", "nldesign"],
  "columnOrder": 1
}
```

### Seed 2: API documentatie

```json
{
  "@self": {
    "register": "planix",
    "schema": "task",
    "slug": "task-api-documentatie"
  },
  "title": "API documentatie schrijven voor zaakregister",
  "description": "OpenAPI 3.0 specificatie opstellen en publiceren voor het zaakregister endpoint.",
  "status": "open",
  "priority": "normal",
  "assignedTo": "priya.sharma",
  "dueDate": "2026-06-30",
  "estimatedDuration": 240,
  "percentComplete": 0,
  "labels": ["backend", "documentatie"],
  "columnOrder": 2
}
```

### Seed 3: Gebruikerstesten

```json
{
  "@self": {
    "register": "planix",
    "schema": "task",
    "slug": "task-gebruikerstesten"
  },
  "title": "Gebruikerstesten uitvoeren voor de meldingsmodule",
  "description": "Testscenario's uitvoeren met vijf burgers voor de herziene meldingen-flow. Bevindingen vastleggen in testrapport.",
  "status": "blocked",
  "priority": "high",
  "assignedTo": "fatima.elamrani",
  "dueDate": "2026-05-28",
  "estimatedDuration": 360,
  "percentComplete": 10,
  "labels": ["qa", "ux", "burgerzaken"],
  "columnOrder": 1
}
```

### Seed 4: Sprint retrospective

```json
{
  "@self": {
    "register": "planix",
    "schema": "task",
    "slug": "task-sprint-retro"
  },
  "title": "Sprint 12 retrospective voorbereiden",
  "description": "Retrospectiveformat kiezen, agenda opsturen naar het team en actiepunten van sprint 11 evalueren.",
  "status": "done",
  "priority": "normal",
  "assignedTo": "mark.visser",
  "dueDate": "2026-05-16",
  "estimatedDuration": 90,
  "percentComplete": 100,
  "labels": ["scrum"],
  "columnOrder": 3,
  "completedAt": "2026-05-16T10:30:00+02:00"
}
```

### Seed 5: Bugfix login validatie

```json
{
  "@self": {
    "register": "planix",
    "schema": "task",
    "slug": "task-bugfix-login"
  },
  "title": "Bugfix: validatiefout in inlogformulier",
  "description": "Het e-mailveld accepteert momenteel adressen zonder TLD. Regex-validatie aanscherpen en unit test toevoegen.",
  "status": "open",
  "priority": "urgent",
  "assignedTo": "jan.devries",
  "dueDate": "2026-05-22",
  "estimatedDuration": 60,
  "percentComplete": 0,
  "labels": ["bugfix", "auth"],
  "columnOrder": 1
}
```
