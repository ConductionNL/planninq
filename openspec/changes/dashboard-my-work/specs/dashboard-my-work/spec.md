# Delta Spec: dashboard-my-work

**Capability:** dashboard-my-work
**Change ID:** dashboard-my-work
**Delta type:** implementation
**Base spec:** [openspec/specs/dashboard-my-work.md](../../../../specs/dashboard-my-work.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the Dashboard and My Work views. The base spec (`openspec/specs/dashboard-my-work.md`) defines all business requirements, scenarios, user stories, and acceptance criteria. The delta below documents:

1. KPI card component anatomy and reuse contract.
2. Data fetching strategy (3 parallel calls, client-side KPI derivation).
3. My Work grouping and sorting logic.
4. Filter URL parameter integration.
5. CnEmptyState patterns for all empty states.
6. Inline status update interaction pattern.
7. Responsive layout constraints.
8. i18n requirements.
9. Constraints introduced by the thin-client + `useObjectStore` architecture.

All base spec requirements are implemented as-is. No base spec requirement is modified or removed.

---

## ADDED Requirements

### Requirement: KPI Card Component [MVP]

A reusable `KpiCard.vue` component MUST be created for the dashboard KPI section.

#### Scenario: KPI card renders correctly
- GIVEN the dashboard loads and task data is available
- WHEN `KpiCard` is rendered with a label, count, icon, color, and filterValue
- THEN the card MUST display the label, count (as a large number), and icon
- AND the card border or background accent MUST use the provided CSS variable color
- AND the card MUST be keyboard-focusable and show a focus ring

#### Scenario: KPI card click navigates to My Work with filter
- GIVEN the user clicks a KPI card
- WHEN the `click` event fires
- THEN the parent `DashboardView` MUST navigate to `/my-work?filter=<filterValue>`
- AND the My Work view MUST apply the filter immediately on mount

#### Scenario: KPI card loading state
- GIVEN the dashboard is loading (API calls in flight)
- WHEN `KpiCard` is rendered
- THEN it MUST show a skeleton loader in place of the count
- AND the card MUST NOT be clickable during loading

---

### Requirement: Data Fetching Strategy [MVP]

The dashboard MUST use 3 parallel API calls via `Promise.all` on component mount.

#### Scenario: Parallel fetch on dashboard mount
- GIVEN the user navigates to `/`
- WHEN `DashboardView` mounts
- THEN the component MUST fire all 3 API calls simultaneously using `Promise.all`:
  1. `useObjectStore('planix', 'task').getObjects({ assignedTo: currentUser })` — all assigned tasks
  2. `useObjectStore('planix', 'project').getObjects({ members: currentUser })` — all member projects
  3. `useObjectStore('planix', 'task').getObjects({ assignedTo: currentUser, dueDateBefore: sevenDaysFromNow })` — tasks due this week
- AND a skeleton loading state MUST be shown until all 3 calls resolve
- AND if any call fails, an error banner MUST appear with "Failed to load dashboard data" and a Retry button

#### Scenario: KPI counts derived client-side
- GIVEN call 1 has resolved with a list of tasks
- WHEN KPI counts are computed
- THEN the following client-side filters MUST be applied to the task list:
  - Open tasks: `status in ['open', 'in_progress']`
  - Overdue: `dueDate < today AND status NOT IN ['done', 'cancelled']`
  - In progress: `status === 'in_progress'`
  - Completed today: `completedAt exists AND isToday(completedAt)`
- AND these counts MUST update reactively if a task status is updated inline (e.g., from My Work)

---

### Requirement: My Work List Layout [MVP]

The `MyWorkView` MUST group and render tasks in a specific layout.

#### Scenario: My Work groups and sort order
- GIVEN the user opens My Work
- WHEN task data loads
- THEN tasks MUST be divided into three groups in this order:
  1. **Overdue** — `dueDate < today AND status NOT IN ['done', 'cancelled']`
  2. **Due This Week** — `dueDate >= today AND dueDate <= today+7 AND status NOT IN ['done', 'cancelled']`
  3. **Everything Else** — all remaining non-done, non-cancelled tasks
- AND within each group tasks MUST be sorted by priority: urgent (0) → high (1) → normal (2) → low (3)
- AND done and cancelled tasks MUST NOT appear in any group

#### Scenario: My Work task row fields
- GIVEN a task row in My Work
- WHEN `MyWorkTaskRow` renders
- THEN it MUST show (left to right): priority dot, task title (clickable), project badge, due date chip, status indicator
- AND the priority dot MUST use CSS variables: urgent=`--color-error`, high=`--color-warning`, normal=transparent, low=`--color-info`
- AND the project badge MUST show the project color defined in the project object
- AND the due date chip MUST follow the same overdue/today/future logic as `TaskCard` from the `tasks` change

#### Scenario: Group headers
- GIVEN My Work is rendered with tasks in multiple groups
- WHEN the view renders
- THEN each group header MUST show the group name, task count, and a collapse/expand toggle
- AND the Overdue group header MUST use `--color-error` for its label color
- AND the Due This Week group header MUST use `--color-warning` for its label color
- AND an empty group MUST be hidden (not rendered as an empty section)

---

### Requirement: Status Update Inline [MVP]

The My Work view MUST allow status updates without full navigation.

#### Scenario: Inline status dropdown
- GIVEN a task row in My Work
- WHEN the user clicks the status indicator chip
- THEN a dropdown MUST appear with all available status options: Open, In Progress, Blocked, Done, Cancelled
- AND each option MUST display the `TaskStatusBadge` styling for that status
- AND selecting a status MUST call `tasksStore.updateStatus(taskId, newStatus)` immediately
- AND the row MUST update reactively after the store action resolves

#### Scenario: Task disappears from group after status update
- GIVEN the user marks an Overdue task as Done
- WHEN `updateStatus` resolves successfully
- THEN the task MUST be removed from the Overdue group reactively
- AND if the group becomes empty, the group section MUST be hidden
- AND a brief success indicator (toast or row highlight) MUST confirm the update

#### Scenario: Status update error handling
- GIVEN the user selects a new status
- WHEN the `updateStatus` store action fails (network error)
- THEN the row MUST revert to the previous status
- AND an error toast MUST appear: "Failed to update task status"

---

### Requirement: Filter URL Integration [MVP]

My Work MUST read and apply filter query parameters from the URL.

#### Scenario: Apply filter from KPI card navigation
- GIVEN the user clicks the "Overdue" KPI card on the dashboard
- WHEN the router navigates to `/my-work?filter=overdue`
- THEN the My Work view MUST scroll to the Overdue group on mount
- AND the Overdue group MUST be briefly highlighted (CSS animation, 2 seconds)

#### Scenario: Filter param for in_progress
- GIVEN the user clicks the "In Progress" KPI card
- WHEN the router navigates to `/my-work?filter=in_progress`
- THEN the My Work view MUST scroll to the Everything Else group
- AND within that group, tasks with `status === 'in_progress'` MUST be visually highlighted

#### Scenario: Filter param for completed_today
- GIVEN the user clicks the "Completed Today" KPI card
- WHEN the router navigates to `/my-work?filter=completed_today`
- THEN the My Work view MUST render an additional ephemeral group at the top: "Completed Today"
- AND this group MUST show tasks with `status === 'done'` AND `isToday(completedAt)`
- AND this group MUST be read-only (no inline status update available)

#### Scenario: No filter param
- GIVEN the user navigates to `/my-work` without a `?filter` param
- WHEN the view mounts
- THEN all three standard groups MUST render normally with no scroll or highlight behavior

---

### Requirement: Empty States [MVP]

All empty states MUST use the `CnEmptyState` component from `@conduction/nextcloud-vue`.

#### Scenario: Dashboard no-projects empty state
- GIVEN the user is authenticated and is not a member of any project
- WHEN `DashboardRecentProjects` renders
- THEN it MUST render `CnEmptyState` with:
  - Title: `t('planix', 'No projects yet')`
  - Description: `t('planix', 'Create your first project to get started')`
  - Action button: `t('planix', 'Create project')` — navigates to `/projects/new`
- AND the KPI cards MUST still render with all counts at 0

#### Scenario: Dashboard due-this-week empty state
- GIVEN the user has no tasks due within 7 days
- WHEN `DashboardDueThisWeek` renders
- THEN it MUST render `CnEmptyState` with:
  - Title: `t('planix', 'No tasks due this week')`
  - No action button
- AND the Recent Projects section MUST still render normally

#### Scenario: My Work empty state
- GIVEN the user has no tasks assigned to them
- WHEN `MyWorkView` renders
- THEN it MUST render `CnEmptyState` with:
  - Title: `t('planix', 'No tasks assigned to you')`
  - Description: `t('planix', 'Tasks assigned to you will appear here')`
  - Action button: `t('planix', 'Browse projects')` — navigates to `/projects`

---

### Requirement: Responsive Layout [MVP]

The dashboard MUST be usable on tablet-sized screens (minimum 768 px wide).

#### Scenario: KPI cards responsive grid
- GIVEN the viewport is >= 1024 px
- WHEN the dashboard renders
- THEN KPI cards MUST be displayed in a 4-column grid
- GIVEN the viewport is 768–1023 px
- THEN KPI cards MUST be displayed in a 2-column grid
- GIVEN the viewport is < 768 px
- THEN KPI cards MUST be displayed in a 1-column stack

#### Scenario: Two-column dashboard layout
- GIVEN the viewport is >= 1024 px
- WHEN the dashboard renders the main content area
- THEN Recent Projects and Due This Week MUST be displayed side by side (approximately 60/40 split)
- GIVEN the viewport is < 1024 px
- THEN they MUST stack vertically (Recent Projects above Due This Week)

---

### Requirement: i18n Coverage [MVP]

All user-visible strings in Dashboard and My Work MUST be internationalised.

#### Scenario: English strings present
- GIVEN the `l10n/en.json` file
- WHEN inspected
- THEN all strings listed in the i18n inventory in `design.md` MUST be present as keys
- AND every Vue template string MUST use `t('planix', '...')` — no hardcoded English strings

#### Scenario: Dutch translations present
- GIVEN the `l10n/nl.json` file
- WHEN compared to `l10n/en.json`
- THEN every key added by this change in `en.json` MUST also exist in `nl.json`
