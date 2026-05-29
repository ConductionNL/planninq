# Planix — Architecture & Data Model

## 1. Overview

Planix is a Kanban-based project and task management app for Nextcloud, built as a thin client on OpenRegister. It manages projects, tasks, kanban boards, and time entries for internal dev and IT teams. Tasks flow through configurable kanban columns in a continuous-flow (non-sprint) model with WIP limits, backlog management, and time tracking.

### Architecture Pattern

```
┌─────────────────────────────────────────────────┐
│  Planix Frontend (Vue 2 + Pinia)                │
│  - Dashboard (My Work, recent projects)         │
│  - Project list / detail views                  │
│  - Kanban board view (drag-and-drop)            │
│  - Backlog view (sorted task list)              │
│  - Task detail view (CnDetailPage + sidebar)    │
│  - Time tracking (log, timesheets)              │
│  - Admin settings                               │
└──────────────┬──────────────────────────────────┘
               │ REST API calls
┌──────────────▼──────────────────────────────────┐
│  OpenRegister API                                │
│  /api/objects/{register}/{schema}/{id}           │
│  - CRUD operations                              │
│  - Search, pagination, filtering                │
└──────────────┬──────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────┐
│  OpenRegister Storage (PostgreSQL)               │
│  - JSON object storage                          │
│  - Schema validation                            │
└─────────────────────────────────────────────────┘
```

Planix owns **no database tables**. All data is stored as OpenRegister objects, defined by schemas in a dedicated register.

## 2. Standards Research

Before defining our data model, we evaluated multiple standards across three categories.

### 2.1 Standards Evaluated

| Standard | Type | Coverage | Maturity | Relevance |
|----------|------|----------|----------|-----------|
| **iCalendar VTODO (RFC 5545)** | International | Task title, description, due date, start date, status, priority, percent-complete, assignee (ATTENDEE), categories, recurrence, alarms | Very mature (1998, widely implemented) | **HIGH** — primary field reference for task data |
| **Schema.org Action** | International | Action, PlanAction, ScheduleAction — agent, actionStatus, startTime, endTime, scheduledTime | Very mature | **HIGH** — semantic type annotations |
| **Schema.org ItemList / DefinedTerm** | International | ItemList (ordered list), ListItem, DefinedTerm (controlled vocabulary term) | Very mature | **HIGH** — models boards and columns |
| **OpenProject Work Packages API** | Industry | subject, description, startDate, dueDate, estimatedTime, spentTime, percentageDone, status, priority, assignee, parent, relations | Mature (EU government adoption) | **HIGH** — proven data model for work packages |
| **Nextcloud CalDAV VTODO** | Nextcloud native | Tasks implemented via VTODO in Calendar app | Active, NC-bundled | **HIGH** — potential reuse for task sync |
| **GitHub Issues API** | Industry | title, body, labels, assignees, milestone, state, comments, reactions | Very mature | **MEDIUM** — reference for dev-team task model |
| **VNG Zaak/Taak** | Dutch gov | Case-bound tasks within ZGW; InterneTaak in Klantinteracties | Part of ZGW family | **MEDIUM** — relevant for Procest bridge |
| **BPMN 2.0 User Task** | International | UserTask, assignment (assignee, candidateGroups), form fields | ISO/IEC 19510 | **LOW** — too process-oriented for kanban |
| **W3C PROV-O** | International | Activity, Entity, Agent provenance tracking | W3C Recommendation | **LOW** — audit trail reference only |

### 2.2 Design Principle: International First

> **Data storage uses international standards. Dutch government standards are an API mapping layer.**

This means:
- Objects in OpenRegister are modeled after **iCalendar VTODO and Schema.org** conventions
- When exposing a ZGW-compatible API, we **map** our international objects to Zaak/Taak field names
- This makes Planix usable in any organization while remaining interoperable with Dutch systems

### 2.3 Key Findings

1. **iCalendar VTODO (RFC 5545)** provides the most comprehensive field reference for task management. Properties like `SUMMARY`, `DESCRIPTION`, `DTSTART`, `DUE`, `STATUS`, `PRIORITY`, `PERCENT-COMPLETE`, `ATTENDEE`, `CATEGORIES`, `RELATED-TO`, and `VALARM` map directly to our task data model. Nextcloud's own Tasks app implements VTODO natively via CalDAV.

2. **Schema.org Action** family (`Action`, `PlanAction`, `ScheduleAction`) provides semantic type annotations. `schema:Action` captures the essential who (`agent`), what (`object`), when (`startTime`/`endTime`/`scheduledTime`), and status (`actionStatus`) for any task.

3. **OpenProject's Work Package model** is the industry reference for server-side project management. It proves a flat JSON object approach (no separate sprint table) works at scale, with `estimatedTime`, `spentTime`, `percentageDone`, and `derivedStartDate`/`derivedDueDate` for hierarchy.

4. **Kanban methodology** is standards-agnostic. WIP limits, column-based flow, and cumulative flow diagrams are practices, not data schema. We store `wipLimit` on columns and derive flow metrics from task state history.

5. **Nextcloud Tasks app** (CalDAV/VTODO) is the existing native task implementation. Planix augments it with project organization, kanban boards, and time tracking — features the Tasks app explicitly excludes. We store a `calendarEventUid` reference to allow optional two-way sync.

6. **VNG InterneTaak** (Klantinteracties) defines tasks within customer interactions. Tasks from Procest cases can be managed in Planix via the cross-app task bridge. The `zaakUuid` field on a Planix task links back to the originating case.

7. **GitHub Issues** proves that labels, milestones, assignees, and states are sufficient for dev-team task management. Complex metadata (story points, velocity) can be derived from these primitives plus time data.

## 3. Data Model Decisions

### 3.1 Standards Hierarchy

| Layer | Standard | Purpose |
|-------|----------|---------|
| **Primary (storage)** | iCalendar VTODO (RFC 5545) | Field reference for task properties |
| **Semantic** | Schema.org JSON-LD (Action, ItemList, DefinedTerm) | Type annotations for linked data |
| **API mapping** | VNG ZGW InterneTaak | Dutch government interoperability |
| **Pattern** | OpenProject Work Package, GitHub Issues | Proven dev-team task model |
| **Nextcloud native** | CalDAV/VTODO, Calendar, Files, Activity | Reuse where possible |

### 3.2 Entity Definitions

#### Task (Taak)

A task is the core unit of work in Planix. Tasks belong to a project, can be placed in a kanban column, and carry time estimates and time log entries.

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Schema.org type** | `schema:Action` (subtype `schema:PlanAction`) | "An action performed by a direct agent" — matches task concept |
| **VTODO alignment** | Fields named after VTODO properties where applicable | iCalendar is the most mature task standard |
| **VNG mapping** | `InterneTaak` (Klantinteracties) | Dutch API compatibility for Procest bridge |
| **Status storage** | Status stored directly on task (`task.status`) | Simpler queries; industry consensus (GitHub Issues, OpenProject) |
| **Column placement** | `column` reference on task, optional | Tasks without a column are in the backlog |
| **Time tracking** | `estimatedDuration` on task, separate `TimeEntry` objects for logs | Separating estimate from actuals (OpenProject pattern) |
| **Hierarchy** | `parent` reference for sub-tasks | Optional; supports 1-level sub-task depth |
| **CalDAV sync** | `calendarEventUid` stores VTODO UID | Optional sync to Nextcloud Tasks app |

**Core properties**:

| Property | Type | VTODO (RFC 5545) | Schema.org | VNG InterneTaak | Required | Default |
|----------|------|------------------|------------|-----------------|----------|---------|
| `title` | string | `SUMMARY` | `schema:name` | `gevraagdeHandeling` | Yes | — |
| `description` | string | `DESCRIPTION` | `schema:description` | — | No | — |
| `status` | enum | `STATUS` | `schema:actionStatus` | `status` | Yes | `open` |
| `priority` | enum: low, normal, high, urgent | `PRIORITY` (1-9) | — | — | No | `normal` |
| `project` | reference | `RELATED-TO` (parent project) | — | — | No | — |
| `zaakUuid` | string (UUID) | — | — | Procest case UUID (cross-app bridge) | No | null |
| `column` | reference | — | — | — | No | null (backlog) |
| `columnOrder` | integer | — | `schema:position` | — | No | 0 |
| `assignedTo` | string (user UID) | `ATTENDEE` | `schema:agent` | `toegewezenAanGebruikersnaam` | No | — |
| `dueDate` | date | `DUE` | `schema:scheduledTime` | `gevraagdeDatum` | No | — |
| `startDate` | date | `DTSTART` | `schema:startTime` | — | No | — |
| `estimatedDuration` | integer (minutes) | `ESTIMATED-DURATION` (RFC 7986) | — | — | No | — |
| `percentComplete` | integer (0–100) | `PERCENT-COMPLETE` | — | — | No | 0 |
| `labels` | string[] | `CATEGORIES` | — | — | No | [] |
| `parent` | reference | `RELATED-TO` (parent task) | — | — | No | null |
| `calendarEventUid` | string | `UID` | — | — | No | null |
| `completedAt` | datetime | `COMPLETED` | `schema:endTime` | `afhandelingsdatum` | No | null |

**Status values** (aligned with VTODO STATUS + Dutch lifecycle):

| Status | VTODO | Dutch | Description |
|--------|-------|-------|-------------|
| `open` | `NEEDS-ACTION` | Open | Not yet started |
| `in_progress` | `IN-PROCESS` | In behandeling | Being worked on |
| `blocked` | — | Geblokkeerd | Blocked by dependency |
| `done` | `COMPLETED` | Gereed | Completed |
| `cancelled` | `CANCELLED` | Geannuleerd | Cancelled |

#### Project

A project is the top-level container for tasks. Each project has exactly one kanban board (columns are part of the project). Projects serve as the boundary for task organization, permissions, and reporting.

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Schema.org type** | `schema:CreativeWork` | Closest match — "The most generic kind of creative work" used as project container |
| **Board model** | 1 project = 1 kanban board | Simplest model; columns belong to project directly |
| **No sprints** | Flow-based, continuous delivery | Kanban-only per user decision |
| **Backlog** | Tasks in the project without a column | Industry pattern — implicit backlog |
| **Procest link** | `caseReference` optional foreign key | Bridge for cases-as-projects integration |

**Core properties**:

| Property | Type | Schema.org | Required | Default |
|----------|------|------------|----------|---------|
| `title` | string | `schema:name` | Yes | — |
| `description` | string | `schema:description` | No | — |
| `status` | enum: active, archived, completed | `schema:creativeWorkStatus` | Yes | `active` |
| `color` | string (hex) | — | No | — |
| `icon` | string (emoji or MDI icon name) | — | No | — |
| `members` | string[] (user UIDs) | `schema:member` | No | [] |
| `defaultAssignee` | string (user UID) | — | No | — |
| `caseReference` | string (Procest case UUID) | — | No | null |
| `labels` | string[] | — | No | [] |

#### Column

A column represents a stage in the project's kanban board. Columns have an explicit order and optional WIP (work-in-progress) limit.

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Schema.org type** | `schema:DefinedTerm` | Term within a controlled vocabulary (the board as `schema:DefinedTermSet`) |
| **WIP limit** | `wipLimit` integer, null = no limit | Core kanban practice; proven by Kanboard, Jira Kanban |
| **Soft limit** | Violations flagged in UI, not blocked | Industry consensus — soft limits with visual warning |
| **Column types** | `type` enum: backlog, active, done | Semantic: determines which columns count as "completed" for metrics |

**Core properties**:

| Property | Type | Schema.org | Required | Default |
|----------|------|------------|----------|---------|
| `title` | string | `schema:name` | Yes | — |
| `project` | reference | `schema:inDefinedTermSet` | Yes | — |
| `order` | integer | `schema:position` | Yes | 0 |
| `wipLimit` | integer \| null | — | No | null |
| `color` | string (hex) | — | No | — |
| `type` | enum: `active`, `done` | — | No | `active` |

**Default columns** (created during project initialization):

| Order | Title | WIP Limit | Type |
|-------|-------|-----------|------|
| 0 | To Do | null | active |
| 1 | In Progress | 3 | active |
| 2 | Review | 2 | active |
| 3 | Done | null | done |

#### TimeEntry

A time entry records actual time spent on a task by a specific user.

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Schema.org type** | `schema:QuantitativeValue` with `schema:unitCode` = `MIN` (minutes) | Quantitative measurement with unit |
| **Granularity** | Minutes (integer) | Matches OpenProject; avoids floating-point issues |
| **Multiple per task** | Separate entity, many-to-one to Task | Users can log multiple time entries across different days |
| **No timer** | Manual entry only in MVP | Simplest implementation; automatic timer in V1 |

**Core properties**:

| Property | Type | Schema.org | Required | Default |
|----------|------|------------|----------|---------|
| `task` | reference | `schema:subjectOf` | Yes | — |
| `user` | string (user UID) | `schema:agent` | Yes | current user |
| `duration` | integer (minutes) | `schema:value` (unitCode: MIN) | Yes | — |
| `date` | date | `schema:startDate` | Yes | today |
| `description` | string | `schema:description` | No | — |

#### Label

Labels are cross-project tags that can be applied to tasks and projects. Stored as shared vocabulary objects.

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Schema.org type** | `schema:DefinedTerm` | Controlled vocabulary term |
| **Scope** | App-wide (not per-project) | Enables cross-project filtering; matches GitHub labels model |
| **Color** | Required | Visual identification; key for kanban card scanning |

**Core properties**:

| Property | Type | Schema.org | Required | Default |
|----------|------|------------|----------|---------|
| `title` | string | `schema:name` | Yes | — |
| `color` | string (hex) | — | Yes | `#4376FC` |
| `description` | string | `schema:description` | No | — |

### 3.3 Cross-App Relationships

```
Planix (Task Management)              Procest (Case Management)
┌──────────────────────┐              ┌─────────────────────┐
│  Project             │              │                     │
│  Column              │              │   Case              │
│  Task ───────────────┼──────────────┼─► Task (InterneTaak)│
│  TimeEntry           │  caseRef     │   Decision          │
│  Label               │              │   Status            │
└──────────────────────┘              └─────────────────────┘
         │
         │ (future)
┌────────▼─────────────┐
│  Pipelinq (CRM)      │
│  Lead / Request      │
└──────────────────────┘
```

**Procest integration**: A Procest case can create tasks in Planix via the cross-app link. The `caseReference` on a Planix Project (or `zaakUuid` on an individual Task) maintains the bridge. Task completion status is mirrored back to the case status where configured.

### 3.4 My Work (Werkvoorraad)

A personal workload view showing all tasks assigned to the current user, across all projects. No new entity is needed — this is a frontend aggregation pattern.

**How it works**:
- Query tasks with `assignedTo == currentUser` and `status != done AND status != cancelled`
- Group by: Overdue (dueDate < today), Due this week, No due date
- Sort by: priority (urgent first), then dueDate ascending

**Required fields already present on Task**:

| Field | Present |
|-------|---------|
| `assignedTo` | Yes |
| `priority` | Yes |
| `dueDate` | Yes |
| `status` | Yes |
| `project` (reference) | Yes |

### 3.5 @conduction/nextcloud-vue Library

All Planix UI MUST use the shared `@conduction/nextcloud-vue` library.

| Layer | What to Use | Purpose |
|-------|-------------|---------|
| **List views** | `CnListViewLayout` | Project list, backlog list layout |
| **Data tables** | `CnDataTable` | Backlog table, timesheet view |
| **Detail pages** | `CnDetailPage` | Task detail (card-based layout) |
| **Sidebar** | `CnObjectSidebar` | Task sidebar (Files, Notes, Tags, Tasks, Audit Trail) |
| **Status badges** | `CnStatusBadge` | Task status indicators on cards |
| **Empty states** | `CnEmptyState` | Empty project, empty backlog, no tasks |
| **Pagination** | `CnPagination` | Backlog list pagination |
| **Settings** | `CnSettingsSection`, `CnVersionInfoCard` | Admin settings page |
| **Store** | `useObjectStore` with plugins | All OpenRegister data operations |
| **Composables** | `useListView`, `useDetailView`, `useSubResource` | Page state management |

**Kanban board**: custom Planix component (drag-and-drop card grid), built on top of library base components. Uses `useObjectStore` for task data, `CnStatusBadge` for status indicators on cards.

```json
"@conduction/nextcloud-vue": "^0.1.0-beta.1"
```

Webpack alias (conditional for monorepo/CI compatibility):
```js
const fs = require('fs')
const localLib = path.resolve(__dirname, '../nextcloud-vue/src')
const useLocalLib = fs.existsSync(localLib)

// In resolve.alias:
...(useLocalLib ? { '@conduction/nextcloud-vue': localLib } : {}),
'vue$': path.resolve(__dirname, 'node_modules/vue'),
'pinia$': path.resolve(__dirname, 'node_modules/pinia'),
'@nextcloud/vue$': path.resolve(__dirname, 'node_modules/@nextcloud/vue'),
```

### 3.6 Vue Router (Navigation)

All navigation uses Vue Router (hash mode). Route table:

| Path | Name | Component | Props |
|------|------|-----------|-------|
| `/` | Dashboard | Dashboard | — |
| `/projects` | Projects | ProjectList | — |
| `/projects/:id` | ProjectBoard | ProjectBoard | `route => ({ projectId: route.params.id })` |
| `/projects/:id/backlog` | ProjectBacklog | ProjectBacklog | `route => ({ projectId: route.params.id })` |
| `/tasks/:id` | TaskDetail | TaskDetail | `route => ({ taskId: route.params.id })` |
| `/my-work` | MyWork | MyWork | — |
| `*` | — | redirect → `/` | — |

**Key files**: `src/router/index.js`, registered in `main.js`, `<router-view />` in `App.vue`.
**MainMenu**: use `:to` prop on `NcAppNavigationItem` (NOT `@click` + `$router.push()`).

### 3.7 Nextcloud Integration Strategy

**Principle: reuse Nextcloud native objects where possible, reference by ID, don't duplicate.**

#### REUSE from Nextcloud

| Feature | OCP Interface | What to Reuse | How |
|---------|--------------|---------------|-----|
| **Users / Assignees** | `OCP\IUserManager` | User identity, display name, avatar | Reference by user UID; resolve display name via `IUserManager::get()` |
| **Files** | `OCP\Files\IRootFolder` | Task attachments, project documents | Reference by Nextcloud file ID; resolve via `IRootFolder->getById()` |
| **Activity** | `OCP\Activity\IManager` | Task created/updated/completed events | Publish to activity stream; implement `IProvider` for rendering |
| **Comments** | `OCP\Comments\ICommentsManager` | Notes on tasks and projects | Attach using objectType `planix_task` + objectId |
| **System Tags** | `OCP\SystemTag\ISystemTagObjectMapper` | Cross-reference labels and categories | Tag task objects with label IDs |
| **Calendar** | `OCP\Calendar\IManager` | Task due dates synced to NC Calendar | Optional VTODO export via CalDAV; `calendarEventUid` back-reference |
| **Notifications** | `OCP\Notification\IManager` | Assignment, due date, status notifications | Publish structured notifications for in-app and push delivery |

#### BUILD in OpenRegister (Planix-specific)

| What | Why Not Reuse |
|------|---------------|
| **Projects** | Project-specific metadata: color, icon, members, columns, Procest link |
| **Tasks** | Full task lifecycle: kanban placement, time estimate, column order, sub-tasks |
| **Columns** | Kanban-specific: WIP limits, column type (active/done), ordered board |
| **TimeEntries** | Time tracking: per-user, per-task logs with date and description |
| **Labels** | App-scoped labels with colors — different from NC system tags |

#### Key OCP Interfaces

```php
// Users - resolve assignee display name
$userManager = \OCP\Server::get(\OCP\IUserManager::class);
$user = $userManager->get($userUid);
$displayName = $user?->getDisplayName() ?? $userUid;

// Activity - publish task events
$activityManager = \OCP\Server::get(\OCP\Activity\IManager::class);
$event = $activityManager->generateEvent();
$event->setApp('planix')->setType('task_update')->setSubject('task_assigned', ['task' => $title]);
$activityManager->publish($event);

// Notifications - task assignment
$notificationManager = \OCP\Server::get(\OCP\Notification\IManager::class);
$notification = $notificationManager->createNotification();
$notification->setApp('planix')->setUser($assignedTo)->setSubject('task_assigned');
$notificationManager->notify($notification);

// Files - resolve attachment
$rootFolder = \OCP\Server::get(\OCP\Files\IRootFolder::class);
$files = $rootFolder->getById($fileId);

// Calendar - export task as VTODO
$calendarManager = \OCP\Server::get(\OCP\Calendar\IManager::class);
// Use CalDAV write endpoint for VTODO creation
```

## 4. OpenRegister Configuration

### Register

| Field | Value |
|-------|-------|
| Name | `planix` |
| Slug | `planix` |
| Description | Project and task management register |

### Schema Definitions

Schemas MUST be defined in `lib/Settings/planix_register.json` using OpenAPI 3.0.0 format, following the pattern used by Pipelinq and Procest.

**Schemas**:
- `task` — Work item (schema:Action / schema:PlanAction)
- `project` — Task container with kanban board (schema:CreativeWork)
- `column` — Kanban board column (schema:DefinedTerm)
- `timeEntry` — Time log entry (schema:QuantitativeValue)
- `label` — Cross-project label/tag (schema:DefinedTerm)

The configuration is imported via `ConfigurationService::importFromApp()` in the repair step.

## 5. Resolved Research Questions

All research questions below have been resolved. Decisions are recorded in the app-specific ADRs under [`openspec/architecture/`](https://codeberg.org/Conduction/planix/src/branch/development/openspec/architecture).

1. **CalDAV VTODO sync** — **One-way export to Nextcloud Tasks app in V1.** The `calendarEventUid` field on Task stores the VTODO UID. Planix writes tasks to CalDAV; changes made in the Tasks app are not synced back. Two-way sync was rejected due to data model mismatch (Tasks app has no concept of projects, columns, or WIP limits).

2. **Sub-task depth** — **One level only (task → sub-task), all tiers.** Projects serve the "epic" grouping role. No formal Epic entity. This matches Linear and Plane (1 level + containers) and avoids Jira's 3-level complexity.

3. **Procest task bridge** — **Configurable — Procest UI decides.** Procest's UI presents a project picker when creating tasks for a case. It may create a new project (with `caseReference`) or add tasks to an existing project (with `zaakUuid` on each task). Planix has no routing mechanism — it reads whatever Procest wrote to OpenRegister. See ADR-003.

4. **Time tracking scope** — **Per-task only, forever.** `TimeEntry.task` is always required. Overhead work (meetings, planning) is tracked as tasks — not as project-level time entries. This keeps the data model simple and queryable. No special cases needed. See ADR-004.

5. **GitHub/GitLab sync** — **Via OpenConnector in V1.** Planix owns no GitHub/GitLab API code. OpenConnector handles the external API mapping (GitHub Issues ↔ Planix tasks). This avoids duplicating integration logic across Conduction apps.

6. **WIP limit enforcement** — **Soft limits with visual warning.** Column header turns orange/red when over the WIP limit; counter shows e.g. `4/3`. Drag is never blocked. Industry consensus: hard limits cause friction and workarounds (Jira, Kanboard both use soft limits).

## 6. References

### Primary Standards (International)
- [RFC 5545 — iCalendar](https://datatracker.ietf.org/doc/html/rfc5545) — VTODO component, task field reference
- [Schema.org](https://schema.org/) — Linked data vocabulary (primary data model)
- [RFC 7986 — iCalendar extensions](https://datatracker.ietf.org/doc/html/rfc7986) — ESTIMATED-DURATION, conference, image

### Schema.org Types Used
- [schema:Action](https://schema.org/Action) — Task (with agent, actionStatus, startTime, endTime)
- [schema:PlanAction](https://schema.org/PlanAction) — Planned task (with scheduledTime)
- [schema:CreativeWork](https://schema.org/CreativeWork) — Project container
- [schema:ItemList](https://schema.org/ItemList) — Kanban board (ordered list)
- [schema:DefinedTerm](https://schema.org/DefinedTerm) — Column and Label (controlled vocabulary)
- [schema:QuantitativeValue](https://schema.org/QuantitativeValue) — TimeEntry (value + unit)
- [schema:agent](https://schema.org/agent) — Assigned user

### Dutch Standards (API Mapping Layer)
- [VNG Klantinteracties — InterneTaak](https://vng-realisatie.github.io/klantinteracties/) — Internal task in customer interaction
- [VNG ZGW Zaken API](https://vng-realisatie.github.io/gemma-zaken/standaard/zaken/) — Case-bound work tracking
- [GEMMA Online](https://www.gemmaonline.nl/) — Dutch municipal architecture

### Industry References
- [OpenProject API — Work Packages](https://www.openproject.org/docs/api/endpoints/work-packages/) — Work package data model
- [Nextcloud Deck API](https://github.com/nextcloud/deck/blob/main/docs/API.md) — Board/Stack/Card pattern reference
- [GitHub Issues API](https://docs.github.com/en/rest/issues) — Issue/label/milestone model
- [Kanban Guide (Scrum.org)](https://kanbanguides.org/) — Kanban flow metrics and WIP limits
