# Design: dashboard-my-work

**Change ID:** dashboard-my-work
**Status:** pr-created
**Created:** 2026-04-02

---

## Context

The `tasks` change built task CRUD, the task store, `TaskCard`, and `TaskStatusBadge`. The `projects` change built the project store and project list. This change builds the personal aggregation layer on top of those foundations — the dashboard and My Work views.

Planix is a thin client: all data lives in OpenRegister and is queried via `useObjectStore('planix', 'task')` and `useObjectStore('planix', 'project')`. The dashboard performs at most 3 parallel API calls on load and derives all KPI counts client-side. No new PHP services or database tables are needed.

---

## Goals

- Dashboard landing page (`/`) with 4 KPI cards, recent projects, and due-this-week tasks.
- KPI cards that navigate to My Work with a filter pre-applied.
- My Work view (`/my-work`) with three urgency groups, priority sort, and filter URL integration.
- Inline status update on My Work task rows (no full navigation).
- CnEmptyState for all empty states (no projects, no tasks due, no assigned tasks).
- Full i18n coverage (en + nl).

## Non-Goals

- Activity feed on dashboard (V1 — requires Nextcloud Activity API integration).
- Nextcloud Dashboard widget (`OCP\Dashboard\IWidget`) surfacing overdue count (V1).
- Sub-task display in My Work (tasks change handles sub-tasks as V1).
- Reporting or analytics (separate future change).
- Custom notification rules for dashboard data (no new PHP services in this change).

---

## Decisions

### Decision 1: Dashboard performs 2 parallel API calls (tasks + projects)

**Options considered:**
1. 3 parallel calls (tasks, projects, due-this-week tasks separately).
2. 2 parallel calls — derive due-this-week client-side from the task list (chosen).

**Rationale:** The due-this-week list is a subset of all assigned tasks. Eliminating the 3rd call simplifies the fetch logic. The 2 calls are:
1. `useObjectStore('planix', 'task').getObjects({ assignedTo: currentUser })` — all tasks assigned to current user (used for KPIs, My Work grouping, AND due-this-week derivation).
2. `useObjectStore('planix', 'project').getObjects({ members: currentUser })` — all projects the user is a member of (for recent projects).

Using `Promise.all` makes both calls fire simultaneously. The dashboard shows a skeleton loading state until both resolve. Due-this-week tasks are filtered client-side: `tasks.filter(t => t.dueDate && t.dueDate <= today+7 && t.status !== 'done')`.

**Note on project progress bars:** The progress bar on recent projects shows all tasks in the project (done/total), not just the current user's tasks. This requires fetching task counts per project — either via a lightweight count query per project or by fetching all project tasks in a 3rd call. The implementer should choose the most efficient approach based on OpenRegister's query capabilities.

### Decision 2: KPI counts are computed client-side from the task list

**Options considered:**
1. A dedicated API call per KPI (4 calls, each returning a count).
2. Derive all KPIs from the single task list returned by call 1 (chosen).

**Rationale:** Once call 1 returns all tasks assigned to the current user, KPI counts are simple array filters:
- **Open tasks**: `tasks.filter(t => ['open', 'in_progress'].includes(t.status)).length`
- **Overdue**: `tasks.filter(t => t.dueDate < today && t.status !== 'done' && t.status !== 'cancelled').length`
- **In progress**: `tasks.filter(t => t.status === 'in_progress').length`
- **Completed today**: `tasks.filter(t => t.completedAt && isToday(t.completedAt)).length`

No additional API calls are needed. This avoids 4 extra round-trips and keeps all data consistent (same snapshot).

### Decision 3: KpiCard is a pure display component — navigation handled by parent

**Options considered:**
1. `KpiCard` navigates internally using `useRouter`.
2. `KpiCard` emits `click` event; `DashboardView` handles navigation (chosen).

**Rationale:** `KpiCard` may be reused in different contexts (e.g., future reporting widget). Keeping navigation in the parent makes the component portable and testable in isolation. The parent (`DashboardView`) calls `router.push({ name: 'MyWork', query: { filter: filterValue } })` on the emitted `click` event.

Stable prop interface:
```js
props: {
  label: { type: String, required: true },
  count: { type: Number, required: true },
  icon: { type: String, required: true },   // icon component name
  color: { type: String, required: true },  // CSS variable name e.g. '--color-error'
  filterValue: { type: String, required: true } // passed back in click event
}
```

### Decision 4: My Work groups are computed client-side from a single task fetch

My Work fetches all tasks assigned to the current user (same call as the dashboard's call 1) and groups them into three buckets:

| Group | Condition | Header color |
|-------|-----------|--------------|
| **Overdue** | `dueDate < today && status !== 'done' && status !== 'cancelled'` | `--color-error` |
| **Due this week** | `dueDate >= today && dueDate <= today+7 && status !== 'done' && status !== 'cancelled'` | `--color-warning` |
| **Everything else** | all other non-done, non-cancelled tasks | default |

Within each group, tasks are sorted by priority:
```js
const PRIORITY_ORDER = { urgent: 0, high: 1, normal: 2, low: 3 }
group.sort((a, b) => PRIORITY_ORDER[a.priority] - PRIORITY_ORDER[b.priority])
```

Done and cancelled tasks are excluded from My Work entirely. If the user wants to see completed tasks, they navigate to the full task list (`/tasks`).

### Decision 5: Filter URL params control My Work initial state

KPI cards on the dashboard navigate to `/my-work?filter=<value>`. The `MyWorkView` reads the `filter` query param on mount and scrolls to / highlights the relevant group:

| Filter value | Behaviour |
|-------------|-----------|
| `overdue` | Scroll to Overdue group, briefly highlight |
| `in_progress` | Scroll to Everything Else group, filter to `in_progress` status within it |
| `open` | Show all three groups (default) |
| `completed_today` | Show a fourth ephemeral group "Completed Today" at the top (read-only, status `done` + `completedAt` is today) |

The filter param is not persistent — it only controls the initial scroll/highlight on load. The user can then freely browse all groups.

### Decision 6: Inline status update uses a dropdown overlay, not a dialog

**Options considered:**
1. Navigate to task detail to change status.
2. Inline `NcSelect` dropdown on the status cell of `MyWorkTaskRow` (chosen).

**Rationale:** The My Work view's primary value is momentum — users should be able to mark tasks done, move them in-progress, or block them without losing context. A dropdown on the status indicator is the fastest interaction pattern and is consistent with how `TaskMetaSidebar` works in the task detail view. The dropdown calls `tasksStore.updateStatus(taskId, newStatus)` directly. On success, the task row updates reactively (or disappears from the group if the new status moves it to done/cancelled).

### Decision 7: Recent projects list shows at most 5, sorted by updatedAt desc

The project store returns all projects the user is a member of. `DashboardRecentProjects` takes the top 5 by `updatedAt` descending. Progress bar calculation:
```js
const done = tasks.filter(t => t.project === project.id && t.status === 'done').length
const total = tasks.filter(t => t.project === project.id).length
const progress = total > 0 ? Math.round((done / total) * 100) : 0
```

Task counts come from the task list already fetched in call 1 (no extra API call). If the user is not a member of any project, `DashboardRecentProjects` is replaced by `CnEmptyState`.

### Decision 8: CnEmptyState is used for all empty states

All three empty states use the `CnEmptyState` component from `@conduction/nextcloud-vue`:

| Location | Title | Action |
|----------|-------|--------|
| Dashboard — no projects | "No projects yet" | "Create project" → `/projects/new` |
| Dashboard — no tasks due this week | "No tasks due this week" | none (informational only) |
| My Work — no assigned tasks | "No tasks assigned to you" | "Browse projects" → `/projects` |

The dashboard KPI cards always render (showing 0) even when there are no projects or tasks.

### Decision 9: No new store is created — reuse existing stores

`DashboardView` and `MyWorkView` import and use `useTasksStore` (from the `tasks` change) and `useProjectsStore` (from the `projects` change) directly. No new Pinia store is created for the dashboard. Dashboard-specific computed state (KPI counts, grouped tasks, recent projects) is defined as local `computed` refs inside the view component using Vue Composition API.

---

## Component Architecture

```
src/
  views/
    DashboardView.vue              # / — landing page, 3 parallel fetches
    MyWorkView.vue                 # /my-work — grouped task list, filter URL
  components/
    KpiCard.vue                    # Reusable KPI card (label, count, icon, color)
    DashboardRecentProjects.vue    # Recent 5 projects with progress bars
    DashboardDueThisWeek.vue       # Due-this-week task list with date chips
    MyWorkTaskRow.vue              # Single task row with inline status dropdown
  router/
    index.js                       # Modified — add / and /my-work routes
  navigation/
    MainMenu.vue                   # Modified — add My Work nav entry

appinfo/
  routes.php                       # Modified — add /my-work catch-all
```

---

## Dashboard Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  KPI Cards (4 columns, responsive → 2 on tablet → 1 on mobile)      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐               │
│  │ Open     │ │ Overdue  │ │In Progress│ │Completed │               │
│  │ 12       │ │ 3        │ │ 5         │ │ Today: 2 │               │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘               │
├──────────────────────────────────────────┬───────────────────────────┤
│  Recent Projects (left, ~60% width)      │  Due This Week (right)    │
│  ┌──────────────────────────────────┐    │  ┌─────────────────────┐  │
│  │ [icon] Project Alpha   ████░ 70% │    │  │ Task A — Project X  │  │
│  │ [icon] Project Beta    ██░░░ 40% │    │  │ Due today           │  │
│  │ ...                              │    │  │ Task B — Project Y  │  │
│  └──────────────────────────────────┘    │  │ Due in 3 days       │  │
│                                          │  └─────────────────────┘  │
└──────────────────────────────────────────┴───────────────────────────┘
```

KPI card colors (CSS variables):
- Open tasks: `--color-primary-element`
- Overdue: `--color-error`
- In progress: `--color-warning`
- Completed today: `--color-success`

---

## My Work Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  My Work                                        [filter dropdown]    │
├──────────────────────────────────────────────────────────────────────┤
│  ▼ Overdue  (red)                                                    │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ [!] Fix login bug  [Backend]  Overdue: Jan 30  [●] In Progress │  │
│  │ [!] Update docs    [Frontend] Overdue: Feb 1   [●] Open        │  │
│  └────────────────────────────────────────────────────────────────┘  │
│  ▼ Due This Week  (amber)                                            │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ [↑] Write tests    [Backend]  Due Feb 5   [●] Open             │  │
│  └────────────────────────────────────────────────────────────────┘  │
│  ▼ Everything Else                                                   │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ [↓] Refactor store [Backend]  No due date [●] Open             │  │
│  └────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

`MyWorkTaskRow` anatomy (left to right):
1. Priority dot (color-coded: urgent=red, high=amber, normal=none, low=blue)
2. Task title (clickable → `/tasks/:id`, truncated to 1 line)
3. Project badge (`NcBadge` with project color)
4. Due date chip (neutral / amber today / red overdue)
5. Status dropdown (`NcSelect` with `TaskStatusBadge` option slots)

---

## Vue Router Routes

| Path | Name | Component | Notes |
|------|------|-----------|-------|
| `/` | `Dashboard` | `DashboardView` | Default route — replaces any previous root redirect |
| `/my-work` | `MyWork` | `MyWorkView` | Reads `?filter` query param |

---

## PHP Routing

```php
// appinfo/routes.php additions
['name' => 'page#my_work', 'url' => '/my-work', 'verb' => 'GET'],
```

The root `/` route is already served by the existing `page#index` catch-all.

---

## i18n String Inventory

| Key context | Example string |
|-------------|----------------|
| Navigation | `My Work` |
| Dashboard title | `Dashboard` |
| KPI labels | `Open Tasks`, `Overdue`, `In Progress`, `Completed Today` |
| Recent projects header | `Recent Projects` |
| Recent projects progress | `{done} of {total} tasks done` |
| Due this week header | `Due This Week` |
| Due date chips | `Due today`, `Due tomorrow`, `Due {date}`, `Overdue: {date}` |
| Empty — no projects | `No projects yet` |
| Empty — no tasks due | `No tasks due this week` |
| Empty — no assigned | `No tasks assigned to you` |
| Empty actions | `Create project`, `Browse projects` |
| My Work title | `My Work` |
| My Work groups | `Overdue`, `Due This Week`, `Everything Else`, `Completed Today` |
| My Work row — no due date | `No due date` |
| Status labels | `Open`, `In Progress`, `Blocked`, `Done`, `Cancelled` |
| Priority labels | `Urgent`, `High`, `Normal`, `Low` |
| Error states | `Failed to load dashboard data`, `Failed to update task status` |
| Loading state | `Loading your work…` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| 3 parallel API calls on dashboard load are slow for users in many projects | Low | Skeleton loading state hides the delay. If call 1 returns quickly, KPIs and My Work are usable before calls 2 and 3 resolve. |
| `completed_today` filter group relies on client-side `completedAt` date comparison | Low | `completedAt` is set by the `tasks` change store action to `new Date().toISOString()`. Timezone drift is acceptable at MVP. |
| Inline status update in My Work causes task to disappear from the group it was in | Intentional | Task reactively moves to the correct group or disappears (if done/cancelled). This is the expected UX — it mirrors how kanban columns work. Show a brief undo toast in V1. |
| Dashboard root route (`/`) may conflict with an existing redirect | Low | Check current `router/index.js` before applying. The `projects` change likely has a root redirect that this change replaces with the Dashboard component. |
