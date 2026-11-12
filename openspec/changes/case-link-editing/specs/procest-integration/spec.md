# procest-integration delta for case-link-editing

## ADDED Requirements

### Requirement: A project owner sets the case link of a project on screen

The project settings sidebar MUST let a project owner or manager pick a case from the case app,
change it and clear it, and MUST store the choice as the project's `caseReference`. A project
with a case link MUST show a case badge on the project list and on its overview, and the badge
MUST link to the case. Other members MUST see the link but MUST NOT change it.

#### Scenario: An owner links a project to a case

- **GIVEN** a project owner on the Details tab of the project settings sidebar at /projects/:id
- **WHEN** the owner picks the case "Omgevingsvergunning Markt 12" in the "Case" field and saves
- **THEN** the project stores that case's id as `caseReference`
- **AND** the project list at /projects shows the badge "Case: Omgevingsvergunning Markt 12"
- **AND** choosing the badge opens the case in the case app

#### Scenario: An owner clears the link

- **GIVEN** a project linked to a case
- **WHEN** the owner clears the "Case" field and saves
- **THEN** the project has no `caseReference` and no case badge

#### Scenario: A member cannot change the link

- **GIVEN** a project member who is not an owner or manager
- **WHEN** the member opens the Details tab
- **THEN** the linked case is shown as a link without an edit control

### Requirement: A project member sets the case link of a task on the task page

The task page MUST show a "Case" field that lets a project member pick, change and clear a case
from the case app, stored as the task's `zaakUuid`. A task with a case link MUST show the case as
a link on its task page.

#### Scenario: A member links a task to a case

- **GIVEN** a project member on the task page of "Check the drawings"
- **WHEN** the member picks the case "Omgevingsvergunning Markt 12" in the "Case" field
- **THEN** the task stores that case's id as `zaakUuid`
- **AND** the task page shows the case name as a link to the case

#### Scenario: The case app is not installed

- **GIVEN** an instance without the case app
- **WHEN** a project member opens a task page
- **THEN** no "Case" field is shown
