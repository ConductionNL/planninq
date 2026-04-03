# Test Plan: dashboard-my-work

## Test Cases

### TC-1: Dashboard KPI cards render with correct counts
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-kpi-counts-derived-client-side`
- **type**: functional
- **persona**: any authenticated Nextcloud user with assigned tasks
- **preconditions**: User has tasks in various states: 2 open, 1 in_progress, 1 overdue (past due date, not done), 1 completed today; user is a project member
- **steps**: Navigate to `/apps/planix/` (dashboard)
- **expected result**: Four KPI cards render showing: Open=3 (open + in_progress), Overdue=1, In Progress=1, Completed Today=1; counts match the user's actual task data
- **test command**: /test-functional

### TC-2: KPI card click navigates to My Work with filter
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-kpi-card-click-navigates-to-my-work-with-filter`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Dashboard is loaded with KPI cards visible
- **steps**: Click the "Overdue" KPI card
- **expected result**: Router navigates to `/my-work?filter=overdue`; My Work view loads with the Overdue group scrolled into view and briefly highlighted (2-second CSS animation)
- **test command**: /test-functional

### TC-3: KPI card loading state shows skeleton
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-kpi-card-loading-state`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: API response is delayed
- **steps**: Navigate to the dashboard with a slow API
- **expected result**: KPI cards show skeleton loaders in place of counts; cards are not clickable during loading
- **test command**: /test-functional

### TC-4: Dashboard parallel fetch — error banner shown on failure
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-parallel-fetch-on-dashboard-mount`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: One of the 3 API calls returns an error
- **steps**: Navigate to the dashboard when the task fetch API fails
- **expected result**: Error banner is shown with "Failed to load dashboard data" and a Retry button; skeleton loading state is visible while calls are in progress
- **test command**: /test-functional

### TC-5: My Work groups tasks correctly
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-my-work-groups-and-sort-order`
- **type**: functional
- **persona**: any authenticated Nextcloud user with assigned tasks
- **preconditions**: User has: 1 overdue task (past due, status open), 1 task due in 3 days (status open), 1 task with no due date (status open); done/cancelled tasks also exist
- **steps**: Navigate to `/apps/planix/my-work`
- **expected result**: Three groups in order: Overdue (task with past due date), Due This Week (task due in 3 days), Everything Else (task with no due date); done and cancelled tasks do NOT appear; within each group tasks sorted urgent→high→normal→low
- **test command**: /test-functional

### TC-6: My Work task row fields render correctly
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-my-work-task-row-fields`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User has at least one assigned task with a project, due date, priority "urgent", and status "in_progress"
- **steps**: Navigate to My Work; inspect a task row
- **expected result**: Row shows (left to right): priority dot (urgent=`--color-error`), task title (clickable link to `/tasks/:id`), project badge (in project color), due date chip (overdue/today/future styling), status indicator
- **test command**: /test-functional

### TC-7: Group headers show count and collapse/expand
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-group-headers`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: My Work has tasks in Overdue and Due This Week groups
- **steps**: View My Work; observe group headers; click a group collapse toggle
- **expected result**: Each group header shows group name, task count, and collapse/expand toggle; Overdue header uses `--color-error`; Due This Week header uses `--color-warning`; empty groups are hidden entirely
- **test command**: /test-functional

### TC-8: Inline status update changes task status without navigation
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-inline-status-dropdown`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: My Work is loaded with at least one task visible
- **steps**: Click the status indicator chip on a task row; select a different status from the dropdown
- **expected result**: Dropdown shows all statuses: Open, In Progress, Blocked, Done, Cancelled; each option shows `TaskStatusBadge` styling; selecting a status calls `tasksStore.updateStatus` immediately; row updates reactively; user stays on My Work view
- **test command**: /test-functional

### TC-9: Task disappears from group after marking as done
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-task-disappears-from-group-after-status-update`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: My Work has an Overdue task
- **steps**: Mark the overdue task as Done using the inline status dropdown
- **expected result**: Task is removed from the Overdue group reactively; if Overdue group becomes empty, the section is hidden; brief success indication (toast or row highlight)
- **test command**: /test-functional

### TC-10: Status update error reverts row
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-status-update-error-handling`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: My Work is loaded; API is set to fail on `updateStatus`
- **steps**: Select a new status from the inline dropdown
- **expected result**: Row reverts to the previous status; error toast "Failed to update task status" appears
- **test command**: /test-functional

### TC-11: Filter param — completed_today renders ephemeral group
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-filter-param-for-completed_today`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User has tasks completed today (completedAt is today's date); user clicks "Completed Today" KPI card
- **steps**: Navigate to `/my-work?filter=completed_today`
- **expected result**: An additional "Completed Today" group appears at the top of the list; group shows done tasks with today's `completedAt`; the group is read-only (no inline status dropdown)
- **test command**: /test-functional

### TC-12: Dashboard empty state — no projects
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-dashboard-no-projects-empty-state`
- **type**: functional
- **persona**: newly created Nextcloud user with zero projects
- **preconditions**: User is not a member of any project
- **steps**: Navigate to the dashboard
- **expected result**: `CnEmptyState` renders in `DashboardRecentProjects` with title "No projects yet", description "Create your first project to get started", and action button "Create project" (navigates to `/projects/new`); KPI cards still render with counts at 0
- **test command**: /test-functional

### TC-13: Dashboard empty state — no tasks due this week
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-dashboard-due-this-week-empty-state`
- **type**: functional
- **persona**: any authenticated Nextcloud user with no upcoming due dates
- **preconditions**: User has no tasks due within 7 days
- **steps**: Navigate to the dashboard
- **expected result**: `CnEmptyState` renders in `DashboardDueThisWeek` with title "No tasks due this week" and no action button; Recent Projects section still renders
- **test command**: /test-functional

### TC-14: My Work empty state — no assigned tasks
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-my-work-empty-state`
- **type**: functional
- **persona**: newly created Nextcloud user with zero assigned tasks
- **preconditions**: User has no tasks assigned to them
- **steps**: Navigate to `/apps/planix/my-work`
- **expected result**: `CnEmptyState` renders with title "No tasks assigned to you", description "Tasks assigned to you will appear here", and action button "Browse projects" (navigates to `/projects`)
- **test command**: /test-functional

### TC-15: KPI cards responsive grid — 4 → 2 → 1 column
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-kpi-cards-responsive-grid`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Dashboard is loaded
- **steps**: Resize the viewport to >= 1024 px; then to 768–1023 px; then to < 768 px
- **expected result**: At ≥ 1024 px: 4-column grid; at 768–1023 px: 2-column grid; at < 768 px: 1-column stack
- **test command**: /test-functional

### TC-16: Dashboard two-column layout stacks on small viewport
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-two-column-dashboard-layout`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Dashboard is loaded with projects and due tasks
- **steps**: View at ≥ 1024 px; view at < 1024 px
- **expected result**: At ≥ 1024 px: Recent Projects and Due This Week are side by side (60/40 split); at < 1024 px: they stack vertically (Recent Projects above Due This Week)
- **test command**: /test-functional

### TC-17: KPI counts update reactively after inline status change
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#scenario-kpi-counts-derived-client-side`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Dashboard is loaded; user has tasks visible in KPI counts
- **steps**: Navigate to My Work via a KPI card click; mark one "In Progress" task as "Done" using the inline dropdown; navigate back to the dashboard
- **expected result**: The "In Progress" KPI count is decremented by 1; the "Completed Today" count is incremented by 1; counts reflect the change without a full page reload
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| KPI Card Component [MVP] | Render, click, loading | TC-1, TC-2, TC-3 |
| Data Fetching Strategy [MVP] | Parallel fetch, error banner, KPI derivation | TC-4, TC-1 |
| My Work List Layout [MVP] | Groups, sort, row fields, group headers | TC-5, TC-6, TC-7 |
| Status Update Inline [MVP] | Dropdown, group removal, error revert | TC-8, TC-9, TC-10 |
| Filter URL Integration [MVP] | KPI→filter, in_progress, completed_today, no-filter | TC-2, TC-11 |
| Empty States [MVP] | No projects, no due-this-week, no tasks | TC-12, TC-13, TC-14 |
| Responsive Layout [MVP] | KPI grid breakpoints, two-column stack | TC-15, TC-16 |
| Reactive KPI updates | After inline status change | TC-17 |
| i18n Coverage [MVP] | Not covered in browser test (see Out of Scope) | — |

## Out of Scope

- i18n translation completeness — verified via build-time linting
- Recent projects progress bar rendering — visual regression test recommended; functional test confirms basic rendering in TC-1
- `Promise.all` parallelism verification — not easily assertable from a browser test; verified via code review / network waterfall inspection
