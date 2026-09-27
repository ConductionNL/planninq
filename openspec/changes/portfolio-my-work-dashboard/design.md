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
