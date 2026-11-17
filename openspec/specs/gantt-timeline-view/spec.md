# gantt-timeline-view Specification

## Purpose
Read-only timelines: one project's tasks on a time axis with their dependencies, and several projects of a portfolio on one axis.

## Requirements

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

### Requirement: The timeline renders the existing dependency links, not a new copy

The timeline MUST draw dependency edges between task bars using the dependency links that
the `task-dependencies` capability already stores. It MUST NOT re-derive, duplicate, or
persist a separate copy of dependency state; it reads and renders what already exists.

#### Scenario: Dependency arrows come from stored links

- **GIVEN** two tasks with a stored predecessor→successor dependency
- **WHEN** the project timeline is rendered
- **THEN** an edge MUST be drawn between their bars sourced from the existing dependency link
- **AND** no new dependency object MUST be created by viewing the timeline

@e2e exclude edge sourcing asserted by the controller/view unit tests (reads dependency links, no writes).

### Requirement: Several projects can be viewed on one timeline

The portfolio timeline MUST show the projects of a chosen portfolio on one time axis, each as a summary bar that opens into its phases and tasks. It MUST read all projects through one RBAC-scoped request, MUST leave out projects the viewer cannot read, and MUST draw dependencies between tasks of different projects when both ends are shown. It MUST be read-only and operable by keyboard. Tier: V1 (docs/FEATURES.md has no row; it extends this capability's single-project timeline).

#### Scenario: A portfolio on one axis

- **GIVEN** a portfolio manager of "Ruimte", which holds three projects with planned dates
- **WHEN** the manager opens the portfolio timeline at /portfolio/timeline and picks "Ruimte"
- **THEN** three summary bars are drawn on one axis, sorted by start date
- **AND** moving focus to a bar and pressing Enter shows that project's phases and task bars under it

#### Scenario: Projects the viewer cannot read are left out

@e2e exclude API-level filtering with no screen, asserted by TimelineControllerTest (task 3.1)
- **GIVEN** a user who can read two of three requested projects
- **WHEN** the user sends `GET /apps/planninq/api/timeline?projects=a,b,c`
- **THEN** the answer holds the two readable projects
- **AND** lists the third under `skipped` without any of its tasks

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

### Requirement: A schedule run is checked whole and written in order on the server

When a member confirms "Move all", the system MUST send the dragged task and every pushed task to
the server as one run. The server MUST refuse the run and write none of it when the caller does not
work on the project, a task of the run is not in the project, a date is not a valid day or a start
is after its due date, or a `blocks` link the run touches would leave a blocked task starting before
its blocker is due. A run that passes MUST be written in the run's order; the first write that
fails MUST stop the run, the tasks before it MUST stay moved, and the answer MUST name the task it
stopped at and how many were written. The timeline MUST tell the member where the run stopped.

#### Scenario: A run that breaks a link writes nothing

- **GIVEN** task Design blocking task Build, which starts Monday 9 November 2026
- **WHEN** a member's run moves Design's due date to Tuesday 10 November and leaves Build alone
- **THEN** the server refuses the run with a conflict and Design keeps its due date

#### Scenario: A write run stops at the first failure and reports it

- **GIVEN** a run moving Design, Build and Test, and OpenRegister refuses the write of Build
- **WHEN** the member chooses "Move all"
- **THEN** Design carries its new dates, Build and Test keep theirs
- **AND** the timeline says "Stopped at Build: its dates could not be saved. The tasks before it were moved."
