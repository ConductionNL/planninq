# board-columns delta for boards-configurable-columns

Extends the flat main specs `openspec/specs/kanban-board.md`, `openspec/specs/tasks.md` and
`openspec/specs/projects.md` (Default Column Creation).

## ADDED Requirements

### Requirement: The board shows the project's own columns

The system MUST render a project's board from that project's `column` objects in their `order`,
placing each task in the column it references. Tier: MVP (docs/FEATURES.md).

#### Scenario: A project with a Review column

- **GIVEN** project "Vergunningen" has columns To do, In progress, Review and Done
- **WHEN** a member opens its board at /projects/:id
- **THEN** four lanes show in that order with their titles

### Requirement: The project owner can manage the columns

The system MUST let the project owner add, rename, reorder, recolour and remove columns, and MUST
refuse these actions to other members. Removing a column that holds cards MUST first move them to
a column the owner picks or to the backlog, and the last done column MUST NOT be removable.
Tier: MVP.

#### Scenario: Add and rename a column

- **GIVEN** the owner is on the board
- **WHEN** they choose "Add column", name it "Waiting for applicant", then rename it "Waiting"
- **THEN** a lane "Waiting" shows at the end of the board

#### Scenario: Remove a column with cards

- **GIVEN** the column "Review" holds two cards
- **WHEN** the owner removes it and picks "In progress" as the target
- **THEN** both cards show in "In progress" and the "Review" lane is gone

#### Scenario: A member cannot change columns

@e2e exclude API permission contract, covered by PHPUnit on ColumnOwnerGuardListener (tests/unit/Listener/ColumnOwnerGuardListenerTest.php)

- **GIVEN** a member who is not the owner
- **WHEN** they open a lane header
- **THEN** no column actions are offered
- **AND** an update of a column object through the API answers 403

### Requirement: A done column finishes the task

The system MUST let the owner mark a column as the done column. A card moved into it MUST get
status `done`. Whenever a task's status becomes `done`, by any client, the server MUST set its
`completedAt` in the same save, and MUST clear it when the status leaves `done`. A card moved
into any other column MUST get that column's mapped status. Tier: MVP.

#### Scenario: Drop a card on Done

- **GIVEN** a task in progress
- **WHEN** a member drags its card to the Done column
- **THEN** the task has status `done` and `completedAt` set to the time of the move

#### Scenario: A move through the API is stamped too

@e2e exclude server-side pre-save stamp, covered by PHPUnit on TaskCompletionListener (tests/unit/Listener/TaskCompletionListenerTest.php)

- **GIVEN** a flow sets a task's status to `done` through the Open Register objects API
- **WHEN** the save finishes
- **THEN** the stored task carries a `completedAt` time from that same save

### Requirement: Lanes show their WIP limit without blocking

The system MUST show a lane's card count against its WIP limit, MUST switch the header to a
warning style with the words "over limit" when the count exceeds it, and MUST still accept the
drop. Tier: MVP.

#### Scenario: Go over the limit

- **GIVEN** the In progress column has a WIP limit of 3 and holds 3 cards
- **WHEN** a member drags a fourth card into it
- **THEN** the card lands in the lane
- **AND** the header reads "4 / 3 over limit" in the warning style

### Requirement: Cards keep the order a member gives them

The system MUST store a card's position within its lane and show cards in that order, and MUST
offer "Move up" and "Move down" in the card menu as the keyboard equivalent of dragging.
Tier: MVP.

#### Scenario: Reorder with the keyboard

- **GIVEN** a lane with cards A above B
- **WHEN** a member opens B's card menu and chooses "Move up"
- **THEN** B shows above A, also after a reload

### Requirement: New projects start with the admin's default columns

The system MUST create a new project's columns from the admin's default column setting, with the
last column as the done column, and MUST fall back to To do, In progress, Review and Done when
the setting is empty. Tier: MVP.

#### Scenario: The admin changed the defaults

@e2e exclude needs an admin setting change mid-suite; covered by PHPUnit on BoardColumnService and ProjectController (tests/unit/Service/BoardColumnServiceTest.php)

- **GIVEN** an admin set the default columns to Intake, Work and Closed
- **WHEN** a user creates a project
- **THEN** its board shows Intake, Work and Closed, with Closed as the done column

### Requirement: Existing boards keep their cards

The system MUST, once, give every task without a column that is not cancelled the column that
matches its status, so no card that showed before the change disappears from the board.
Tier: MVP.

#### Scenario: An upgraded board

@e2e exclude one-shot upgrade repair step, covered by PHPUnit on AssignBoardColumns (tests/unit/Repair/AssignBoardColumnsTest.php)

- **GIVEN** a project whose tasks have statuses open, in_progress and done and no column
- **WHEN** the app upgrade runs its repair steps
- **THEN** each task sits in the matching column and the board shows all three
