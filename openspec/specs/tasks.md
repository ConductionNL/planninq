# Tasks Specification

**Status**: idea

**Standards**: iCalendar VTODO (RFC 5545), Schema.org Action/PlanAction, VNG InterneTaak
**Feature tier**: MVP

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

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

## User Stories

- As a team member, I want to create a task with a title and description so that I can capture work that needs to be done
- As a team lead, I want to assign tasks to specific users so that workload is clearly distributed
- As a developer, I want to set a due date on a task so that I can track deadlines
- As a user, I want to see tasks I created and tasks assigned to me so that I know my responsibilities
- As a project manager, I want to filter board tasks by assignee so that I can review each person's workload
- As a team member, I want to set a time estimate on a task so that I can plan capacity

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

## Notes

- Sub-tasks (V1) will use `parent` reference — one level deep only
- Task dependencies (V1) will be a separate `dependency` entity linking two tasks with a type (blocks/is-blocked-by)
- CalDAV VTODO sync (V1) uses `calendarEventUid` as the back-reference to the NC Tasks app
- VNG InterneTaak mapping: `title` → `gevraagdeHandeling`, `assignedTo` → `toegewezenAanGebruikersnaam`, `dueDate` → `gevraagdeDatum`, `completedAt` → `afhandelingsdatum`
