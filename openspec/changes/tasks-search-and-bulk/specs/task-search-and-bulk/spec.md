# task-search-and-bulk delta for tasks-search-and-bulk

Extends the flat main spec `openspec/specs/tasks.md` ("Task Search", "Bulk Task Operations").

## ADDED Requirements

### Requirement: A project member can search the tasks of a board

The system MUST offer a search field on the project board and on the backlog that narrows the
visible tasks, without a page reload, to those whose title, description or key contains the
term, ignoring case. Tier: MVP (docs/FEATURES.md).

#### Scenario: Search the board

- **GIVEN** a project member is on a board with the tasks "Draft the permit letter" and "Call the applicant"
- **WHEN** they type "PERMIT" in the board's search field
- **THEN** only the "Draft the permit letter" card is shown
- **AND** each lane's count shows the number of matching cards

#### Scenario: Clear the search

- **GIVEN** a search term narrows the board
- **WHEN** the member presses Escape in the search field
- **THEN** every task shows again

### Requirement: A project member can change several backlog tasks at once

The system MUST let a project member select several tasks on the backlog list and change their
status, assignee or priority, or add or remove a label, in one action. It MUST report how many
tasks were updated and MUST keep failed tasks selected. Tier: V1.

#### Scenario: Bulk status change

- **GIVEN** a project member selected three tasks on the backlog
- **WHEN** they choose "Change status" and then "In progress"
- **THEN** all three tasks have status `in_progress`
- **AND** a message says "3 tasks updated"

#### Scenario: One task fails

- **GIVEN** a member selected three tasks and one of them was deleted by someone else meanwhile
- **WHEN** they assign the selection to Bram
- **THEN** the message says two tasks were updated and one could not be
- **AND** the failed task stays selected
