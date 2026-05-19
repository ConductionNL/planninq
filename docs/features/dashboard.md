# Dashboard & My Work

The personal landing page of Planix — an overview of your work state and tasks assigned to you across all projects.

## Overview

When you open Planix, you land on the Dashboard. It shows KPI cards for your task counts, the five most recently active projects you are a member of, and tasks due within the next seven days. The My Work view provides a prioritized list of all tasks assigned to you, grouped by urgency.

## Dashboard

- **KPI cards** — four cards showing counts for Open tasks (open or in_progress), Overdue tasks (past due date, not done), In Progress, and Completed Today; each card is clickable and navigates to My Work with the corresponding filter applied
- **Recent projects** — the five most recently active projects you belong to, each showing title, color/icon, task count, and a progress bar (done/total tasks)
- **Due this week** — tasks assigned to you with a due date within the next seven days, sorted by due date; today's and tomorrow's due dates are highlighted

**Empty states**: New users with no projects see a "No projects yet" empty state with a "Create project" button. KPI cards always show (set to 0 when no tasks exist).

## My Work

My Work shows all tasks assigned to you across all projects, grouped into three sections:

| Group | Criteria |
|-------|----------|
| **Overdue** | Due date is in the past, status is not done (highlighted in red) |
| **Due this week** | Due date is within the next 7 days, status is not done |
| **Everything else** | Open tasks with no due date or a due date more than 7 days away |

Within each group, tasks are sorted by priority (urgent → high → normal → low).

From My Work you can update a task's status via an inline dropdown, or click the task title to navigate to the task detail view. The browser back button returns to My Work.

## Standards

- Schema.org Action / PlanAction — task aggregation query pattern
- Nextcloud Dashboard API (OCP\Dashboard\IWidget) — optional widget integration (V1)

## Spec

- [dashboard-my-work spec](https://github.com/ConductionNL/planix/blob/development/openspec/specs/dashboard-my-work.md)
