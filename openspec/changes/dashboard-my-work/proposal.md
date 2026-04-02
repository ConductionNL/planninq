# Change Proposal: dashboard-my-work

**Change ID:** dashboard-my-work
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

Planix now has projects, tasks, and a kanban board — but there is no personal landing page. When a user opens the app, they have no immediate overview of their own work state. Every user must manually navigate to a project and search for their tasks, making it hard to prioritise daily work or spot overdue items at a glance.

This change delivers two personal views that transform Planix into a daily-driver tool:

1. **Personal Dashboard** — the default landing page (`/`) showing KPI cards (open tasks, overdue, in progress, completed today), the 5 most recently active projects the user is a member of, and tasks due within 7 days. No new entities are needed; the dashboard is a frontend aggregation over existing Task and Project data.
2. **My Work view** (`/my-work`) — a priority-sorted, urgency-grouped list of all tasks assigned to the current user, with an inline status update control so users can act on tasks without navigating away.

Both views are personal and user-scoped. No new backend entities or PHP controllers are required beyond SPA route registrations. The dashboard is deliberately minimal (3 parallel API calls) to keep load times fast even for users who are members of many projects.

---

## What Changes

Adds the Vue frontend and routing needed to:

1. **Dashboard view** — route `/` renders `DashboardView.vue`. On mount, 3 parallel API calls fetch: tasks assigned to current user, projects the user is a member of, and tasks with `dueDate` within 7 days. The view renders 4 KPI cards and two list sections (recent projects, due this week).
2. **KPI card component** — `KpiCard.vue` — reusable card showing a label, count, icon, and color. Clickable; emits a filter value that the parent uses to navigate to `/my-work?filter=<value>`.
3. **Recent projects list** — `DashboardRecentProjects.vue` — shows the 5 most recently active projects the user is in (sorted by `updatedAt` desc), with title, color/icon, task count, and a progress bar (done/total).
4. **Due this week section** — `DashboardDueThisWeek.vue` — lists tasks with `dueDate` within the next 7 days, sorted by due date ascending, with project badge and due date chip.
5. **My Work view** — route `/my-work` renders `MyWorkView.vue`. Fetches tasks assigned to current user. Groups them into Overdue (red header), Due this week, and Everything else. Within each group, sorts by priority (urgent → high → normal → low).
6. **My Work task row** — `MyWorkTaskRow.vue` — shows task title (clickable → `/tasks/:id`), project badge, due date chip, priority dot, and an inline status dropdown. Status can be updated without navigating away.
7. **Empty states** — `CnEmptyState` used for: no-projects dashboard, no-tasks-due-this-week section, empty My Work list.
8. **Filter URL integration** — My Work reads `?filter` query param on mount and applies it (overdue, in_progress, open, completed_today). KPI cards write the filter param on navigate.
9. **Vue Router routes** — `/` (Dashboard) and `/my-work` (My Work) added.
10. **Navigation wiring** — "My Work" entry added to `MainMenu.vue`; Dashboard is already the root path.
11. **i18n strings** — all user-visible strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`dashboard-my-work`** — implementing the personal dashboard and My Work views defined in `openspec/specs/dashboard-my-work.md`. This change brings the capability from spec-only to fully implemented: KPI cards, recent projects with progress bars, due-this-week task list, My Work grouping/sorting, inline status update, filter URL integration, and all empty states.

No new capabilities are introduced. The `dashboard-my-work` capability was declared in the spec and depends on the `tasks` and `projects` capabilities already implemented.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `src/views/DashboardView.vue` | New — dashboard landing page with 3 parallel fetches, KPI cards, recent projects, due this week |
| `src/views/MyWorkView.vue` | New — My Work view with grouping, sorting, filter URL integration |
| `src/components/KpiCard.vue` | New — reusable KPI card: label, count, icon, color, clickable |
| `src/components/DashboardRecentProjects.vue` | New — recent projects list with progress bars |
| `src/components/DashboardDueThisWeek.vue` | New — due-this-week task list with date chips |
| `src/components/MyWorkTaskRow.vue` | New — single task row in My Work with inline status dropdown |
| `src/router/index.js` | Modified — add `/` (Dashboard) and `/my-work` routes |
| `src/navigation/MainMenu.vue` | Modified — add My Work navigation entry |
| `appinfo/routes.php` | Modified — add SPA catch-all for `/my-work` |
| `l10n/en.json` | Modified — add all dashboard and My Work translation strings |
| `l10n/nl.json` | Modified — add Dutch translations for all dashboard/My Work strings |

### Risk

Low. This change is entirely additive — no existing components or views are modified (except routing and navigation). The dashboard is a pure frontend aggregation over existing API endpoints. No new PHP services are introduced. The only PHP change is adding a catch-all route for `/my-work` in `appinfo/routes.php`.

The inline status update in My Work reuses the existing `tasksStore.updateStatus()` action from the `tasks` change — no new store logic is needed. The `tasks` change must be applied before this one.

### Dependencies

- `register-schemas` must be applied first (Task and Project schemas must exist in OpenRegister).
- `projects` change must be applied first (project store and `useObjectStore('planix', 'project')` must be in place).
- `tasks` change must be applied first (`useTasksStore`, `TaskStatusBadge`, and `updateStatus` must exist; `TaskCard` is available as a reference but not directly reused here — `MyWorkTaskRow` is a custom row layout).
- `@conduction/nextcloud-vue` must export `CnEmptyState`, `useObjectStore`, `CnDetailPage` (already declared in `package.json`).
- OpenRegister `^v0.2.10` (already declared).
