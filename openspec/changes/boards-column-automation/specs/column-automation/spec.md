# column-automation delta for boards-column-automation

## ADDED Requirements

### Requirement: A project member can add rules to a board column

A project member MUST be able to add, change and remove rules on a column of their project's board, each rule saying what happens when a card enters that column: set the status, set the priority, assign a named project member, assign the person who moved the card, remove the assignee, or add a label. The rules dialog SHALL offer only valid values: members of this project, existing labels, and the task schema's status and priority values. Tier: V1 (docs/FEATURES.md, kanban board; this change adds the row).

#### Scenario: A member adds an assign rule to Review

- **GIVEN** a project member on the project board at /projects/:id with the column "Review"
- **WHEN** they open the "Review" column menu, choose "Rules", add "Assign the person who moved the card" and save
- **THEN** the "Review" column stores that rule
- **AND** its header shows an icon labelled "1 rule runs when a card enters this column"

#### Scenario: The dialog offers only project members

- **GIVEN** a project with members Anna and Ben, and a user Carl who is not a member
- **WHEN** a project member adds an "Assign to" rule
- **THEN** the person picker offers Anna and Ben and not Carl

### Requirement: Column rules run whenever a task enters the column

When a task's column changes, or a task is created in a column, the system MUST apply the rules of the column it enters in the same save, whichever client made the change. Rules SHALL run in their listed order, a later rule winning on the same field, and SHALL NOT run when the task's column did not change. A rule whose value is no longer valid, such as an assignee who left the project, MUST be skipped and logged without blocking the move. Tier: V1.

#### Scenario: Moving a card into Review assigns the mover

- **GIVEN** the "Review" column with the rule "Assign the person who moved the card" and the task "Export to CSV" assigned to Anna
- **WHEN** Ben drags "Export to CSV" into "Review" on the project board
- **THEN** the stored task is in "Review" and assigned to Ben, in one saved version
- **AND** the card shows Ben as assignee and the page announces "Rules applied: assigned to Ben"

#### Scenario: A move through the API runs the same rules

- **GIVEN** the "Blocked" column with the rule "Set priority to high"
- **WHEN** a project member's client sends PATCH /apps/openregister/api/objects/planninq/task/{id} with the "Blocked" column
- **THEN** the stored task has priority `high`

#### Scenario: Moving a card by keyboard applies the rules

- **GIVEN** a project member using the keyboard on the board and the "Review" rule from above
- **WHEN** they use the card's "Move task to another column" menu to move it to "Review"
- **THEN** the task is assigned to them

#### Scenario: A rule for a former member is skipped

- **GIVEN** the "Review" column with the rule "Assign to Carl" and Carl no longer a project member
- **WHEN** a project member moves a card into "Review"
- **THEN** the card moves and keeps its assignee
- **AND** the rules dialog marks the rule "No longer a project member"

#### Scenario: Editing a task without moving it runs nothing

- **GIVEN** a task in the "Review" column with an assign rule
- **WHEN** a project member changes only the task's title
- **THEN** the assignee is unchanged
