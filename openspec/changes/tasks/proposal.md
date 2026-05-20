# Proposal: Tasks Specification

## Summary

Introduce the Task entity as the core unit of work in Planix — the project management Nextcloud app. Tasks have a title, description, assignee, due date, priority, status, and optional kanban-column placement. They belong to a project and support board (drag-and-drop), backlog, filtering, search, and bulk operations.

## Motivation

Planix has Projects and Columns defined but no Task entity. Without tasks, the kanban board is an empty shell — there is nothing to create, assign, track, or complete. This change delivers the full Task lifecycle: CRUD, kanban placement, search, and bulk actions. It aligns with iCalendar VTODO (RFC 5545), Schema.org Action/PlanAction, and the VNG InterneTaak standard.

## Affected Projects

- [x] Project: `planix` — Backend schema, controller, service, and frontend board/backlog/search components

## Scope

### In Scope

- `Task` OpenRegister schema with all properties from the context brief
- `TaskController` (create, read, update, delete, list, bulk-update)
- `TaskService` with business rules: default status/priority, WIP limit check, `completedAt` auto-set, column-clear on project move
- Nextcloud notification dispatch on assignment (`task_assigned`, respecting `notify_assigned` user setting)
- Frontend: `TaskCard.vue`, `TaskBoardView.vue` (kanban), `TaskBacklogView.vue` (list + bulk actions), `TaskDetailView.vue` (form + sidebar)
- Board filters: priority, assignee, label — without page reload
- Task search: title + description, case-insensitive, without page reload
- Bulk status update and bulk assignee update from backlog view
- Seed data: 5 realistic Dutch task objects in `lib/Settings/planix_register.json`
- `@spec` PHPDoc tags on all new classes and public methods

### Out of Scope

- Sub-tasks (`parent` field stored but no V1 sub-task UI — guarded by V1 guard scenario)
- Task dependencies (separate `dependency` entity, V1)
- CalDAV VTODO sync (`calendarEventUid` stored but sync not implemented — V1)
- Dashboard "Overdue task list" and "Tasks due this week" widgets (separate change)
- Due date warning badges on task cards (separate change: `task-due-date-warning`)
- Time entries linked to tasks (separate change)

## Approach

- Define `Task` schema in `lib/Settings/planix_register.json` under the `planix` register
- `TaskController` → thin REST controller; delegates entirely to `TaskService`
- `TaskService` enforces all business rules (status defaults, `completedAt`, WIP soft-limit, column-clear on project move, assignment notification)
- Frontend uses `createObjectStore('tasks')` with `selectionPlugin` (bulk ops) and `relationsPlugin` (project/column links)
- Kanban board (`TaskBoardView.vue`) uses drag-and-drop (`vue-draggable`) to update `column` + `columnOrder` atomically via a single `PATCH /api/tasks/{id}`
- WIP limit: frontend reads `column.wipLimit` and shows a `CnStatusBadge` warning when exceeded (soft limit — drop is still allowed)
- `CnMassActionBar` + `CnMassDeleteDialog` for bulk ops in backlog view
- `useListView` composable drives search + filter state (no custom debounce or query builder)
- Notifications via OpenRegister `NotificationService`

## New Dependencies

None — all capabilities are provided by `@conduction/nextcloud-vue` and OpenRegister.

## Impact

- `lib/Settings/planix_register.json` — adds `Task` schema + 5 seed objects
- `lib/Controller/TaskController.php` — new REST controller
- `lib/Service/TaskService.php` — business logic
- `appinfo/routes.php` — adds Task API routes
- `src/store/modules/tasks.js` — Pinia object store with selection + relations plugins
- `src/views/TaskBoardView.vue` — kanban board with drag-and-drop columns
- `src/views/TaskBacklogView.vue` — list view with bulk action bar
- `src/views/TaskDetailView.vue` — task form + sidebar
- `src/components/TaskCard.vue` — kanban card (avatar, due date, priority color, labels)
- `src/router/index.js` — adds `/tasks`, `/tasks/:id` routes
- `l10n/nl.json` + `l10n/en.json` — translation keys for all new UI strings

## Cross-Project Dependencies

- **OpenRegister** — `ObjectService`, `NotificationService`, `createObjectStore` (runtime dependency, already present)
- **@conduction/nextcloud-vue** — `CnIndexPage`, `CnMassActionBar`, `CnFormDialog`, `CnObjectSidebar`, `useListView` (runtime, already bundled)

## Risks

### Risk 1: Drag-and-drop column ordering race condition
**Severity:** Medium — **Mitigation:** PATCH sends both `column` and `columnOrder` in one request. Backend `TaskService` validates the column belongs to the same project before saving.

### Risk 2: Assignment notification self-send
**Severity:** Low — **Mitigation:** `TaskService::notifyAssigned()` compares `$assignedTo` to the requesting user's UID and skips the notification if equal.

### Risk 3: WIP limit UI inconsistency across clients
**Severity:** Low — **Mitigation:** WIP limit is a soft limit; the backend never blocks the save. The warning is purely cosmetic and computed client-side from column metadata already loaded with the board.

## Rollback Strategy

All Task data lives in OpenRegister objects. Remove the `Task` schema from `planix_register.json`, delete the controller/service, and remove routes. No custom DB migrations to reverse.
