# Tasks Specification

**Status**: in-progress

**Standards**: iCalendar VTODO (RFC 5545), Schema.org Action/PlanAction, VNG InterneTaak
**Feature tier**: MVP

**OpenSpec changes:**
- [register-schemas](../changes/register-schemas/) — defines the Task schema in planix_register.json

## Placement & Information Architecture

**Placement type:** `SUB_PAGE` — Sub-page beneath a top-level menu entry. Renders as a page inside the parent surface (usually reachable via a router child route or a tab on the parent index page).

**Lives at:** Mijn werk > Mijn taken (also Projecten > Taken)

**Rationale:** tasks live in two views, one model  
_Source: /tmp/ia-small5.md_

> **Implementation note for builders:** Respect the placement above. Do not promote this spec to a top-level menu item, sub-page, or new route unless the placement type explicitly says so. If the placement is `DETAIL_TAB`, `WIDGET`, `ACTION`, `SETTING`, or `INFRA`, the feature must NOT introduce a new entry in the app sidebar. When in doubt, ask before creating a new top-level surface.

## Purpose

Tasks are the core unit of work in Planix. A task represents a discrete piece of work with a title, description, assignee, due date, priority, and status. Tasks belong to a project and may be placed in a kanban column or held in the backlog. Users create tasks to track and coordinate work across a dev or IT team.

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for the full entity definitions.

| Property | Type | Required | Default |
|----------|------|----------|---------|
| `title` | string | Yes | — |
| `description` | string | No | — |
| `status` | enum: open, in_progress, blocked, done, cancelled | Yes | `open` |
| `priority` | enum: low, normal, high, urgent | No | `normal` |
| `project` | reference (Project) | No | — |
| `column` | reference (Column) | No | null (backlog) |
| `columnOrder` | integer | No | 0 |
| `assignedTo` | string (user UID) | No | — |
| `dueDate` | date | No | — |
| `startDate` | date | No | — |
| `estimatedDuration` | integer (minutes) | No | — |
| `percentComplete` | integer 0–100 | No | 0 |
| `labels` | string[] | No | [] |
| `parent` | reference (Task) | No | null |
| `calendarEventUid` | string | No | null |
| `completedAt` | datetime | No | null |

## Requirements

### Requirement: Task CRUD [MVP]
The system MUST allow authenticated users to create, read, update, and delete tasks within projects they are members of.

#### Scenario: Create a task
- GIVEN a user is a member of a project
- WHEN the user creates a task with a title
- THEN the system MUST store the task with status `open` and priority `normal`
- AND the task MUST be placed in the backlog (no column) by default

#### Scenario: Assign task to column
- GIVEN a task exists in the backlog
- WHEN the user drags the task to a kanban column
- THEN the system MUST update the task's `column` reference and `columnOrder`
- AND the task MUST appear in that column on all users' board views

#### Scenario: Mark task as done
- GIVEN a task has status `in_progress`
- WHEN the user moves the task to a column with `type: done`
- THEN the system MUST set `status` to `done`
- AND the system MUST set `completedAt` to the current datetime

#### Scenario: WIP limit exceeded
- GIVEN a column has `wipLimit` = 3 and already contains 3 tasks
- WHEN a user drags a fourth task into that column
- THEN the system MUST display a visual warning on the column
- AND the system MUST still allow the task to be placed (soft limit, not blocked)

#### Scenario: Task priority filter on board
- GIVEN a project kanban board is open
- WHEN the user applies a filter for `priority: urgent`
- THEN the system MUST show only urgent tasks on the board
- AND non-urgent tasks MUST be visually hidden or faded

#### Scenario: Assignment notification sent
- GIVEN UserA is a member of a project
- WHEN UserB assigns a task to UserA
- THEN the system MUST create a Nextcloud notification for UserA with subject `task_assigned`
- AND the notification MUST only be sent if UserA has `notify_assigned = true` in their user settings
- AND UserA MUST NOT receive a notification if they assigned the task to themselves

#### Scenario: Delete a task
- GIVEN a task exists in a project
- WHEN the task creator, project creator, or a Nextcloud admin clicks "Delete task" and confirms the dialog
- THEN the system MUST delete the task and all linked TimeEntries
- AND the task MUST be removed from the board and backlog immediately for all users

#### Scenario: Delete a task with sub-tasks (V1 guard)
- GIVEN a task has one or more sub-tasks (V1 feature)
- WHEN a user attempts to delete the parent task
- THEN the system MUST prompt: "This task has sub-tasks. Delete all sub-tasks too, or move them to backlog?"
- AND the system MUST NOT silently orphan sub-tasks

#### Scenario: Move task to another project
- GIVEN a task belongs to Project A
- WHEN a project member edits the task and selects Project B as the new project
- THEN the system MUST update the task's `project` reference
- AND the task's `column` assignment MUST be cleared (task moves to Project B's backlog)
- AND the task MUST no longer appear in Project A's board or backlog

### Requirement: Task Search [MVP]
The system MUST allow users to search tasks within a project.

#### Scenario: Search tasks by title
- GIVEN a project board or backlog is open
- WHEN the user types in the search field
- THEN the system MUST filter visible tasks to those matching the search term in `title` or `description`
- AND the filter MUST apply without page reload
- AND the search MUST be case-insensitive

### Requirement: Bulk Task Operations [MVP]
The system MUST allow users to update multiple tasks at once from the backlog view.

#### Scenario: Bulk status update
- GIVEN multiple tasks are selected in the backlog view
- WHEN the user selects "Change status" from the bulk actions bar
- THEN the system MUST update all selected tasks to the chosen status
- AND a toast notification MUST confirm "N tasks updated"

#### Scenario: Bulk assignee update
- GIVEN multiple tasks are selected in the backlog view
- WHEN the user selects "Assign to" from the bulk actions bar and picks a user
- THEN the system MUST set `assignedTo` on all selected tasks
- AND assignment notifications MUST be sent per each task's normal notification rules

## User Stories

- As a team member, I want to create a task with a title and description so that I can capture work that needs to be done
- As a team lead, I want to assign tasks to specific users so that workload is clearly distributed
- As a developer, I want to set a due date on a task so that I can track deadlines
- As a user, I want to see tasks I created and tasks assigned to me so that I know my responsibilities
- As a project manager, I want to filter board tasks by assignee so that I can review each person's workload
- As a team member, I want to set a time estimate on a task so that I can plan capacity
- As a user, I want to delete a task I no longer need so that the board stays clean
- As a team lead, I want to move a task to a different project so that it is tracked in the right place
- As a user, I want to search for tasks by title so that I can find specific work items quickly
- As a project manager, I want to bulk-update assignee or status on multiple tasks so that I can reorganize work efficiently

## Acceptance Criteria

- [ ] A task can be created with at minimum a title
- [ ] A task without a column assignment appears in the project backlog
- [ ] Dragging a task to a column updates its `column` and `columnOrder` atomically
- [ ] Moving a task to a `done`-type column sets `status: done` and `completedAt`
- [ ] WIP limit violations show a visual warning but do not block the drop
- [ ] Board filter by priority, assignee, and label works without page reload
- [ ] Tasks show assignee avatar, due date, priority color, and label chips on kanban cards
- [ ] Overdue tasks (dueDate < today, status != done) are highlighted in red on cards
- [ ] Assigning a task to another user triggers a `task_assigned` notification (respects `notify_assigned` user setting)
- [ ] A user assigning a task to themselves does NOT trigger an assignment notification
- [ ] Deleting a task requires a confirmation dialog and removes all linked TimeEntries
- [ ] Only the task creator, project creator, or NC admin can delete a task
- [ ] Moving a task to another project clears its column assignment (task lands in new project's backlog)
- [ ] Task search filters by title and description, case-insensitive, without page reload
- [ ] Bulk status update applies to all selected tasks and confirms with a toast
- [ ] Bulk assignee update applies to all selected tasks and respects per-user notification settings

## Notes

- Sub-tasks (V1) will use `parent` reference — one level deep only
- Task dependencies (V1) will be a separate `dependency` entity linking two tasks with a type (blocks/is-blocked-by)
- CalDAV VTODO sync (V1) uses `calendarEventUid` as the back-reference to the NC Tasks app
- VNG InterneTaak mapping: `title` → `gevraagdeHandeling`, `assignedTo` → `toegewezenAanGebruikersnaam`, `dueDate` → `gevraagdeDatum`, `completedAt` → `afhandelingsdatum`
