# case-bridge Specification

## Purpose
A case handler starts a planninq project from a case page, with the case linked, and a project owner hands the project's files and metadata over to its case unchanged, with the checksums recorded on the project.

## Requirements

### Requirement: A case handler can start a project from a case page

The system MUST show planninq's Projects panel on a case page of the case app, listing the projects
linked to that case, and its "New project" action MUST open planninq's New project dialog with the
case linked and the case title filled in. Tier: V1 (docs/FEATURES.md).

#### Scenario: Start a project from a case
@e2e exclude Needs Dossiq's case page with planninq's leaf placed on it (the Dossiq issue named in the proposal); the case scope is asserted by tests/vitest/caseBridge.spec.js "asks for the projects linked to the case, and keeps only those" and the link by "links the case and its title from a case page"

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
@e2e exclude Needs Dossiq installed next to planninq; asserted by CaseHandoverControllerTest::testOwnerHandsOverFilesAndMetadataUnchanged and testChecksumMismatchFailsThatFile

- **GIVEN** a project linked to a case holds three files on its tasks, and its owner may write to the case
- **WHEN** the owner chooses "Hand over to case" in the project settings
- **THEN** the case holds the three files and `project-metadata.json`
- **AND** each copied file has the same SHA-256 as its source
- **AND** the project lists the handover with date, person and the four checksums

#### Scenario: No access to the case
@e2e exclude Needs Dossiq installed next to planninq; asserted by CaseHandoverControllerTest::testNothingIsCopiedWithoutAReachableCase

- **GIVEN** the owner of a linked project cannot open the case (the rule OpenRegister's own files API applies before it adds a file to an object)
- **WHEN** they choose "Hand over to case"
- **THEN** nothing is copied and they see "The linked case could not be found, or you cannot open it."

#### Scenario: The case app is not installed
@e2e exclude The e2e instance has no Dossiq, so the hidden action is the default there; asserted by tests/vitest/caseBridge.spec.js "is hidden without the case app, without a case link, or for anyone else" and CaseHandoverControllerTest::testNothingIsCopiedWithoutAReachableCase

- **GIVEN** the case app is not installed on the instance
- **WHEN** a project owner opens the project settings
- **THEN** no "Hand over to case" action is shown
