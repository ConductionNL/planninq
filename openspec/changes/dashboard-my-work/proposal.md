# Proposal: Dashboard & My Work

## Summary

Implement the Planix personal dashboard (route `/`) and My Work view (route `/my-work`). The dashboard is the app's default landing page, providing four KPI cards, a recent-projects section, and a tasks-due-this-week section. The My Work view gives each user a priority-sorted, urgency-grouped inbox of all tasks assigned to them. No new OpenRegister entities are required — both views are pure frontend aggregation patterns over the existing Task and Project stores.

## Motivation

Planix currently has no landing page. Users open the app with no overview of their personal work state and must navigate into individual projects to find their tasks. Without a dashboard:

- Overdue tasks are invisible until a user drills into a specific board
- There is no single entry point showing "what I need to work on right now"
- KPI-level awareness (how many tasks are open, overdue, completed today) requires manual counting

The My Work view closes this gap by surfacing all assigned tasks in a single, urgency-grouped list with inline status updates.

## Affected Projects

- [x] Project: `planix` — Frontend-only: new dashboard route, My Work route, shared `useDashboard` composable, `KpiCard` and `MyWorkTaskRow` components

## Scope

### In Scope

- Dashboard landing page (route `/`) with 4 KPI cards, recent projects section (5 most recent), and due-this-week section
- My Work view (route `/my-work`) with tasks grouped into: Overdue / Due this week / Everything else
- Inline status update on task rows in My Work — no full navigation required
- Clickable KPI cards that navigate to `/my-work?filter={key}` with the selected filter pre-applied
- Empty states for new users (no projects), and for users with projects but no assigned tasks
- Navigation items for Dashboard and My Work in the app sidebar

### Out of Scope

- Activity feed on dashboard (V1 — deferred; depends on Nextcloud Activity API integration)
- Nextcloud Dashboard widget `OCP\Dashboard\IWidget` (V1 integration — mentioned in context brief)
- Sub-task display in My Work (V1 — sub-tasks not yet implemented)
- Time tracking surface on dashboard (separate change)
- CalDAV / calendar widget

## Approach

Pure frontend aggregation with at most 3 parallelised API calls on mount:

1. `GET /api/tasks?assignedTo=currentUser` — all my tasks (KPI counts + My Work data)
2. `GET /api/projects?member=currentUser&limit=5&sort=updatedAt:desc` — recent projects
3. Due-this-week list is derived from call 1 when result set is small; otherwise a scoped call with `dueDateFrom/dueDateTo` filters

A single `useDashboard` composable centralises data fetching and computed KPI logic. No new Pinia stores — the existing task and project stores provide the underlying state.

## New Dependencies

None

## Impact

- `src/views/DashboardView.vue` — new dashboard page (`CnDashboardPage`, self-contained per ADR-017)
- `src/views/MyWorkView.vue` — new My Work page (`CnIndexPage`, self-contained per ADR-017)
- `src/composables/useDashboard.js` — KPI computation, urgency grouping, parallel fetches
- `src/components/KpiCard.vue` — stateless KPI card (count + label + click handler → router push)
- `src/components/MyWorkTaskRow.vue` — task row with inline status dropdown
- `src/router/index.js` — register `/` and `/my-work` routes
- `src/components/MainMenu.vue` (or equivalent) — add Dashboard + My Work nav items
- `l10n/en.json`, `l10n/nl.json` — all new user-visible strings

## Cross-Project Dependencies

None

## Risks

### Risk 1: Large task set degrades dashboard load time
**Severity:** Medium
**Mitigation:** Tasks fetched server-side with `assignedTo` filter. KPI counts derived from the already-fetched list — no extra roundtrip. Dashboard renders loading skeletons immediately on mount; data populates asynchronously.

### Risk 2: Inline status update causes stale My Work grouping
**Severity:** Low
**Mitigation:** After a successful PATCH, the `useDashboard` composable reactively re-computes `tasksByUrgency`. Tasks that move to `done` are removed from all groups immediately via Pinia reactivity — no manual list splice required.

## Rollback Strategy

Revert the single commit. No schema changes, no migrations, no data changes — purely additive frontend code.
