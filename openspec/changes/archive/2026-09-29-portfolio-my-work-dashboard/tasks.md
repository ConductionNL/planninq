# Tasks: portfolio-my-work-dashboard

## 1. My tasks

- [x] 1.1 `fetchMyTasks()` in `src/store/projects.js` (assigned or shared, open statuses, paged) and a pure `groupMyTasks(tasks, today)` helper. Verify: vitest specs for the merge and the three groups with priority order.
- [x] 1.2 `src/views/MyWork.vue`, its manifest page and registry entry, a "My tasks" menu entry, inline status change, and the empty state "No tasks assigned to you" with "Browse projects". Verify: Playwright e2e opens My tasks, changes a status in place, and follows a title to TaskDetail and back.

## 2. Dashboard

- [x] 2.1 Check the stat filter grammar for date and array operators, then add the four task KPI widgets and the member filter on "Projects I am in" in `src/manifest.json`. Verify: `npm run check:manifest` exits 0; Playwright e2e reads the counts for a seeded user.

## 3. Project order

- [x] 3.1 Pin and "Move up" and "Move down" in `src/components/DashboardPanels.vue`, stored as `dashboardProjectOrder` through the user settings endpoint. Verify: PHPUnit test for the preference in `SettingsService`; Playwright e2e pins a project and reloads.

## 4. Copy and verification

- [x] 4.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [x] 4.2 `openspec validate portfolio-my-work-dashboard --type change --strict` passes.
