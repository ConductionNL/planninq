# shared-boards delta for boards-cross-project-board

## ADDED Requirements

### Requirement: A user can create a board across several of their projects

A signed-in user MUST be able to create a shared board with a name and between one and twenty projects, chosen only from projects they are a member of, and SHALL be its owner. Only the owner and admins SHALL be able to rename it, change its projects or people, or delete it. Tier: V1 (docs/FEATURES.md, kanban board; this change adds the row).

#### Scenario: A user creates a shared board from two projects

- **GIVEN** a user who is a member of the projects "Servers" and "Workplace"
- **WHEN** they choose "New shared board" on the Borden page, name it "IT operations", pick both projects and save
- **THEN** a `board` object with that title, both project ids and the user as owner exists
- **AND** the Borden page lists "IT operations" under "Shared boards"

#### Scenario: The project picker offers only member projects

- **GIVEN** a user who is a member of "Servers" but not of "Finance"
- **WHEN** they open the project picker in the shared board dialog
- **THEN** "Servers" is offered and "Finance" is not

### Requirement: The owner can share a board with other people

The owner MUST be able to add and remove people on a shared board. A person it is shared with SHALL find it on their Borden page and SHALL be able to open it, but SHALL NOT be able to change it. Tier: V1.

#### Scenario: A shared board appears for the person it is shared with

- **GIVEN** the owner of "IT operations" who adds Ben to it
- **WHEN** Ben opens the Borden page
- **THEN** "IT operations" is listed under "Shared boards" for Ben
- **AND** Ben's board page offers no "Edit" or "Delete"

### Requirement: A shared board shows its projects' tasks in status columns

A shared board MUST show the tasks of all its projects that the viewer can read, grouped in the task status columns, with each card naming its project in text. A viewer SHALL be able to move a card to another status by dragging it or with the card's move menu, which changes the task's status in its own project. Tier: V1.

#### Scenario: The board shows tasks of two projects with project chips

- **GIVEN** a member of both "Servers" and "Workplace", and the board "IT operations" showing both
- **WHEN** they open the board at /boards/:id
- **THEN** open tasks of both projects appear in the Open column
- **AND** each card shows its project's name

#### Scenario: Moving a card changes its status in its own project

- **GIVEN** the same viewer and the open task "Patch mail server" of "Servers" on the board
- **WHEN** they drag it to In progress
- **THEN** the task has status `in_progress`
- **AND** the "Servers" project board shows it in In progress

#### Scenario: The keyboard move works on a shared board

- **GIVEN** the same viewer using only the keyboard, with focus on the card "Patch mail server"
- **WHEN** they open "Move task to another column" and choose Done
- **THEN** the task has status `done`

### Requirement: A shared board never shows a task its project would hide

The system MUST read a shared board's tasks with the viewer's own rights, so a viewer SHALL see no task of a project they are not a member of. The board MUST say how many of its projects are hidden from the viewer, without naming them. Tier: V1.

#### Scenario: A viewer outside a project sees the hidden-projects notice

- **GIVEN** the board "IT operations" showing "Servers" and "Workplace", shared with Carl, who is a member of "Workplace" only
- **WHEN** Carl opens the board
- **THEN** he sees the tasks of "Workplace" and none of "Servers"
- **AND** the board says "1 project on this board is hidden from you."

#### Scenario: A person the board is not shared with cannot read it

- **GIVEN** the board "IT operations" and a user who is neither its owner nor shared on it
- **WHEN** their client requests GET /apps/openregister/api/objects/planninq/board/{id}
- **THEN** OpenRegister refuses the read
