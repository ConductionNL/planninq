# backlog Specification

## Purpose
A project's backlog: the tasks in no board column, which members create into, rank, sort, filter and move to and from the board. Built by the archived change 2026-09-28-backlog-list.

## Requirements

### Requirement: The backlog lists the tasks that are not on the board

The system MUST show on /projects/:id/backlog every task of the project that has no column and is
not done, in rank order, and MUST let a project member create a task directly into it.
Tier: MVP (docs/FEATURES.md).

#### Scenario: Open the backlog

- **GIVEN** project "Vergunningen" has two tasks without a column and five on the board
- **WHEN** a member opens its backlog
- **THEN** the two column-less tasks are listed and the five board tasks are not

#### Scenario: Create into the backlog

- **GIVEN** a member is on the backlog
- **WHEN** they choose "New task", enter "Check the zoning plan" and save
- **THEN** the task is listed last in the backlog and does not show on the board

### Requirement: A member can rank the backlog

The system MUST let a project member put backlog tasks in their own order by dragging or with
"Move up" and "Move down", and MUST keep that order after a reload. Tier: V1.

#### Scenario: Rank with the keyboard

- **GIVEN** the backlog lists A, B, C
- **WHEN** a member chooses "Move up" on C twice
- **THEN** the backlog lists C, A, B, also after a reload

### Requirement: A member can sort and filter the backlog

The system MUST let a project member sort the backlog by rank, priority, due date or creation date
and filter it with the board's filters, and MUST show cancelled tasks only under the "Cancelled"
filter. Tier: MVP.

#### Scenario: Sort by due date

@e2e exclude the backlog has no due-date field to set from the UI yet; the comparator is covered by tests/vitest/backlog.spec.js (sortBacklog)

- **GIVEN** backlog tasks due on 3, 1 and 2 October
- **WHEN** a member sorts by due date
- **THEN** they are listed 1, 2, 3 October
- **AND** dragging is disabled with the hint "Sort by rank to reorder"

### Requirement: A member can move tasks between the backlog and the board

The system MUST let a project member move a backlog task to a board column of their choice, and
move a board card back to the backlog. Tier: MVP.

#### Scenario: Plan a task

- **GIVEN** "Check the zoning plan" is in the backlog
- **WHEN** a member chooses "Move to board" and then "In progress"
- **THEN** the task leaves the backlog and shows at the bottom of the In progress lane with that lane's status

#### Scenario: Take a card off the board

- **GIVEN** a card in the To do lane
- **WHEN** a member chooses "Move to backlog" in its card menu
- **THEN** the card leaves the board and is listed last in the backlog
