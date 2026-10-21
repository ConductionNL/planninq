# cross-project-view Specification

## Purpose
A cross-project view is a saved selection of up to twenty projects whose tasks are shown together in status lanes. It holds no columns, card order or tasks of its own: every project keeps its one board, reads and writes run with the viewer's own rights, and a move goes through the task's own project columns. Built by change 2026-09-30-boards-cross-project-board.

## Requirements

### Requirement: A user can save a view across several of their projects

A signed-in user MUST be able to save a cross-project view with a name and between one and twenty projects, chosen only from projects they are a member of, and SHALL be its owner. A view SHALL store only its name, owner, people and project ids: no columns, card order or tasks of its own, so every project keeps its one board. Only the owner and admins SHALL be able to rename it, change its projects or people, or delete it. Tier: V1 (docs/FEATURES.md, kanban board; this change adds the row).

#### Scenario: A user saves a view of two projects

- **GIVEN** a user who is a member of the projects "Servers" and "Workplace"
- **WHEN** they choose "New view" on the Borden page, name it "IT operations", pick both projects and save
- **THEN** a `boardView` object with that title, both project ids and the user as owner exists
- **AND** the Borden page lists "IT operations" under "Cross-project views"

#### Scenario: The project picker offers only member projects

- **GIVEN** a user who is a member of "Servers" but not of "Finance"
- **WHEN** they open the project picker in the view dialog
- **THEN** "Servers" is offered and "Finance" is not

### Requirement: The owner can share a view with other people

The owner MUST be able to add and remove people on a view. A person it is shared with SHALL find it on their Borden page and SHALL be able to open it, but SHALL NOT be able to change it. A person it is not shared with SHALL NOT be able to read it. Tier: V1.

#### Scenario: A view appears for the person it is shared with

- **GIVEN** the owner of "IT operations" who adds Ben to it
- **WHEN** Ben opens the Borden page
- **THEN** "IT operations" is listed under "Cross-project views" for Ben
- **AND** Ben's view page offers no "Edit" or "Delete"

@e2e exclude needs a second account the CI e2e run does not have; the member read rule is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testBoardViewAuthorization and the Newman request "view member can read it", the hidden Edit and Delete by tests/vitest/projectsView.spec.js "only the owner and admins manage a view", and the member's refused PATCH by the Newman request "view member cannot edit it"

#### Scenario: A person the view is not shared with cannot read it

- **GIVEN** the view "IT operations" and a user who is neither its owner nor shared on it
- **WHEN** their client requests GET /apps/openregister/api/objects/planninq/boardView/{id}
- **THEN** OpenRegister refuses the read

@e2e exclude an API refusal for a second account; asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testBoardViewAuthorization and the Newman request "non-member cannot read a cross-project view" in tests/integration/planninq.postman_collection.json

### Requirement: A view shows its projects' tasks in status lanes

A cross-project view MUST show the tasks of all its projects that the viewer can read, grouped in lanes by task status, with each card naming its project in text, and SHALL offer the same filter bar as a project board. Tier: V1.

#### Scenario: The view shows tasks of two projects with project chips

- **GIVEN** a member of both "Servers" and "Workplace", and the view "IT operations" showing both
- **WHEN** they open the view at /boards/views/:id
- **THEN** open tasks of both projects appear in the Open lane
- **AND** each card shows its project's name

### Requirement: A move on a view goes through the task's own project board

Moving a card to another lane on a view MUST place the task in the first column, by order, of its own project whose mapped status is that lane's status, and SHALL write the same column, card order and status that a move on that project's board writes, with the viewer's own rights. When the task's project has no column for that status, the system MUST put the card back and say which project has no such column. Tier: V1.

#### Scenario: Moving a card places it in its project's column

- **GIVEN** the view "IT operations", the open task "Patch mail server" of "Servers", and "Servers" columns "To do" (open), "Doing" (in_progress) and "Done" (done)
- **WHEN** a member of "Servers" drags the card to the In progress lane
- **THEN** the task's column is "Doing" and its status is `in_progress`
- **AND** the "Servers" project board shows it at the end of "Doing"

#### Scenario: The keyboard move works on the view

- **GIVEN** the same member using only the keyboard, with focus on the card "Patch mail server"
- **WHEN** they open "Move task to another column" and choose Done
- **THEN** the task's column is "Done" and its status is `done`

#### Scenario: A project without a matching column refuses the move

- **GIVEN** the task "Order laptops" of "Workplace", whose columns map only open, in_progress and done
- **WHEN** a member drags it to the Blocked lane of the view
- **THEN** the card returns to its lane and the task is unchanged
- **AND** the view says "Workplace has no column for Blocked."

### Requirement: A view never shows or moves a task its project would hide

The system MUST read and write a view's tasks with the viewer's own rights, so a viewer SHALL see and move no task of a project they are not a member of. The view MUST say how many of its projects are hidden from the viewer, without naming them. Tier: V1.

#### Scenario: A viewer outside a project sees the hidden-projects notice

- **GIVEN** the view "IT operations" showing "Servers" and "Workplace", shared with Carl, who is a member of "Workplace" only
- **WHEN** Carl opens the view
- **THEN** he sees the tasks of "Workplace" and none of "Servers"
- **AND** the view says "1 project in this view is hidden from you."
