# Dashboard & My Work Specification

**Status**: in-progress

**Standards**: Schema.org Action/PlanAction (task aggregation), Nextcloud Dashboard API (OCP\Dashboard\IWidget)
**Feature tier**: MVP

**OpenSpec changes:**
- [dashboard-my-work](../changes/dashboard-my-work/) — implements dashboard landing page, KPI cards, My Work view

## Placement & Information Architecture

**Placement type:** `TOP_MENU` — Top-level menu entry — this functionality earns its own item in the app's left-nav.

**Lives at:** Mijn werk

**Rationale:** primary landing  
_Source: /tmp/ia-small5.md_

> **Implementation note for builders:** Respect the placement above. Do not promote this spec to a top-level menu item, sub-page, or new route unless the placement type explicitly says so. If the placement is `DETAIL_TAB`, `WIDGET`, `ACTION`, `SETTING`, or `INFRA`, the feature must NOT introduce a new entry in the app sidebar. When in doubt, ask before creating a new top-level surface.

## Purpose

The dashboard is the landing page for Planix — a personal overview of the user's work state across all projects. It shows KPI cards (open tasks, overdue, in progress, completed today), recent projects, and quick access to tasks due soon. The "My Work" view provides a focused, priority-sorted list of all tasks assigned to the current user, grouped by urgency. Both views are personal — no new entity is needed; they are frontend aggregation patterns over Task and Project queries.

## Data Model

No new entities. The dashboard and My Work view aggregate from:
- `Task` — filtered by `assignedTo == currentUser`
- `Project` — filtered by `members contains currentUser`

## Requirements

### Requirement: Personal Dashboard [MVP]
The system MUST show a personal dashboard when a user opens Planix.

#### Scenario: Dashboard KPI cards
- GIVEN a user has tasks assigned to them across multiple projects
- WHEN the user opens the Planix dashboard
- THEN the system MUST show KPI cards with counts for:
  - Open tasks assigned to me (status: open or in_progress)
  - Overdue tasks (dueDate < today, status != done)
  - Completed today (completedAt is today)
  - In progress (status: in_progress)
- AND each KPI card MUST be clickable and navigate to the relevant My Work filter

#### Scenario: Recent projects list
- GIVEN a user is a member of multiple projects
- WHEN the dashboard loads
- THEN the system MUST show the 5 most recently active projects
- AND each project entry MUST show: title, color/icon, task count, progress bar (done/total)

#### Scenario: Tasks due this week
- GIVEN a user has tasks with due dates
- WHEN the dashboard loads
- THEN the system MUST show tasks due within the next 7 days, sorted by due date
- AND tasks MUST show: title, project name, due date (highlighted if today or tomorrow)

### Requirement: My Work View [MVP]
The system MUST provide a "My Work" view showing all tasks assigned to the current user, grouped by urgency.

#### Scenario: Display My Work
- GIVEN a user has tasks assigned across multiple projects
- WHEN the user opens the My Work view
- THEN the system MUST display tasks in three groups:
  1. **Overdue** — dueDate < today, status != done (highlighted in red)
  2. **Due this week** — dueDate within next 7 days, status != done
  3. **Everything else** — open tasks with no due date or due date > 7 days
- AND within each group, tasks MUST be sorted by priority (urgent → high → normal → low)

#### Scenario: Quick status update from My Work
- GIVEN a task is shown in My Work
- WHEN the user clicks the status indicator on a task row
- THEN the system MUST show a dropdown with available statuses
- AND selecting a status MUST update the task without navigating away from My Work

#### Scenario: Navigate to task detail from My Work
- GIVEN a task is shown in My Work
- WHEN the user clicks the task title
- THEN the system MUST navigate to the task detail view (CnDetailPage)
- AND the browser back button MUST return to My Work

#### Scenario: Empty My Work
- GIVEN the user has no tasks assigned
- WHEN the user opens My Work
- THEN the system MUST show a CnEmptyState with message "No tasks assigned to you" and a "Browse projects" action

### Requirement: Dashboard Empty State [MVP]
The system MUST guide new users who have no projects or tasks yet.

#### Scenario: Dashboard — new user with no projects
- GIVEN a user is authenticated and is not a member of any project
- WHEN the user opens the Planix dashboard
- THEN the system MUST show a CnEmptyState in place of the recent projects and due-this-week sections
- AND the message MUST read "No projects yet"
- AND a "Create project" button MUST be shown (navigates to the new project form)
- AND KPI cards MUST all show 0 (not be hidden)

#### Scenario: Dashboard — member of projects but no assigned tasks
- GIVEN a user is a member of projects but has no tasks assigned to them
- WHEN the user opens the Planix dashboard
- THEN KPI cards MUST show 0 for Open, Overdue, In Progress, and Completed Today
- AND the "Due this week" section MUST show a CnEmptyState: "No tasks due this week"
- AND recent projects MUST still render normally

## User Stories

- As a developer, I want to see all my tasks in one place when I open Planix so that I can prioritize my day
- As a user, I want to see which tasks are overdue so that I can address them immediately
- As a team member, I want to see tasks due this week so that I can plan my workload
- As a user, I want to see my recent projects at a glance so that I can navigate quickly to active work
- As a user, I want KPI cards on the dashboard so that I can understand my work state without scrolling
- As a developer, I want to update task status directly from My Work so that I don't have to open each task
- As a new user, I want a helpful empty state when I have no projects yet so that I know how to get started
- As a user, I want clicking a KPI card to take me to My Work filtered to that category so that I can act on it immediately

## Acceptance Criteria

- [ ] Dashboard is the default route (`/`) and loads on Planix open
- [ ] Dashboard shows 4 KPI cards: Open, Overdue, In Progress, Completed Today
- [ ] Each KPI card is clickable and navigates to My Work with the corresponding filter pre-applied
- [ ] Dashboard shows the 5 most recently active projects with progress bars
- [ ] Dashboard shows tasks due within 7 days, sorted by due date ascending
- [ ] "Due this week" section shows CnEmptyState when no tasks are due this week
- [ ] New user with no projects sees a CnEmptyState with a "Create project" button; KPI cards show 0
- [ ] My Work groups tasks into Overdue, Due this week, Everything else
- [ ] Within each group, tasks are sorted by priority (urgent → high → normal → low)
- [ ] Tasks in My Work show: project name (badge), title, due date, status indicator, priority dot
- [ ] Status can be updated inline from My Work without full navigation
- [ ] Clicking a task title in My Work navigates to task detail; back button returns to My Work
- [ ] Empty My Work state shows CnEmptyState with "Browse projects" action

## Notes

- No new OpenRegister entities are needed. Dashboard and My Work are pure frontend aggregation.
- The dashboard relies on at most 3 API calls (tasks assigned to me, projects I'm in, tasks due this week). These can be parallelized.
- Activity feed on dashboard (V1): shows recent task updates across all my projects via the Nextcloud Activity API.
- The Nextcloud Dashboard widget (OCP\Dashboard\IWidget) can surface a Planix widget in the NC Dashboard for overdue task count — a V1 integration.
