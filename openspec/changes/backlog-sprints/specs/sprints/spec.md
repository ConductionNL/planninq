# sprints delta for backlog-sprints

## ADDED Requirements

### Requirement: A project owner can turn sprints on for a project

A project MUST carry a `sprintsEnabled` flag that defaults to off, and only the project owner or an admin SHALL be able to change it. With the flag off, the project's backlog, board and timeline MUST behave exactly as they do without this change. Tier: V1 (docs/FEATURES.md, backlog management; this change adds the row).

#### Scenario: The owner turns sprints on

- **GIVEN** a project owner on the project board at /projects/:id of a project with sprints off
- **WHEN** they open project settings, switch on "Plan in sprints" in the Details tab and save
- **THEN** the project stores `sprintsEnabled: true`
- **AND** the backlog page at /projects/:id/backlog shows a "New sprint" button

#### Scenario: A project without sprints is unchanged

- **GIVEN** a project member on a project whose `sprintsEnabled` is false
- **WHEN** they open the project board at /projects/:id
- **THEN** the board shows every task of the project grouped by status, as before
- **AND** no sprint title, goal or "New sprint" button appears anywhere on the project

### Requirement: A project member can create a sprint and plan tasks into it

When sprints are on, a project member MUST be able to create a sprint with a name, a start date, an end date and an optional goal, and MUST be able to move a backlog task into a planned or active sprint. The system SHALL reject a sprint whose end date is before its start date. A task SHALL belong to at most one sprint at a time. Tier: V1.

#### Scenario: A member creates a sprint

- **GIVEN** a project member on the backlog page at /projects/:id/backlog of a project with sprints on
- **WHEN** they choose "New sprint", enter "Sprint 12", a start date of 2026-10-05 and an end date of 2026-10-16, and save
- **THEN** a `sprint` object with those values and status `planned` exists for the project
- **AND** the backlog page shows a "Sprint 12" section above the unplanned backlog

#### Scenario: An end date before the start date is refused

- **GIVEN** a project member in the new sprint dialog
- **WHEN** they enter a start date of 2026-10-16 and an end date of 2026-10-05 and save
- **THEN** no sprint is created
- **AND** the dialog says "The end date must be after the start date." next to the end date

#### Scenario: A member plans a backlog task into a sprint with the keyboard

- **GIVEN** a project member on the backlog page with a planned sprint "Sprint 12" and an unplanned task "Export to CSV"
- **WHEN** they focus the task row, open its actions and choose "Move to sprint" then "Sprint 12"
- **THEN** the task's `sprint` references "Sprint 12"
- **AND** the task is listed under the "Sprint 12" section and no longer under the backlog

### Requirement: A project member can start and complete a sprint

A project MUST have at most one active sprint. Starting a planned sprint SHALL set its status to `active`, and the system MUST refuse to start a second sprint while one is active. Completing a sprint MUST move every task in it whose status is not `done` or `cancelled` to a target the member chooses, the next planned sprint or the backlog, before the sprint's status becomes `completed`. Tier: V1.

#### Scenario: A second active sprint is refused

- **GIVEN** a project with the active sprint "Sprint 12" and the planned sprint "Sprint 13"
- **WHEN** a project member chooses "Start sprint" on "Sprint 13"
- **THEN** "Sprint 13" stays `planned`
- **AND** the page says "Complete Sprint 12 before you start another sprint."

#### Scenario: Completing a sprint moves unfinished work to the backlog

- **GIVEN** the active sprint "Sprint 12" holding three done tasks and two open tasks
- **WHEN** a project member chooses "Complete sprint", picks "Back to the backlog" in the dialog and confirms
- **THEN** the two open tasks have no sprint and appear in the unplanned backlog
- **AND** "Sprint 12" has status `completed` and the three done tasks still reference it

#### Scenario: A failed move keeps the sprint running

- **GIVEN** the active sprint "Sprint 12" holding two open tasks
- **WHEN** a project member completes it and the write for one of the two tasks fails
- **THEN** "Sprint 12" stays `active`
- **AND** the dialog names the task that did not move and offers "Try again"

### Requirement: The board of a project with a running sprint shows that sprint

When a project has sprints on and an active sprint, the project board MUST show only the tasks of that sprint by default, and its header SHALL show the sprint's name, its goal and the number of days left. The member MUST be able to switch the board back to every task of the project. Tier: V1.

#### Scenario: The sprint board shows only sprint tasks and keeps the goal visible

- **GIVEN** a project member, a project with the active sprint "Sprint 12" whose goal is "Customers can export their data", four tasks in that sprint and six tasks outside it
- **WHEN** they open the project board at /projects/:id
- **THEN** the board shows the four sprint tasks in their status columns and none of the other six
- **AND** the header shows "Sprint 12", the goal "Customers can export their data" and the days left until the end date

#### Scenario: The member switches back to the whole project

- **GIVEN** the same member on the sprint board of "Sprint 12"
- **WHEN** they press the "Whole project" toggle in the board header
- **THEN** the board shows all ten tasks
- **AND** the toggle is announced as pressed to assistive technology

#### Scenario: Without an active sprint the board shows the whole project

- **GIVEN** a project with sprints on and only planned or completed sprints
- **WHEN** a project member opens the project board
- **THEN** the board shows every task of the project
- **AND** the header says "No sprint is running."

### Requirement: A project member can edit the sprint goal while the sprint runs

A sprint MUST carry an optional goal that a project member can set when creating the sprint and change while it is planned or active, and the goal SHALL be shown wherever the sprint is shown: its backlog section, the sprint board header and the burndown. Tier: V1.

#### Scenario: The goal is changed during the sprint

- **GIVEN** a project member on the backlog page and the active sprint "Sprint 12"
- **WHEN** they edit the sprint, change the goal to "Customers can export and import their data" and save
- **THEN** the sprint stores the new goal
- **AND** the project board header shows the new goal the next time it loads

### Requirement: A project member can follow a sprint on a burndown chart

The system MUST offer a burndown chart per sprint that plots, for every day from the sprint's start date to its end date, the work remaining in the sprint, next to an ideal line from the starting total to zero. Work SHALL be measured in story points when any task in the sprint has story points, otherwise in estimated hours, otherwise in number of tasks, and the chart MUST name the unit it used. A task counts as finished on the date in its `completedAt`. The chart MUST have a table equivalent that lists the same numbers per day. Tier: V1.

#### Scenario: The burndown plots remaining story points

- **GIVEN** the active sprint "Sprint 12" from 2026-10-05 to 2026-10-16 with tasks worth 20 story points, of which tasks worth 8 points were moved to done on 2026-10-07
- **WHEN** a project member opens "Burndown" from the sprint board header
- **THEN** the chart shows 20 points remaining on 2026-10-05 and 12 on 2026-10-07
- **AND** the ideal line runs from 20 on 2026-10-05 to 0 on 2026-10-16 and the axis is labelled "Story points"

#### Scenario: The burndown has a table equivalent

- **GIVEN** the same member with a screen reader on the burndown of "Sprint 12"
- **WHEN** they move to "Show as table"
- **THEN** a table lists each day with its remaining and ideal values

#### Scenario: Tasks without a finish time are disclosed

- **GIVEN** a sprint holding one done task that has no `completedAt`
- **WHEN** a project member opens its burndown
- **THEN** that task is counted as finished on the sprint's last day
- **AND** the chart says "1 task has no finish date and is shown on the last day."

### Requirement: Moving a task to done records when it finished

Whenever the app changes a task's status to `done` it MUST set `completedAt` to the current date and time in the same write, and whenever it changes a task's status away from `done` it MUST clear `completedAt`. Tier: V1.

#### Scenario: Dragging a card to done stamps the finish time

- **GIVEN** a project member on the project board with the open task "Export to CSV"
- **WHEN** they move the card to the Done column
- **THEN** the task's status is `done` and its `completedAt` holds the time of the move

#### Scenario: Reopening a task clears the finish time

- **GIVEN** the done task "Export to CSV" with a `completedAt`
- **WHEN** a project member moves it back to In progress
- **THEN** its `completedAt` is empty
