# Change Proposal: tasks

**Change ID:** tasks
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

The `register-schemas` change defined the Task schema in OpenRegister, and the `projects` change established the project container that tasks belong to. However, there is currently no way to create, view, update, or manage tasks — the core work item in Planix. Every downstream feature (kanban board, time tracking, dashboard, reporting) depends on a functional task management layer.

Tasks are the primary unit of work in Planix. Without task CRUD, assignment, status transitions, and search, the application has no usable value for end users. This change delivers the complete task interaction layer: Vue components for listing, creating, editing, and deleting tasks; a Pinia task store built on `useObjectStore`; PHP notification services for assignment and due-date alerts; backlog list integration; search and filter; and bulk operations. It also delivers the reusable `TaskCard` component that the separate `kanban-board` change will consume.

---

## What Changes

Build the Vue frontend, PHP notification backend, and routing needed to:

1. **Task detail view** — route `/tasks/:id` renders `TaskDetail.vue` using `CnDetailPage` with a `CnObjectSidebar` (Files, Notes, Tags, Audit Trail tabs). The sidebar also exposes task metadata editing (assignee, priority, due date, labels, project).
2. **Task creation dialog** — `TaskCreateDialog.vue` modal for creating tasks; supports title (required), description, priority, project, due date, assignee, and labels. New tasks go to the project backlog by default (`column: null`).
3. **TaskCard component** — reusable card showing title, assignee avatar, due-date chip (with overdue highlighting), priority color indicator, and label chips. Used by the backlog list and consumed by the `kanban-board` change.
4. **Pinia task store** — `useTasksStore` wraps `useObjectStore('planix', 'task')`. Exposes full CRUD, status lifecycle, search/filter, and bulk operations.
5. **Backlog integration** — `ProjectBacklog.vue` (placeholder shell in the `projects` change) is implemented using `CnDataTable` with the task store, column-less task filter, and row-level actions.
6. **Search and filter** — client-side search by title/description (debounced 300 ms); server-side filter by assignee, priority, label, and status.
7. **Bulk operations** — bulk status update and bulk assignee update from the backlog view; per-user notification rules respected.
8. **PHP NotificationService** — `lib/Service/NotificationService.php` with `SUBJECT_SETTING_MAP` for `task_assigned` and `task_due_soon` subjects. Respects user notification preferences; suppresses self-assignment notifications.
9. **PHP Notifier** — `lib/Notifier/TaskNotifier.php` implementing `INotifier` for rendering task-related notifications in the Nextcloud notification bell.
10. **Vue Router routes** — `/tasks/:id` with props mapping.
11. **Navigation wiring** — "Tasks" entry in `MainMenu.vue` linking to a global task list (`/tasks`); task links on `TaskCard` components navigate to `/tasks/:id`.
12. **i18n strings** — all user-visible strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`tasks`** — implementing the full task lifecycle defined in `openspec/specs/tasks.md`. This change brings the capability from spec-only (schema defined by `register-schemas`) to fully implemented: CRUD, status lifecycle, assignment with notifications, priority, due dates, labels, search/filter, bulk operations, and task detail view.

No new capabilities are introduced. The `tasks` capability was declared in the spec and the schema was prepared by `register-schemas`; this change completes the interaction layer.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `src/views/TaskDetail.vue` | New — task detail view using `CnDetailPage` + `CnObjectSidebar` |
| `src/views/TaskList.vue` | New — global task list using `CnDataTable` |
| `src/store/tasks.js` | New — Pinia store for task CRUD, status lifecycle, bulk ops, search/filter |
| `src/components/TaskCard.vue` | New — reusable task card for backlog and kanban board |
| `src/components/TaskMetaSidebar.vue` | New — metadata sidebar panel (assignee, priority, due date, labels) |
| `src/components/dialogs/TaskCreateDialog.vue` | New — task creation modal |
| `src/components/dialogs/TaskDeleteDialog.vue` | New — task deletion confirmation with sub-task warning |
| `src/components/TaskStatusBadge.vue` | New — status badge component used on card and detail view |
| `src/components/TaskBulkActionBar.vue` | New — bulk action toolbar for backlog CnDataTable selection |
| `src/router/index.js` | Modified — add `/tasks`, `/tasks/:id` routes |
| `src/navigation/MainMenu.vue` | Modified — add Tasks navigation entry |
| `src/views/ProjectBacklog.vue` | Modified — implement backlog using `CnDataTable` + task store |
| `appinfo/routes.php` | Modified — add SPA catch-all routes for `/tasks*` paths |
| `lib/Service/NotificationService.php` | New — PHP notification service with `SUBJECT_SETTING_MAP` |
| `lib/Notifier/TaskNotifier.php` | New — `INotifier` implementation for task notifications |
| `lib/AppInfo/Application.php` | Modified — register `TaskNotifier` |
| `l10n/en.json` | Modified — add all task-related translation strings |
| `l10n/nl.json` | Modified — add Dutch translations for all task strings |

### Risk

Low-to-medium. Frontend additions are purely additive. The PHP notification layer is new and requires correct registration in `Application.php`, which touches an existing file. Backlog view implementation modifies `ProjectBacklog.vue` from a placeholder to a functional view; this is a controlled modification.

The `TaskCard` component is designed for reuse by the `kanban-board` change — its public props interface must be stable. Any later breaking change to `TaskCard` props will require a coordinated update with the kanban-board change.

### Dependencies

- `register-schemas` must be applied first (Task schema must exist in OpenRegister).
- `projects` change must be applied first (`ProjectBacklog.vue` placeholder must exist; project routes and store must be in place).
- `@conduction/nextcloud-vue` must export `CnDetailPage`, `CnObjectSidebar`, `CnDataTable`, `useObjectStore`, `useDetailView` (already declared in `package.json`).
- OpenRegister `^v0.2.10` (already declared).
- Nextcloud `INotifier` interface available via `OCP\Notification\INotifier` (standard Nextcloud API).
