# Tasks: Dashboard & My Work

## 1. Router setup

- [ ] 1.1 Register route `/` → `DashboardView.vue` in `src/router/index.js` (make it the default landing page)
- [ ] 1.2 Register route `/my-work` → `MyWorkView.vue` in `src/router/index.js`
- [ ] 1.3 Add `filter` as a recognised query parameter on `/my-work` (e.g. `overdue`, `in_progress`, `open`, `completed_today`)
- [ ] 1.4 Ensure specific routes are declared BEFORE any wildcard `{slug}` route (ADR-003)

## 2. Composable: `useDashboard`

- [ ] 2.1 Create `src/composables/useDashboard.js` — first line: `// SPDX-License-Identifier: EUPL-1.2`
- [ ] 2.2 Implement `fetchDashboardData()` using `Promise.all` to parallelise: `taskStore.fetchMyTasks(currentUser)` and `projectStore.fetchMyProjects({ limit: 5, sort: 'updatedAt:desc' })`
- [ ] 2.3 Expose `loading` (Boolean) and `error` (String | null) refs for consumer components
- [ ] 2.4 Compute `kpis` object: `{ open, overdue, inProgress, completedToday }` — derived from fetched task list, no extra API calls
  - `open`: tasks where `status === 'open' || status === 'in_progress'`
  - `overdue`: tasks where `dueDate < today && status !== 'done'`
  - `inProgress`: tasks where `status === 'in_progress'`
  - `completedToday`: tasks where the date part of `completedAt` equals today
- [ ] 2.5 Compute `recentProjects` (max 5, sorted by `updatedAt` desc)
- [ ] 2.6 Compute `dueThisWeek`: tasks where `dueDate >= today && dueDate <= today + 7 days && status !== 'done'`, sorted by `dueDate` ascending
- [ ] 2.7 Compute `tasksByUrgency`: `{ overdue: Task[], dueThisWeek: Task[], everythingElse: Task[] }` — each array sorted by priority (`urgent → high → normal → low`); done/cancelled tasks excluded from all groups
- [ ] 2.8 Verify all API calls inside stores use `@nextcloud/axios` — NEVER raw `fetch()` (ADR-015)

## 3. KpiCard component

- [ ] 3.1 Create `src/components/KpiCard.vue` — first line: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
- [ ] 3.2 Accept props: `label` (String, required), `count` (Number, required), `filterKey` (String, required — used as `?filter=` query param)
- [ ] 3.3 On click and on Enter/Space keypress: call `router.push('/my-work?filter=' + filterKey)`
- [ ] 3.4 Add `role="button"` and `tabindex="0"` for keyboard accessibility (WCAG AA)
- [ ] 3.5 Use NL Design System CSS custom property tokens for colours and spacing — NO hardcoded hex values (ADR-010)
- [ ] 3.6 Add `<style scoped>` block (ADR-010)
- [ ] 3.7 Import from `@conduction/nextcloud-vue` — NOT `@nextcloud/vue` (ADR-015)
- [ ] 3.8 Wrap label string in `t('planix', '...')` (ADR-007)

## 4. MyWorkTaskRow component

- [ ] 4.1 Create `src/components/MyWorkTaskRow.vue` — first line: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
- [ ] 4.2 Accept prop: `task` (Object, required — Task entity)
- [ ] 4.3 Render project name as a colored badge (use `task.project` to look up project `color` and `title`)
- [ ] 4.4 Render task title as a `<router-link>` to `/tasks/:id`
- [ ] 4.5 Render due date formatted as `D MMM`; apply warning highlight (orange) if due today or tomorrow; apply error highlight (red) if overdue — ALSO show a text label or icon so colour is not the sole indicator (WCAG 1.4.1)
- [ ] 4.6 Render status chip that opens an `NcSelect` dropdown on click (options: `open`, `in_progress`, `blocked`, `done`, `cancelled`) — use `header-actions` slot pattern (ADR-018)
- [ ] 4.7 Render priority indicator (dot or icon) reflecting `task.priority` value
- [ ] 4.8 On status selection: wrap `taskStore.updateTask(task.id, { status })` in `try/catch`; on error show `NcToast` with a generic message and revert the local status value (ADR-015)
- [ ] 4.9 Emit `statusUpdated` event after successful status save so the parent can re-trigger grouping
- [ ] 4.10 All user-visible strings wrapped in `t('planix', '...')` (ADR-007)
- [ ] 4.11 Add `<style scoped>` block (ADR-010)
- [ ] 4.12 Import ALL components used in the template AND list them in `components: {}` (ADR-015)

## 5. DashboardView

- [ ] 5.1 Create `src/views/DashboardView.vue` — first line: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
- [ ] 5.2 Use `CnDashboardPage` as the root element — do NOT wrap it in `NcAppContent` (ADR-017)
- [ ] 5.3 Call `useDashboard().fetchDashboardData()` in `onMounted` (or `created`)
- [ ] 5.4 Render four `KpiCard` components (Open, Overdue, In progress, Completed today) using `kpis` from the composable
- [ ] 5.5 Render "Recent projects" section: iterate `recentProjects`, showing color swatch, icon, title, task count, and a progress bar (done tasks / total tasks per project)
- [ ] 5.6 Render "Due this week" section: iterate `dueThisWeek`, rendering `MyWorkTaskRow` for each task
- [ ] 5.7 Show `CnEmptyState` ("No projects yet" + "Create project" button → `/projects/new`) when `recentProjects` is empty
- [ ] 5.8 Show `CnEmptyState` ("No tasks due this week") when `dueThisWeek` is empty
- [ ] 5.9 Show loading skeleton while `loading === true` (before data arrives)
- [ ] 5.10 Show error empty-content (`NcEmptyContent`) with a "Retry" button if `error` is set
- [ ] 5.11 Import ALL components used in the template AND list them in `components: {}` (ADR-015)
- [ ] 5.12 All user-visible strings wrapped in `t('planix', '...')` (ADR-007)
- [ ] 5.13 Add `<style scoped>` block (ADR-010)

## 6. MyWorkView

- [ ] 6.1 Create `src/views/MyWorkView.vue` — first line: `<!-- SPDX-License-Identifier: EUPL-1.2 -->`
- [ ] 6.2 Use `CnIndexPage` as the root element — do NOT wrap it in `NcAppContent` (ADR-017)
- [ ] 6.3 Call `useDashboard().fetchDashboardData()` in `onMounted` (share composable instance with dashboard if already cached)
- [ ] 6.4 Read `route.query.filter` on mount; scroll to or visually highlight the corresponding group section
- [ ] 6.5 Render "Overdue" section heading with danger color token; render its `MyWorkTaskRow` list
- [ ] 6.6 Render "Due this week" section heading; render its `MyWorkTaskRow` list
- [ ] 6.7 Render "Everything else" section heading; render its `MyWorkTaskRow` list
- [ ] 6.8 Hide empty group sections entirely (do not render heading if group array is empty)
- [ ] 6.9 Show `CnEmptyState` ("No tasks assigned to you" + "Browse projects" button → `/projects`) when all three groups are empty
- [ ] 6.10 Show loading skeleton while `loading === true`
- [ ] 6.11 Handle `statusUpdated` event from `MyWorkTaskRow`: composable reactivity re-computes `tasksByUrgency` automatically via Pinia; verify task moves out of group correctly
- [ ] 6.12 Import ALL components used in the template AND list them in `components: {}` (ADR-015)
- [ ] 6.13 All user-visible strings wrapped in `t('planix', '...')` (ADR-007)
- [ ] 6.14 Add `<style scoped>` block (ADR-010)

## 7. Navigation & sidebar

- [ ] 7.1 Add "Dashboard" navigation item to the main menu component (route `/`), with an appropriate icon
- [ ] 7.2 Add "My Work" navigation item to the main menu component (route `/my-work`), with an appropriate icon
- [ ] 7.3 Verify active state highlights the correct nav item based on the current route (Vue Router `active-class`)

## 8. Translations

- [ ] 8.1 Add all new English strings to `l10n/en.json` using sentence case keys (ADR-007)
- [ ] 8.2 Add all corresponding Dutch translations to `l10n/nl.json` — human-readable Dutch, not English copies
- [ ] 8.3 Required keys (minimum set):
  - `"Dashboard"`, `"My work"`
  - `"Open"`, `"Overdue"`, `"In progress"`, `"Completed today"`
  - `"Due this week"`, `"Everything else"`
  - `"No projects yet"`, `"Create project"`
  - `"No tasks due this week"`
  - `"No tasks assigned to you"`, `"Browse projects"`
  - `"Loading…"`, `"Retry"`
  - `"Status updated"`, `"Failed to update status"`
- [ ] 8.4 Verify `en.json` and `nl.json` have exactly the same key set — zero gaps

## 9. Pre-commit verification

- [ ] 9.1 SPDX headers: `grep -rL 'SPDX-License-Identifier' src/composables/useDashboard.js src/views/DashboardView.vue src/views/MyWorkView.vue src/components/KpiCard.vue src/components/MyWorkTaskRow.vue` → must return no files
- [ ] 9.2 No raw fetch: `grep -rn 'fetch(' src/composables/useDashboard.js src/views/DashboardView.vue src/views/MyWorkView.vue src/components/KpiCard.vue src/components/MyWorkTaskRow.vue` → must return zero matches
- [ ] 9.3 No `@nextcloud/vue` imports: `grep -rn "from '@nextcloud/vue'" src/` → must be zero matches
- [ ] 9.4 Scoped styles: verify every new `<style>` block has the `scoped` attribute
- [ ] 9.5 try/catch coverage: every `await store.*` call in new `.vue` files is wrapped in `try/catch` with user feedback
- [ ] 9.6 Component imports: for every `<NcFoo>` or `<CnFoo>` in new templates, verify the component is imported AND listed in `components: {}`
- [ ] 9.7 Translation keys: all `t()` keys in new files are English sentence-case strings — no Dutch keys, no title-case keys
- [ ] 9.8 Run `npm run lint` — zero errors and zero warnings before commit
