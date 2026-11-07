# Design: my work, task figures on the dashboard, and my own project order

## Context

Read at development `de35541`:

- `src/manifest.json:72-140` Dashboard page: three `stat` widgets on the `project` schema
  (active, all, archived; the "Projects I am in" widget has no filter) and a `project-panels`
  widget rendered by `src/components/DashboardPanels.vue` (registered in `src/main.js:71`).
- `src/components/DashboardPanels.vue:102-106` `recentProjects` filters active projects and takes
  the first five in store order.
- Menu entries: Dashboard, Boards, Projects, Timesheet, and footer items; no My work.
- `src/store/projects.js:775` `fetchTasks(projectId)` is per project; task reads are scoped by Open
  Register to the user's projects, so a query without `project` already stays inside them.
- `openspec/specs/dashboard-my-work.md` requirements "Personal Dashboard", "My Work View",
  "Dashboard Empty State"; it names a change that does not exist.
- hydra ADR-049: manifest `stat` and `stats-block` widgets take token-resolved filters (`@me`,
  `@today`); custom widgets need a justification (gate 29, custom-widget ratchet).
- `openspec/architecture/adr-001-information-architecture.md` rule 6: Mijn werk is the landing page.

## Goals / non-goals

Goals: the main spec's My Work view and task KPIs, a correct member count, a personal project
order. Non-goals: the menu restructure, a cross-project board, the timer.

## Decisions

### Decision 1: My tasks is a custom page over one query

`src/views/MyWork.vue` (route `/my-tasks`, registered in `src/registry.js`) loads open tasks where
`assignedTo` is the current user, plus those whose `sharedWith` contains them, merges them, and
groups them with a pure helper into Overdue, Due this week and Later, each sorted urgent to low.
A status select per row PATCHes in place; the title opens TaskDetail and Back returns here. The
page is a custom page because the grouping and inline status do not fit a list widget; it is not a
dashboard widget.

### Decision 2: task KPIs are declarative widgets

Four manifest `stat` widgets on the `task` schema with `@me` and `@today` tokens: open
(`assignedTo: @me`, status open or in_progress), overdue (`dueDate` before `@today`, not done),
in progress, and completed today (`completedAt` on `@today`). Each `route` opens My tasks with a
`?group=` filter. "Projects I am in" gets `members` contains `@me`. Alternative: count in
`DashboardPanels`. Rejected by ADR-049 while the stat grammar can express it.

### Decision 3: pinned projects in the user's settings

"My projects" gets a pin toggle and "Move up" and "Move down" per row. The order is a user
preference `dashboardProjectOrder` (list of project ids) saved through the user settings endpoint;
pinned projects come first in that order, then the rest as today.

### Decision 4: the menu entry until the five menus land

A menu entry "My tasks" (order 15) sits under Dashboard. When `adopt-five-menu-navigation-ia`
builds the Mijn werk group, it moves there without a URL change.

## Risks / trade-offs

- [The overdue and completed-today filters need date comparison in the stat grammar] -> if the
  grammar lacks a before-today operator, the two figures move into one `stats-block` computed by an
  Open Register calculation, never into a custom widget.

## Open questions

- Does the `stat` widget filter grammar support "before `@today`" and "on `@today`" for date fields
  and "contains" for arrays? Task 2.1 checks against nextcloud-vue before choosing.

## Amendments at build time (2026-09-29)

- My tasks reads the task collection once, paged, and keeps the tasks assigned to or shared with the user in the browser (`fetchMyTasks`). `sharedWith` is an array, and an array filter on OpenRegister's object list is not portable across databases (the same reason `fetchProjects` filters members in the browser). For an admin this reads every task the admin can see.
- The stat grammar was checked against OpenRegister's aggregation runner (task 2.1): `ne`, `in`, `notIn`, `gt`, `gte`, `lt`, `lte`, and a filter on an array-typed field (`members: @me`) is matched as any-overlap by the PHP fallback. So all four figures and "Projects I am in" are declarative `stat` widgets: open = `status notIn [done, cancelled]`, overdue = that plus `dueDate lt @today`, in progress = `status in_progress`, completed today = `status done` and `completedAt gte @today`.
- The figures count the tasks the user is responsible for (`assignedTo: @me`). The aggregation cannot say "assigned to me OR shared with me" in one filter. So a figure opens My tasks narrowed to exactly what it counts (`?group=open|overdue|in_progress|completed_today`, responsible only), and the unfiltered page lists shared tasks too. A "Show all my tasks" button clears the figure.
- Back on a task opened from My tasks returns to My tasks (`?from=my-tasks` on the task route).
- "My projects" rows became a button (open the board) plus an actions menu (Pin to the top or Unpin, Move up, Move down), because a pin control inside a row that is itself a button nests two interactive elements. Pinned projects are all listed, then the rest up to five in all.
- Copy lands in all 36 locales the app ships, not only en and nl (task 4.1).
