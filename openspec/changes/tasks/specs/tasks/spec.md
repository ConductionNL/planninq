# Tasks Specification (Delta)

**Status**: in-progress
**Scope**: planix
**Standards**: iCalendar VTODO (RFC 5545), Schema.org Action/PlanAction, VNG InterneTaak
**OpenSpec changes**:
- [tasks](../../) — introduces the Task entity, CRUD, board, backlog, search, and bulk operations

## Purpose

Defines the complete behavioural contract for the `Task` entity in Planix: lifecycle (CRUD, status transitions, column placement), filtering, search, bulk operations, assignment notifications, and permission rules. Tasks are the core unit of work: they belong to a project, optionally occupy a kanban column, and can be assigned to a Nextcloud user.

---

## ADDED Requirements

### Requirement: REQ-TSK-001 — Task CRUD [MVP]

The system MUST allow authenticated project members to create, read, update, and delete tasks within projects they are members of.

A new task MUST default to `status: open` and `priority: normal` unless explicitly set by the client. A task without a `column` reference MUST appear in the project backlog.

#### Scenario: REQ-TSK-001-01 — Create task with defaults

- GIVEN a user is an authenticated member of a project
- WHEN the user submits a create-task request with only a `title`
- THEN the system MUST persist a Task object with `status: open`, `priority: normal`, and `column: null`
- AND the task MUST appear in the project's backlog

#### Scenario: REQ-TSK-001-02 — Assign task to kanban column

- GIVEN a task exists in the backlog (column is null)
- WHEN the user sends a PATCH request setting `column` to a valid Column ID and `columnOrder` to an integer
- THEN the system MUST update both `column` and `columnOrder` atomically
- AND the task MUST appear in the specified column for all users viewing the board

#### Scenario: REQ-TSK-001-03 — Move task to done column sets completedAt

- GIVEN a task has `status: in_progress`
- WHEN the user moves the task to a Column with `type: done`
- THEN the system MUST set `status` to `done`
- AND the system MUST set `completedAt` to the current server datetime
- AND the client MUST NOT be able to override `completedAt` directly

#### Scenario: REQ-TSK-001-04 — WIP limit exceeded shows warning

- GIVEN a Column has `wipLimit: 3` and already contains 3 tasks
- WHEN a user moves a fourth task into that column
- THEN the system MUST save the task into the column (soft limit — not blocked)
- AND the frontend MUST display a visual warning indicator on the column header
- AND the warning MUST NOT prevent the task from being placed

#### Scenario: REQ-TSK-001-05 — Priority filter on board

- GIVEN a project kanban board is open
- WHEN the user applies a filter for `priority: urgent`
- THEN the system MUST show only tasks with `priority: urgent` on the board
- AND tasks with other priorities MUST be visually hidden or faded
- AND the filter MUST apply without a full page reload

#### Scenario: REQ-TSK-001-06 — Assignment notification sent

- GIVEN UserA is an authenticated member of a project with `notify_assigned: true` in their user settings
- WHEN UserB assigns a task to UserA via a PUT or PATCH request
- THEN the system MUST create a Nextcloud notification for UserA with subject `task_assigned`

#### Scenario: REQ-TSK-001-07 — No self-assignment notification

- GIVEN a user is assigning a task to themselves
- WHEN the PATCH request sets `assignedTo` equal to the requesting user's UID
- THEN the system MUST NOT send a `task_assigned` notification

#### Scenario: REQ-TSK-001-08 — Notify_assigned setting respected

- GIVEN UserA has `notify_assigned: false` in their user settings
- WHEN any other user assigns a task to UserA
- THEN the system MUST NOT send a notification to UserA

#### Scenario: REQ-TSK-001-09 — Delete task with confirmation

- GIVEN a task exists in a project
- WHEN the task creator, project creator, or a Nextcloud admin confirms deletion via the dialog
- THEN the system MUST permanently delete the task
- AND the system MUST delete all TimeEntry objects linked to the task
- AND the task MUST immediately disappear from the board and backlog for all users

#### Scenario: REQ-TSK-001-10 — Delete permission guard

- GIVEN a task exists
- WHEN a user who is neither the task creator, project creator, nor a Nextcloud admin attempts to delete the task
- THEN the system MUST return HTTP 403 Forbidden
- AND the task MUST NOT be deleted

#### Scenario: REQ-TSK-001-11 — Delete task with sub-tasks (V1 guard)

- GIVEN a task has one or more sub-tasks (`parent` references pointing to this task)
- WHEN a user attempts to delete the parent task
- THEN the system MUST display a dialog: "This task has sub-tasks. Delete all sub-tasks too, or move them to backlog?"
- AND the system MUST NOT silently orphan sub-tasks
- AND deletion MUST only proceed after the user explicitly confirms one of the two options

#### Scenario: REQ-TSK-001-12 — Move task to another project

- GIVEN a task belongs to Project A and is placed in a column of Project A
- WHEN a project member edits the task and sets `project` to Project B
- THEN the system MUST update the task's `project` reference
- AND the system MUST set `column` to `null` and `columnOrder` to `0`
- AND the task MUST no longer appear in Project A's board or backlog
- AND the task MUST appear in Project B's backlog

---

### Requirement: REQ-TSK-002 — Task Search [MVP]

The system MUST allow users to search tasks within a project by title and description.

#### Scenario: REQ-TSK-002-01 — Search by title or description

- GIVEN a project board or backlog is open
- WHEN the user types a search term into the search field
- THEN the system MUST filter visible tasks to those matching the term in `title` or `description`
- AND the filter MUST be case-insensitive
- AND the filter MUST apply without a full page reload

#### Scenario: REQ-TSK-002-02 — Empty search clears filter

- GIVEN a search term is active and tasks are filtered
- WHEN the user clears the search field
- THEN the system MUST restore the full unfiltered task list
- AND no page reload MUST occur

---

### Requirement: REQ-TSK-003 — Bulk Task Operations [MVP]

The system MUST allow users to update multiple tasks simultaneously from the backlog view.

#### Scenario: REQ-TSK-003-01 — Bulk status update

- GIVEN multiple tasks are selected in the backlog view
- WHEN the user chooses "Change status" from the bulk actions bar and selects a status
- THEN the system MUST update `status` on all selected tasks to the chosen value
- AND a toast notification MUST confirm "N tasks updated" where N is the count of updated tasks

#### Scenario: REQ-TSK-003-02 — Bulk assignee update

- GIVEN multiple tasks are selected in the backlog view
- WHEN the user chooses "Assign to" from the bulk actions bar and selects a user
- THEN the system MUST set `assignedTo` on all selected tasks to the selected user UID
- AND assignment notifications MUST be evaluated per task according to REQ-TSK-001-06, REQ-TSK-001-07, and REQ-TSK-001-08

---

## Non-Functional Requirements

### Performance

- Task list endpoints MUST return within 500 ms for projects with up to 500 tasks
- Board filter and search MUST apply client-side (or via indexed OpenRegister query) without full page reload

### Accessibility

- Task cards MUST expose title, status, and assignee to screen readers
- Priority color on cards MUST NOT be the sole indicator — a text or icon label MUST accompany color (WCAG 1.4.1)
- All interactive elements (drag handles, filter controls, bulk checkboxes) MUST be keyboard-navigable (WCAG 2.1 AA)

### Internationalization

- All user-visible strings MUST use `t(appName, 'text')` translation keys
- Dutch translations MUST be provided in `l10n/nl.json` for all new keys (ADR-007)

### Security

- All Task API endpoints MUST require Nextcloud session authentication (`@NoCSRFRequired` only on read endpoints exposed to CalDAV — not applicable here)
- Project membership MUST be verified by `TaskService` before create/update/delete
- `assignedTo` MUST only accept valid Nextcloud user UIDs — the backend MUST validate against the Nextcloud user manager

---

## Acceptance Criteria

- [ ] A task can be created with at minimum a `title`
- [ ] A task without a column assignment appears in the project backlog
- [ ] Dragging a task to a column updates `column` and `columnOrder` atomically
- [ ] Moving a task to a `done`-type column sets `status: done` and `completedAt`
- [ ] WIP limit violations show a visual warning but do not block the drop
- [ ] Board filter by priority, assignee, and label works without page reload
- [ ] Task cards show assignee avatar, due date, priority color, and label chips
- [ ] Assigning a task to another user triggers a `task_assigned` notification (respects `notify_assigned`)
- [ ] A user assigning a task to themselves does NOT trigger an assignment notification
- [ ] Deleting a task requires a confirmation dialog and removes all linked TimeEntries
- [ ] Only the task creator, project creator, or NC admin can delete a task
- [ ] Moving a task to another project clears its column assignment (lands in new project's backlog)
- [ ] Task search filters by title and description, case-insensitive, without page reload
- [ ] Bulk status update applies to all selected tasks and confirms with a toast
- [ ] Bulk assignee update applies to all selected tasks and respects per-user notification settings
- [ ] Deleting a task with sub-tasks prompts the user to choose: delete all or move to backlog

## Notes

- Sub-tasks use the `parent` field (one level deep only). Sub-task UI is V1 scope — the field is stored but the V1 guard dialog (REQ-TSK-001-11) ensures no silent orphaning even in MVP.
- `calendarEventUid` is stored as a back-reference to the Nextcloud Tasks VTODO for future CalDAV sync (V1). No sync logic in this change.
- VNG InterneTaak field mapping is handled by a mapping layer — Dutch field names are NOT stored as primary properties.
- Related spec: `task-due-date-warning` — due date badge rendering on task cards (separate change).
