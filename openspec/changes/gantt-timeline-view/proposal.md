---
kind: code
---

# Proposal: gantt-timeline-view

## Why

A Gantt / timeline view is the defining table-stake of every project-management product
(Asana Timeline, Monday Gantt, Jira/OpenProject/ClickUp/Wrike, MS Project) — the schedule
view that a kanban board cannot give: tasks laid out on a time axis, showing overlap,
slack, and the dependency chain. Planix already has **all the data** for it and **none of
the view**:

- The task schema carries `startDate`, `dueDate`, and `duration`.
- Task **dependencies** shipped (`task-dependencies` — archived 2026-06-14), so
  predecessor/successor links already exist.
- Tasks belong to projects (`projects` spec), so a per-project timeline is a natural scope.

What is missing is purely a **read surface**: nothing renders tasks on a time axis or draws
the dependency edges. Planix ships kanban (`kanban-board`) and a my-work dashboard but no
schedule view, so a planner cannot see whether the plan is actually feasible in time.

## What Changes

- Add `lib/Controller/TimelineController.php` (SPDX docblock; `@NoAdminRequired`,
  `@NoCSRFRequired`; inject `IUserSession`, `ObjectService`): `forProject(projectId, from, to)`
  returns the project's tasks with their `startDate`/`dueDate`/`duration` and their
  dependency links, read through `ObjectService` (RBAC/tenancy-scoped, ADR-022) — no new
  schema, no new storage, no scheduling engine. Tasks with no dates are returned flagged
  "unscheduled" (listed in a backlog rail, not dropped).
- Register the route `timeline#forProject` (GET `/api/projects/{projectId}/timeline`) in
  `appinfo/routes.php` with explicit auth (route-auth + route-reachability PASS).
- Add `src/api/timeline.js` (stateless functions, no store) and `src/views/ProjectTimeline.vue`:
  a horizontal time axis (day/week/month zoom) with one bar per task (start→due, label,
  status colour), dependency arrows between bars from the existing links, an "unscheduled"
  rail, and today-marker. Read-only in v1 (drag-to-reschedule is an explicit follow-up).
  Strings via `t()`, data via the API/`loadState`, any `NcSelect` carries `inputLabel`.
  Add a "Timeline" tab/entry to the project surface + manifest menu.
- Reuse the existing dependency data as the edge source — the timeline MUST NOT re-derive
  or duplicate dependency state; it renders what `task-dependencies` already stores.

## Impact

- Affected: new `TimelineController`, one route, `src/api/timeline.js`,
  `src/views/ProjectTimeline.vue`, project-surface tab + menu entry, and their unit/vitest/e2e
  tests. NO schema change, NO new storage, NO scheduling engine — a read surface over
  existing task dates + dependency links.
- Out of scope (explicit follow-ups): drag-to-reschedule (write path), critical-path
  computation, resource/workload lanes, milestones-as-diamonds (a `milestone` task flag is
  a separate small change), and baseline/variance tracking.
