# gantt-timeline-view delta for planning-timeline-editing

## MODIFIED Requirements

### Requirement: A project's tasks can be viewed on a time axis

The system MUST provide a per-project timeline that returns the project's tasks with their
`startDate`, `dueDate`, and `duration`, laid out for rendering on a time axis. Reads MUST go
through OpenRegister `ObjectService` (RBAC/tenancy-scoped); a caller MUST NOT see tasks of a
project they cannot access. Tasks with no dates MUST be returned flagged as "unscheduled"
(surfaced separately), never silently dropped. The timeline MUST NOT introduce a new schema or
new storage. The timeline endpoint MUST stay read-only; date changes made on the timeline view
MUST be written to the existing task's `startDate` and `dueDate` through the OpenRegister object
API with the caller's own rights.

#### Scenario: A project timeline returns dated tasks positioned in time

- **GIVEN** a project whose tasks carry `startDate`/`dueDate`
- **WHEN** a permitted caller requests the project's timeline for a window
- **THEN** the system MUST return those tasks with their `startDate`/`dueDate`/`duration` so each can be drawn as a bar from start to due
- **AND** tasks with no dates MUST be returned flagged "unscheduled"

#### Scenario: Timeline access is scoped by OpenRegister RBAC

- **GIVEN** a caller with no access to a project
- **WHEN** they request that project's timeline
- **THEN** the system MUST NOT return its tasks (RBAC-scoped through `ObjectService`)

#### Scenario: The timeline endpoint writes nothing

- **GIVEN** a project member
- **WHEN** they call GET /apps/planninq/api/projects/{projectId}/timeline
- **THEN** no task, dependency or other object is created, updated or deleted

@e2e exclude the read/windowing/unscheduled-split is unit-tested against seeded tasks; a Playwright timeline smoke follows once seed data lands.

## ADDED Requirements

### Requirement: A project member can reschedule a task on the timeline

A project member MUST be able to move a task on the project timeline by dragging its bar, and change its start or due date by dragging the bar's left or right end. The system SHALL write the new dates to the task, put the bar back and show an error when the write fails, and keep the task's length in working days when the whole bar moves. Tier: V1 (docs/FEATURES.md, project management; this change adds the row).

#### Scenario: A member drags a bar to move a task

- **GIVEN** a project member on the timeline at /projects/:id/timeline and the task "Export to CSV" running from Monday 5 October 2026 to Friday 9 October 2026
- **WHEN** they drag the bar one week to the right and release it
- **THEN** the task's `startDate` is 2026-10-12 and its `dueDate` is 2026-10-16
- **AND** the bar is drawn at the new dates after the timeline reloads

#### Scenario: A member resizes the due date

- **GIVEN** the same member and task
- **WHEN** they drag the right end of the bar to Tuesday 13 October 2026
- **THEN** the task's `startDate` stays 2026-10-05 and its `dueDate` is 2026-10-13

#### Scenario: A failed write puts the bar back

- **GIVEN** a project member dragging the bar of a task whose write OpenRegister refuses
- **WHEN** they release it
- **THEN** the bar returns to its old dates
- **AND** the page says "The dates could not be saved."

### Requirement: A task's dates can be changed on the timeline from the keyboard

Every task bar MUST be reachable with Tab and SHALL accept Left and Right to move the task by one working day, Shift with Left or Right to move only its due date, and Enter to open a dialog with a start and a due date field. Each change MUST be announced to assistive technology with the task's new dates. Tier: V1.

#### Scenario: A member moves a task with the keyboard

- **GIVEN** a project member using only the keyboard on the timeline with focus on the bar of "Export to CSV", running from Monday 5 October to Friday 9 October 2026
- **WHEN** they press Right
- **THEN** the task runs from Tuesday 6 October to Monday 12 October 2026
- **AND** a screen reader announces "Export to CSV now runs from 6 October to 12 October"

#### Scenario: A member sets dates in the dialog

- **GIVEN** the same member with focus on the bar
- **WHEN** they press Enter, set the due date to 2026-10-20 and save
- **THEN** the task's `dueDate` is 2026-10-20 and focus returns to the bar

### Requirement: Dependent tasks can move along when a task slips

A project MUST carry an `autoSchedule` flag that defaults to off and that only the project owner or an admin can change. When it is on and a member moves a task's due date later, the system MUST show every task that the change would push, following `blocks` dependencies transitively, with its old and new dates, and SHALL write none of them before the member confirms. Each pushed task SHALL start on the first working day after its blocker's new due date and keep its length in working days. Tasks linked by other dependency types MUST NOT move, and no task SHALL be moved earlier. The timeline MUST draw `blocks` links differently from other link types. Tier: V1.

#### Scenario: A slip shows the preview and moves the chain

- **GIVEN** a project with auto-scheduling on, task A due Friday 9 October 2026 blocking task B (Monday 12 to Wednesday 14 October), which blocks task C (Thursday 15 to Friday 16 October)
- **WHEN** a project member drags the end of A to Tuesday 13 October 2026
- **THEN** a dialog lists B moving to 14 to 16 October and C moving to 19 to 20 October
- **AND** after they choose "Move all", A, B and C carry those dates

#### Scenario: A member moves only the task they dragged

- **GIVEN** the same project and preview
- **WHEN** the member chooses "Only this task"
- **THEN** A is due 13 October and B and C keep their dates

#### Scenario: A relates link moves nothing

- **GIVEN** a project with auto-scheduling on and task A linked to task D by a `relates` dependency
- **WHEN** a project member moves A's due date a week later
- **THEN** no preview lists D and D keeps its dates
- **AND** the link between A and D is drawn as a dotted line without an arrowhead

#### Scenario: Without auto-scheduling only the dragged task moves

- **GIVEN** a project with auto-scheduling off and task A blocking task B
- **WHEN** a project member moves A's due date past B's start
- **THEN** no preview appears, A has its new dates and B keeps its own
