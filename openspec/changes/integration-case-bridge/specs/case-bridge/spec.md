# case-bridge delta for integration-case-bridge

Extends the flat main spec `openspec/specs/procest-integration.md`.

## ADDED Requirements

### Requirement: A case handler can start a project from a case page

The system MUST show planninq's Projects panel on a case page of the case app, listing the projects
linked to that case, and its "New project" action MUST open planninq's New project dialog with the
case linked and the case title filled in. Tier: V1 (docs/FEATURES.md).

#### Scenario: Start a project from a case

- **GIVEN** a case handler allowed to create projects is on the page of case "Omgevingsvergunning Markt 12" in the case app
- **WHEN** they choose "New project" in the Projects panel and create the project
- **THEN** a project exists with `caseReference` set to that case
- **AND** the Projects panel on the case page lists it

#### Scenario: A link opens the dialog prefilled

- **GIVEN** a user follows /apps/planninq/projects?new=1&case={uuid}&title=Markt%2012
- **WHEN** the Projects page loads
- **THEN** the New project dialog is open with the title "Markt 12" and the linked case shown

### Requirement: The project owner can hand a project's record over to its case

The system MUST let the owner of a project linked to a case copy every file on the project and its
tasks, and a metadata file describing the project, to that case without changing a byte, and MUST
record the handover on the project with each file's checksum. Tier: Enterprise.

#### Scenario: Hand over a finished project

- **GIVEN** a project linked to a case holds three files on its tasks, and its owner may write to the case
- **WHEN** the owner chooses "Hand over to case" in the project settings
- **THEN** the case holds the three files and `project-metadata.json`
- **AND** each copied file has the same SHA-256 as its source
- **AND** the project lists the handover with date, person and the four checksums

#### Scenario: No right to write to the case

- **GIVEN** the owner of a linked project cannot write to the case
- **WHEN** they choose "Hand over to case"
- **THEN** nothing is copied and they see that they have no access to the case

#### Scenario: The case app is not installed

- **GIVEN** the case app is not installed on the instance
- **WHEN** a project owner opens the project settings
- **THEN** no "Hand over to case" action is shown
