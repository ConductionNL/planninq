# Design: Dashboard & My Work

## Summary

The dashboard (route `/`) and My Work (route `/my-work`) are pure frontend aggregation views over the existing Task and Project stores. No new entities, no new API endpoints, no backend changes. Both views use a shared `useDashboard` composable for data fetching and KPI computation.

## Motivation

Planix needs a personal landing page that shows a user's work state at a glance. The My Work view provides a focused, urgency-grouped task inbox. Both are computed projections of data that already exists in the system.

## Goals / Non-Goals

**Goals:**
- Provide a personal dashboard as the default Planix landing page (`/`)
- Provide a My Work view with urgency grouping and inline status updates
- Parallelise API calls — dashboard interactive within 2 seconds
- All text translated (en + nl per ADR-007)
- WCAG AA: keyboard navigation, screen-reader labels, colour not the sole indicator

**Non-Goals:**
- Activity feed (V1 — Nextcloud Activity API integration deferred)
- Nextcloud Dashboard widget `OCP\Dashboard\IWidget` (V1 integration)
- New OpenRegister entities or API endpoints
- Sub-task display in My Work (V1 feature)

---

## Component Architecture

| View | Root Component | Note |
|---|---|---|
| Dashboard | `DashboardView.vue` → `CnDashboardPage` | Self-contained — do NOT wrap in `NcAppContent` (ADR-017) |
| My Work | `MyWorkView.vue` → `CnIndexPage` | Self-contained — do NOT wrap in `NcAppContent` (ADR-017) |
| KPI card | `KpiCard.vue` | Stateless, reusable |
| Task row | `MyWorkTaskRow.vue` | Used in My Work and due-this-week section |

All component imports MUST come from `@conduction/nextcloud-vue`, NOT `@nextcloud/vue` (ADR-015). Every component used in a template MUST be imported AND listed in `components: {}`.

---

## Composable: `useDashboard`

Single composable centralises all dashboard data and computed logic.

### Fetching (parallelised)

```js
const [myTasks, recentProjects] = await Promise.all([
  taskStore.fetchMyTasks(currentUser),
  projectStore.fetchMyProjects({ limit: 5, sort: 'updatedAt:desc' })
])
```

All API calls use `@nextcloud/axios` — NEVER raw `fetch()` (ADR-015, CSRF requirement).

### Computed KPIs (derived from `myTasks`, no extra API calls)

| KPI card | Filter |
|---|---|
| Open | `status === 'open' \|\| status === 'in_progress'` |
| Overdue | `dueDate < today && status !== 'done'` |
| In progress | `status === 'in_progress'` |
| Completed today | `completedAt` date part === today's date |

### My Work urgency grouping (computed)

Tasks are distributed into three groups, in display order:

1. **Overdue** — `dueDate < today && status !== 'done'`
2. **Due this week** — `dueDate >= today && dueDate <= today + 7 days && status !== 'done'`
3. **Everything else** — all remaining non-done tasks (no due date, or due date > 7 days out)

Within each group, tasks are sorted by `priority` descending: `urgent → high → normal → low`.

Tasks with `status === 'done'` or `status === 'cancelled'` do NOT appear in any group.

### Exposed refs

```js
return {
  loading,        // Boolean — true while fetching
  error,          // String | null — error message if fetch failed
  kpis,           // { open, overdue, inProgress, completedToday }
  recentProjects, // Array<Project> (max 5)
  dueThisWeek,    // Array<Task> sorted by dueDate asc
  tasksByUrgency, // { overdue: Task[], dueThisWeek: Task[], everythingElse: Task[] }
}
```

---

## Routing

```
/            → DashboardView.vue
/my-work     → MyWorkView.vue
/my-work?filter=overdue      → My Work pre-scrolled to Overdue group
/my-work?filter=in_progress  → My Work pre-scrolled to In progress group
/my-work?filter=open         → My Work pre-scrolled to Everything else group
/my-work?filter=completed_today → My Work (informational — completed tasks not shown in groups)
```

KPI card click emits a router push to `/my-work?filter={cardKey}`. My Work reads `route.query.filter` on mount to scroll to or highlight the relevant group.

---

## Inline Status Update (My Work)

Task rows show a status chip. Clicking opens an `NcSelect` dropdown placed in the row's action area, following the `header-actions` pattern (ADR-018). Valid statuses: `open`, `in_progress`, `blocked`, `done`, `cancelled`.

On status select:

1. Call `taskStore.updateTask(task.id, { status })` — uses `@nextcloud/axios` PATCH
2. Wrap in `try/catch` — on error show `NcToast` with generic message (ADR-015)
3. Pinia reactivity re-computes `tasksByUrgency` — task moves out of its group if the new status qualifies (e.g., `done` removes it entirely)

---

## Empty States

| Condition | Location | Component | Message | Action |
|---|---|---|---|---|
| No projects | Dashboard recent-projects section | `CnEmptyState` | "No projects yet" | "Create project" → `/projects/new` |
| No tasks due this week | Dashboard due-this-week section | `CnEmptyState` | "No tasks due this week" | — |
| No assigned tasks | My Work (full view) | `CnEmptyState` | "No tasks assigned to you" | "Browse projects" → `/projects` |

KPI cards MUST render with count `0` for new users — they are never hidden.

---

## Accessibility & Style

- WCAG AA: KPI cards are keyboard-navigable (tab + enter/space). Status dropdowns accessible via keyboard. Overdue state conveyed via text/icon label — not colour alone.
- NL Design System tokens for all colours and spacing — no hardcoded hex values (ADR-010).
- All `<style>` blocks MUST use `scoped` attribute (ADR-010).
- SPDX header (`<!-- SPDX-License-Identifier: EUPL-1.2 -->`) on line 1 of every new `.vue` file; `// SPDX-License-Identifier: EUPL-1.2` on line 1 of every new `.js` file (ADR-014).

---

## Seed Data

### Tasks assigned to current user — Dutch examples

```json
[
  {
    "id": "task-001",
    "title": "API-documentatie schrijven voor authenticatie-module",
    "status": "in_progress",
    "priority": "high",
    "project": "project-001",
    "assignedTo": "jan.devries",
    "dueDate": "2026-05-19",
    "completedAt": null,
    "labels": ["documentatie", "backend"]
  },
  {
    "id": "task-002",
    "title": "Pull request reviewen voor klantenportaal",
    "status": "open",
    "priority": "urgent",
    "project": "project-002",
    "assignedTo": "jan.devries",
    "dueDate": "2026-05-21",
    "completedAt": null,
    "labels": ["review"]
  },
  {
    "id": "task-003",
    "title": "Testrapport opstellen voor Sprint 14",
    "status": "open",
    "priority": "normal",
    "project": "project-001",
    "assignedTo": "jan.devries",
    "dueDate": "2026-05-24",
    "completedAt": null,
    "labels": ["testen"]
  },
  {
    "id": "task-004",
    "title": "Installatiehandleiding bijwerken",
    "status": "done",
    "priority": "low",
    "project": "project-003",
    "assignedTo": "jan.devries",
    "dueDate": "2026-05-20",
    "completedAt": "2026-05-20T09:45:00Z",
    "labels": ["documentatie"]
  },
  {
    "id": "task-005",
    "title": "Bugfix: exportfunctie crasht bij lege dataset",
    "status": "open",
    "priority": "urgent",
    "project": "project-002",
    "assignedTo": "jan.devries",
    "dueDate": "2026-05-15",
    "completedAt": null,
    "labels": ["bug", "export"]
  }
]
```

Expected KPI values for seed tasks (today = 2026-05-20):

| KPI | Count | Reason |
|---|---|---|
| Open | 4 | task-001 (in_progress) + task-002, 003, 005 (open) |
| Overdue | 2 | task-001 (dueDate 2026-05-19), task-005 (dueDate 2026-05-15) |
| In progress | 1 | task-001 |
| Completed today | 1 | task-004 (completedAt 2026-05-20) |

My Work grouping for seed data:

- **Overdue**: task-005 (urgent), task-001 (high) — sorted by priority
- **Due this week**: task-002 (urgent), task-003 (normal) — sorted by priority
- **Everything else**: *(none in this seed — all tasks have due dates within range)*

### Recent projects — Dutch examples

```json
[
  {
    "id": "project-001",
    "title": "Gemeenteportaal Heerhugowaard",
    "color": "#0070BB",
    "icon": "🏛️",
    "status": "active",
    "members": ["jan.devries", "sophie.bakker", "ali.hassan"],
    "updatedAt": "2026-05-20T08:30:00Z"
  },
  {
    "id": "project-002",
    "title": "Klantenportaal Renovatie",
    "color": "#E85D04",
    "icon": "🔧",
    "status": "active",
    "members": ["jan.devries", "mila.jansen"],
    "updatedAt": "2026-05-19T16:00:00Z"
  },
  {
    "id": "project-003",
    "title": "Interne Documentatie 2026",
    "color": "#52B788",
    "icon": "📄",
    "status": "active",
    "members": ["jan.devries", "sophie.bakker"],
    "updatedAt": "2026-05-18T11:00:00Z"
  }
]
```
