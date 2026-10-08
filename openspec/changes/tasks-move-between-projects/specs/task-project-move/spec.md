# task-project-move delta for tasks-move-between-projects

Extends the flat main spec `openspec/specs/tasks.md` ("Move task to another project").

## ADDED Requirements

### Requirement: A member of two projects can move a task between them

The system MUST let a user who is a member of both projects move a task from one to the other.
The moved task MUST land in the target project's backlog, its subtasks MUST move with it, and
people who are not members of the target MUST be cleared after the user confirms. Tier: MVP
(docs/FEATURES.md).

#### Scenario: Move a task

- **GIVEN** Anna is a member of "Vergunningen" and "Handhaving" and is on the task page of a task in "Vergunningen"
- **WHEN** she chooses "Move to project", picks "Handhaving" and confirms
- **THEN** the task's project is "Handhaving" and it has no column
- **AND** it is gone from the "Vergunningen" board and shows in the "Handhaving" backlog

#### Scenario: Subtasks follow their parent

- **GIVEN** a task with two subtasks
- **WHEN** a member moves it to another project
- **THEN** both subtasks belong to the target project too

#### Scenario: Only projects the user belongs to are offered

- **GIVEN** Anna is not a member of "Financien"
- **WHEN** she opens "Move to project"
- **THEN** "Financien" is not in the list

### Requirement: Moving a task removes its dependency links

The system MUST remove every dependency link of a task when its project changes, in the same
write, and MUST show the links to be removed before the user confirms. Tier: V1.

#### Scenario: Links are named and removed

- **GIVEN** a task that blocks one other task in its project
- **WHEN** a member opens "Move to project"
- **THEN** the dialog names the link that will be removed
- **AND** after the move no dependency link references the task

### Requirement: Booked time stays on the project it was booked on

The system MUST NOT rewrite the project of time already logged on a moved task, and MUST show on
the task page which time was logged under a different project. Tier: V1.

#### Scenario: Time logged before a move

- **GIVEN** Bram logged two hours on a task in "Vergunningen", which was then moved to "Handhaving"
- **WHEN** a member opens the task page
- **THEN** the time section shows "2h logged under Vergunningen"

### Requirement: A project member can move or copy a column to another project

The system MUST let a project member move a board column with its tasks to another project they
are a member of, or copy it with copies of its tasks. A moved or copied column MUST be appended as
the last column of the target board. Tier: V1.

#### Scenario: Copy a column

- **GIVEN** a column "Intake" with three tasks on the "Vergunningen" board
- **WHEN** a member chooses "Copy column to project" and picks "Handhaving"
- **THEN** the "Handhaving" board ends with a column "Intake" holding three open copies without assignees
- **AND** the "Vergunningen" board is unchanged
