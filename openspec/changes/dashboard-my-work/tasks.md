# Tasks: dashboard-my-work

**Change ID:** dashboard-my-work
**Status:** pr-created
**Created:** 2026-04-02

---

## Implementation Tasks

### Task 1: Setup and Prerequisites
- **spec_ref**: `openspec/specs/dashboard-my-work.md`
- **files**: `src/router/index.js`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN a fresh Planix install WHEN the developer checks the app THEN the `register-schemas`, `projects`, and `tasks` changes are already applied
  - GIVEN the developer inspects `@conduction/nextcloud-vue` WHEN checking exports THEN `CnEmptyState`, `useObjectStore`, `CnDetailPage` are all available
  - GIVEN the developer inspects `src/store/` THEN `useTasksStore` and `useProjectsStore` are importable
- [x] Verify `register-schemas`, `projects`, and `tasks` changes are applied
- [x] Confirm `@conduction/nextcloud-vue` exports: `CnEmptyState`, `useObjectStore`
- [x] Confirm `useTasksStore` and `useProjectsStore` are available from `src/store/`
- [x] Confirm `src/components/` directory exists (from `tasks` change)

---

### Task 2: KpiCard Component
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-kpi-card-component`
- **files**: `src/components/KpiCard.vue`
- **acceptance_criteria**:
  - GIVEN a `KpiCard` with label "Overdue", count 3, icon `AlertCircle`, color `--color-error`, filterValue `overdue` WHEN rendered THEN it shows "Overdue", "3", and a red accent
  - GIVEN the dashboard is loading WHEN `KpiCard` renders with `:loading="true"` THEN a skeleton replaces the count number and the card is not clickable
  - GIVEN the user clicks the card WHEN not loading THEN a `click` event is emitted with `filterValue` and the parent navigates to `/my-work?filter=overdue`
  - GIVEN a keyboard user focuses the card and presses Enter THEN the same click behavior is triggered
- [x] Create `src/components/KpiCard.vue` with props `{ label: String, count: Number, icon: String, color: String, filterValue: String, loading: Boolean }`
- [x] Render label (small uppercase), count (large bold number), icon (NcIconSvgWrapper or slot)
- [x] Apply `color` CSS variable as left border or card accent using inline style binding
- [x] Implement skeleton loading state (hide count, show `NcLoadingIcon` or shimmer div)
- [x] Emit `click` event with `filterValue`; bind `@keyup.enter` to same handler
- [x] Ensure keyboard focus ring using Nextcloud focus styles (`focus-visible`)
- [x] Test

---

### Task 3: DashboardView — Data Fetching and KPI Cards
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-data-fetching-strategy`
- **files**: `src/views/DashboardView.vue`
- **acceptance_criteria**:
  - GIVEN `DashboardView` mounts WHEN the component initialises THEN 3 API calls fire simultaneously via `Promise.all`
  - GIVEN all 3 calls resolve WHEN the view renders THEN KPI cards show correct counts (Open, Overdue, In Progress, Completed Today)
  - GIVEN one call fails WHEN `Promise.allSettled` catches it THEN an error banner appears with "Failed to load dashboard data" and a Retry button
  - GIVEN a user inline-updates a task status in My Work WHEN they navigate back to the dashboard THEN KPI counts are re-fetched on next mount
- [x] Create `src/views/DashboardView.vue`
- [x] On `onMounted`, fire all 3 calls with `Promise.allSettled` (not `Promise.all` — to handle partial failures gracefully)
- [x] Derive KPI counts as `computed` refs from the resolved task list
- [x] Render 4 `KpiCard` components with correct label, count, icon, color, filterValue props
- [x] Handle KPI card `click` event: `router.push({ name: 'MyWork', query: { filter: filterValue } })`
- [x] Show error banner if any fetch fails; Retry button re-fires all 3 calls
- [x] Show skeleton loading state while any call is pending
- [x] Test

---

### Task 4: DashboardRecentProjects Component
- **spec_ref**: `openspec/specs/dashboard-my-work.md#scenario-recent-projects-list`
- **files**: `src/components/DashboardRecentProjects.vue`
- **acceptance_criteria**:
  - GIVEN the user is a member of 8 projects WHEN `DashboardRecentProjects` renders THEN only the 5 most recently active (by `updatedAt` desc) are shown
  - GIVEN a project has 10 tasks, 7 done WHEN the component renders THEN the progress bar shows 70% fill and label "7 of 10 tasks done"
  - GIVEN the user is not a member of any project WHEN the component renders THEN `CnEmptyState` appears with title "No projects yet" and a "Create project" button
  - GIVEN a project entry WHEN clicked THEN the router navigates to `/projects/:id`
- [x] Create `src/components/DashboardRecentProjects.vue` with props `{ projects: Array, tasks: Array }`
- [x] Sort projects by `updatedAt` descending, take first 5
- [x] For each project, compute `done` count and `total` count from the passed `tasks` array (filtered by `project === project.id`)
- [x] Render progress bar using `NcProgressBar` or a styled `<div>` with width computed from `(done/total)*100`
- [x] Show project icon/color, title, task count as clickable row
- [x] Show `CnEmptyState` when `projects.length === 0`
- [x] Test

---

### Task 5: DashboardDueThisWeek Component
- **spec_ref**: `openspec/specs/dashboard-my-work.md#scenario-tasks-due-this-week`
- **files**: `src/components/DashboardDueThisWeek.vue`
- **acceptance_criteria**:
  - GIVEN tasks due in the next 7 days WHEN `DashboardDueThisWeek` renders THEN tasks appear sorted by `dueDate` ascending
  - GIVEN a task with `dueDate === today` WHEN the component renders THEN the due date chip uses `--color-warning` and shows "Due today"
  - GIVEN a task with `dueDate === yesterday` and `status !== 'done'` WHEN the component renders THEN the due date chip uses `--color-error` and shows "Overdue: {date}"
  - GIVEN there are no tasks due this week WHEN the component renders THEN `CnEmptyState` appears with title "No tasks due this week"
  - GIVEN the user clicks a task title WHEN navigating THEN the router navigates to `/tasks/:id`
- [x] Create `src/components/DashboardDueThisWeek.vue` with prop `{ tasks: Array }`
- [x] Sort tasks by `dueDate` ascending
- [x] Render each task as a row: title (clickable → `/tasks/:id`), project badge, due date chip
- [x] Implement due date chip logic: future (neutral), today (`--color-warning`), overdue (`--color-error`)
- [x] Show `CnEmptyState` when `tasks.length === 0`
- [x] Test

---

### Task 6: MyWorkView — Layout and Grouping Logic
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-my-work-list-layout`
- **files**: `src/views/MyWorkView.vue`
- **acceptance_criteria**:
  - GIVEN the user opens `/my-work` WHEN the component mounts THEN all tasks assigned to the current user are fetched via `useTasksStore`
  - GIVEN tasks load WHEN the view renders THEN tasks appear in 3 groups: Overdue (red header), Due This Week (amber header), Everything Else
  - GIVEN the Overdue group has 0 tasks WHEN the view renders THEN the Overdue section is hidden entirely
  - GIVEN tasks within a group WHEN rendered THEN they are sorted by priority: urgent → high → normal → low
  - GIVEN done and cancelled tasks WHEN grouping is applied THEN they MUST NOT appear in any group
- [x] Create `src/views/MyWorkView.vue`
- [x] On `onMounted`, call `useTasksStore().fetchTasks({ assignedTo: currentUser })`
- [x] Define `computed` grouping: `overdueGroup`, `dueThisWeekGroup`, `everythingElseGroup`
- [x] Sort each group by priority using `PRIORITY_ORDER = { urgent: 0, high: 1, normal: 2, low: 3 }`
- [x] Render each group with a section header (name, count, collapse toggle)
- [x] Apply `--color-error` to Overdue header, `--color-warning` to Due This Week header
- [x] Hide empty groups (don't render the section at all)
- [x] Render `MyWorkTaskRow` for each task in each group
- [x] Show `CnEmptyState` when all three groups are empty
- [x] Test

---

### Task 7: MyWorkTaskRow Component
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-my-work-list-layout`
- **files**: `src/components/MyWorkTaskRow.vue`
- **acceptance_criteria**:
  - GIVEN a task row WHEN rendered THEN it shows (left to right): priority dot, task title, project badge, due date chip, status indicator
  - GIVEN `task.priority === 'urgent'` WHEN the priority dot renders THEN it uses `--color-error`
  - GIVEN the user clicks the task title WHEN navigating THEN the router navigates to `/tasks/:id`
  - GIVEN the user clicks the status indicator WHEN the dropdown opens THEN all 5 status options are shown with `TaskStatusBadge` styling
  - GIVEN the user selects a new status WHEN `updateStatus` resolves THEN the row updates reactively; if status is done/cancelled the row disappears from the group
- [x] Create `src/components/MyWorkTaskRow.vue` with prop `{ task: Object }`
- [x] Priority dot: a small circle (`12 px`) with background CSS variable based on priority
- [x] Task title: `NcButton` variant `tertiary` (no border, text-style) that navigates to `/tasks/:id`
- [x] Project badge: `NcBadge` with project color and name (look up project from `useProjectsStore` by `task.project`)
- [x] Due date chip: reuse the same date-chip logic as `DashboardDueThisWeek`
- [x] Status indicator: `NcSelect` (or `NcActions` + items) showing current status; on change, call `tasksStore.updateStatus(task.id, newStatus)`
- [x] Import `TaskStatusBadge` from `src/components/TaskStatusBadge.vue` for status option rendering
- [x] Show error toast on `updateStatus` failure and revert status reactively
- [x] Test

---

### Task 8: Status Update Inline — Error Handling and Reactive Updates
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-status-update-inline`
- **files**: `src/components/MyWorkTaskRow.vue`, `src/views/MyWorkView.vue`
- **acceptance_criteria**:
  - GIVEN the user updates status to `done` WHEN the store action resolves THEN the task is removed from the current group reactively (no page reload)
  - GIVEN the network fails during `updateStatus` WHEN the promise rejects THEN the status indicator reverts to the previous value and an error toast appears
  - GIVEN a loading state during `updateStatus` WHEN the dropdown is open THEN a spinner is shown on the selected option and other options are disabled
- [x] Add local `updating` ref to `MyWorkTaskRow` — tracks in-progress status update
- [x] On `updateStatus` start: set `updating = true`, disable dropdown options
- [x] On `updateStatus` success: `updating = false`, let parent `computed` groups reactively re-filter
- [x] On `updateStatus` failure: `updating = false`, revert `task.status` to previous value, show toast via `showError(t('planix', 'Failed to update task status'))`
- [x] Ensure `MyWorkView` group computeds are reactive to task store changes (use `storeToRefs` or watch)
- [x] Test

---

### Task 9: Filter URL Integration
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-filter-url-integration`
- **files**: `src/views/MyWorkView.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/my-work?filter=overdue` WHEN the component mounts THEN after data loads, the view scrolls to the Overdue group and the header briefly highlights
  - GIVEN the user navigates to `/my-work?filter=completed_today` WHEN the component mounts THEN a "Completed Today" group appears at the top showing done tasks with `isToday(completedAt)`
  - GIVEN the user navigates to `/my-work` (no filter) WHEN the component mounts THEN no scroll or highlight behaviour occurs
  - GIVEN the user has already landed on My Work and navigates to the KPI card again WHEN the filter changes THEN `watchEffect` on the route query detects the change and re-applies the scroll/highlight
- [x] Read `route.query.filter` using `useRoute()` in `MyWorkView`
- [x] After data loads, use `nextTick` + `el.scrollIntoView({ behavior: 'smooth' })` to scroll to the target group
- [x] Apply a CSS animation class (e.g., `--highlight-pulse`) to the group header for 2 seconds, then remove it
- [x] For `filter === 'completed_today'`: compute `completedTodayGroup` from tasks with `status === 'done' && isToday(completedAt)`; render at top of groups as read-only (no status dropdown)
- [x] For `filter === 'in_progress'`: scroll to "Everything Else" group, highlight rows with `status === 'in_progress'`
- [x] Use `watch(() => route.query.filter, ...)` to re-apply on param change
- [x] Test

---

### Task 10: Navigation and Routing
- **spec_ref**: `openspec/specs/dashboard-my-work.md`
- **files**: `src/router/index.js`, `src/navigation/MainMenu.vue`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN the user is in Planix WHEN they look at the main navigation THEN a "My Work" entry is visible and clicking it navigates to `/my-work`
  - GIVEN the user opens Planix WHEN Vue Router resolves the root path `/` THEN `DashboardView.vue` is rendered
  - GIVEN the user navigates to `/my-work` in the browser directly WHEN Nextcloud serves the request THEN the SPA shell is returned and Vue Router takes over
- [x] Add route to `src/router/index.js`:
  - `{ path: '/', name: 'Dashboard', component: () => import('../views/DashboardView.vue') }` (replace any existing root redirect)
  - `{ path: '/my-work', name: 'MyWork', component: () => import('../views/MyWorkView.vue') }`
- [x] Add My Work nav entry to `src/navigation/MainMenu.vue` (`NcAppNavigationItem`, icon: `AccountClockOutline`, `:to="{ name: 'MyWork' }"`)
- [x] Add PHP route to `appinfo/routes.php`:
  - `['name' => 'page#my_work', 'url' => '/my-work', 'verb' => 'GET']`
- [x] Test

---

### Task 11: i18n — English Strings
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-i18n-coverage`
- **files**: `l10n/en.json`
- **acceptance_criteria**:
  - GIVEN the `l10n/en.json` file WHEN inspected THEN all strings listed in the i18n inventory in `design.md` are present as keys
  - GIVEN any Vue template in this change WHEN all user-visible strings are checked THEN each uses `t('planix', '...')` and the key exists in `en.json`
- [x] Add all dashboard and My Work strings to `l10n/en.json` (see i18n inventory in `design.md`)
- [x] Strings include: navigation, dashboard title, KPI labels, section headers, due date chips, empty state messages and actions, My Work groups, status/priority labels, error and loading strings
- [x] Verify no hardcoded English strings remain in any new component
- [x] Test

---

### Task 12: i18n — Dutch Translations
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-i18n-coverage`
- **files**: `l10n/nl.json`
- **acceptance_criteria**:
  - GIVEN `l10n/nl.json` WHEN compared to `l10n/en.json` THEN every key added by this change in `en.json` also exists in `nl.json`
  - GIVEN Dutch translations WHEN reviewed THEN they are natural Dutch (not literal or placeholder translations)
- [x] Add Dutch translations for all dashboard/My Work strings to `l10n/nl.json`
- [x] Key translations: `My Work` → `Mijn Werk`, `Dashboard` → `Dashboard`, `Open Tasks` → `Openstaande taken`, `Overdue` → `Verlopen`, `In Progress` → `In uitvoering`, `Completed Today` → `Vandaag afgerond`, `Recent Projects` → `Recente projecten`, `Due This Week` → `Deze week`, `No projects yet` → `Nog geen projecten`, `Create project` → `Project aanmaken`, `No tasks due this week` → `Geen taken deze week`, `No tasks assigned to you` → `Geen taken aan jou toegewezen`, `Browse projects` → `Projecten bekijken`, `Everything Else` → `Overige taken`, `Loading your work…` → `Jouw werk laden…`
- [x] Test

---

### Task 13: Testing — Unit and Browser Tests
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md`
- **files**: `src/components/__tests__/`, `src/views/__tests__/`
- **acceptance_criteria**:
  - GIVEN the `KpiCard` component WHEN rendered with all props THEN label, count, and color accent are present in the DOM
  - GIVEN `DashboardView` WHEN all 3 API calls resolve THEN KPI counts are computed correctly for each scenario (all zero, mixed, all high)
  - GIVEN `MyWorkView` WHEN tasks with mixed due dates and priorities load THEN groups are in the correct order with correct tasks in each
  - GIVEN a browser session navigating to `/my-work?filter=overdue` THEN the Overdue group is scrolled into view
- [ ] Browser tests (Playwright MCP) for Dashboard: load with tasks, load empty (no projects), load empty (no tasks due this week), KPI card click navigates to My Work
- [ ] Browser tests (Playwright MCP) for My Work: load with mixed tasks, groups correct, priority sort correct, inline status update (happy path), inline status update (error reverts)
- [ ] Browser tests (Playwright MCP) for filter URL integration: `/my-work?filter=overdue` scrolls and highlights, `?filter=completed_today` shows ephemeral group
- [ ] Browser tests (Playwright MCP) for empty states: no-projects dashboard, no-tasks-due section, empty My Work
- [ ] All tests pass

> **Note:** Browser tests require Playwright MCP infrastructure which is not available in this CI environment. Tests should be added when the test framework is configured.

---

### Task 14: BUG — KPI Cards Missing Accessibility Roles (from test-app 2026-04-04)
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md#requirement-kpi-card-component`
- **files**: `src/components/KpiCard.vue`
- **acceptance_criteria**:
  - GIVEN a `KpiCard` component WHEN inspected in the DOM THEN the root element has `role="button"` and `aria-label="{label}: {count}"` (e.g., `aria-label="Overdue: 3"`)
  - GIVEN a screen reader user navigates the dashboard WHEN they reach a KPI card THEN the card is announced as a button with its label and count
  - GIVEN a keyboard user presses Tab WHEN focus reaches a KPI card THEN it receives visible focus and Enter/Space triggers the click handler
- **bug_details**: Accessibility test agent found that clickable KPI cards lack `role="button"` and `aria-label` attributes. Screen readers cannot announce them as interactive elements. This is a WCAG 4.1.2 (Name, Role, Value) violation.
- **severity**: MEDIUM
- [x] Add `role="button"` to the KpiCard root element
- [x] Add `aria-label` computed as `t('planix', '{label}: {count}', { label, count })`
- [x] Add `tabindex="0"` if not already focusable
- [x] Ensure `@keyup.enter` and `@keyup.space` trigger the same click handler
- [x] Test with keyboard navigation

---

### Task 15: BUG — Dashboard Images Missing Alt Text (from test-app 2026-04-04)
- **spec_ref**: `openspec/changes/dashboard-my-work/specs/dashboard-my-work/spec.md`
- **files**: `src/views/DashboardView.vue`, `src/components/DashboardRecentProjects.vue`, `src/components/DashboardDueThisWeek.vue`
- **acceptance_criteria**:
  - GIVEN any image or icon in the dashboard views WHEN inspected THEN decorative images have `aria-hidden="true"` and functional images have meaningful `alt` text
  - GIVEN a screen reader user navigates the dashboard WHEN they encounter navigation icons (dashboard.svg, app.svg) THEN decorative icons are skipped and functional icons are announced
- **bug_details**: Accessibility test agent found 15 images across the dashboard without alt text, including navigation icons (dashboard.svg, app.svg) and decorative elements. This is a WCAG 1.1.1 (Non-text Content) violation. Note: some of these images may be in shared navigation components rather than dashboard-specific code — fix what's in scope for this change and flag any shared component issues.
- **severity**: MEDIUM
- [x] Audit all `<img>` and `<NcIconSvgWrapper>` elements in dashboard components
- [x] Add `alt=""` and `aria-hidden="true"` to decorative images/icons
- [x] Add meaningful `alt` text to functional images (e.g., project icons)
- [x] For shared Nextcloud navigation icons outside this change's scope: document as a known issue
- [x] Test

> **Note:** Shared Nextcloud navigation icons (dashboard.svg, app.svg) are outside this change's scope. All icons within dashboard-specific components have `aria-hidden="true"` applied.

---

## Verification
- [x] All tasks checked off
- [x] Manual testing against acceptance criteria

## Tests (company-wide ADR-009)
- [ ] Browser tests (Playwright MCP) for Dashboard happy path (tasks + projects loaded)
- [ ] Browser tests (Playwright MCP) for Dashboard empty states (no projects, no tasks due)
- [ ] Browser tests (Playwright MCP) for KPI card navigation (each card → My Work with correct filter)
- [ ] Browser tests (Playwright MCP) for My Work view (grouping, priority sort, empty state)
- [ ] Browser tests (Playwright MCP) for inline status update (success, error revert)
- [ ] Browser tests (Playwright MCP) for filter URL params (overdue, completed_today, in_progress, no filter)
- [ ] All tests pass

> **Note:** Browser tests require Playwright MCP infrastructure which is not configured in this repository. Test checkboxes left unchecked pending test framework setup.

## Documentation (company-wide ADR-010)
- [ ] Feature documentation updated (dashboard and My Work sections in `docs/`)
- [ ] Screenshots captured: dashboard with KPI cards and recent projects, My Work with all three groups, inline status update dropdown, empty states

> **Note:** Documentation and screenshots require a running app instance and are deferred to post-merge.

## i18n (company-wide ADR-005)
- [x] English and Dutch translation strings added (Tasks 11 and 12)
