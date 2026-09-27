# project-phases delta for planning-phase-gate-document

## ADDED Requirements

### Requirement: A project member can manage the phases of a project

Each project MUST have a phases page at /projects/:id/phases, reached from the project board header, that lists the project's phases in order with their status, planned dates and budget hours. A project member SHALL be able to add a phase, edit it and move it up or down with the keyboard. Only members of the project and admins SHALL see its phases. Tier: V1 (docs/FEATURES.md, project management: project milestones).

#### Scenario: A member opens the phases page from the board

- **GIVEN** a project member on the project board at /projects/:id
- **WHEN** they press "Phases" in the board header
- **THEN** the phases page opens with the project's name in its breadcrumb

#### Scenario: A member adds a phase

- **GIVEN** a project member on the phases page of a project with no phases
- **WHEN** they choose "Add phase", enter "Ontwerp" with planned dates 2026-10-01 to 2026-11-30 and save
- **THEN** a `projectPhase` object for this project exists with status `open`
- **AND** the page lists "Ontwerp" with those dates

#### Scenario: A member reorders phases with the keyboard

- **GIVEN** the phases "Ontwerp" then "Realisatie" on the phases page
- **WHEN** a project member focuses "Realisatie" and chooses "Move up"
- **THEN** "Realisatie" is listed first and the phases' `order` values are swapped

### Requirement: A project member can keep documents on a phase

A project member MUST be able to upload documents to a phase and see, download and remove them from the phase's side panel. The documents SHALL be stored as files on the phase object through OpenRegister's object files API. Tier: V1.

#### Scenario: A member uploads a document to a phase

- **GIVEN** a project member on the phases page
- **WHEN** they select the phase "Ontwerp" and upload "Ontwerpbesluit.pdf" in the Files tab of its side panel
- **THEN** the file is listed on the phase
- **AND** the phase's audit trail records the upload

### Requirement: A phase can only be completed with a concluding document

The `projectPhase` schema MUST declare a lifecycle on `status` whose transition to `completed` requires planninq's concluding-document guard, and OpenRegister SHALL reject any update that sets `status` to `completed` unless the phase's `concludingDocument` names a file attached to that phase. The rejection MUST carry the message "Upload the concluding document before you close this phase.". The rule SHALL apply to every client, not only the phases page. Tier: V1.

#### Scenario: Closing without a document explains what is needed

- **GIVEN** a project member on the phases page and the phase "Ontwerp" with no files
- **WHEN** they choose "Close phase" and confirm without picking a document
- **THEN** the phase stays `in_progress`
- **AND** the dialog says "Upload the concluding document before you close this phase."

#### Scenario: Closing with a concluding document completes the phase

- **GIVEN** the phase "Ontwerp" with the attached file "Ontwerpbesluit.pdf"
- **WHEN** a project member chooses "Close phase", picks "Ontwerpbesluit.pdf" as the concluding document and confirms
- **THEN** the phase has status `completed` and `concludingDocument` names that file
- **AND** the phases page shows "Ontwerpbesluit.pdf" as the phase's concluding document

#### Scenario: The API refuses a close without a document

- **GIVEN** a project member's client and the phase "Realisatie" with no concluding document
- **WHEN** it sends PATCH /apps/openregister/api/objects/planninq/projectPhase/{id} with `status: completed`
- **THEN** OpenRegister answers 403 with the guard's message
- **AND** the phase's status is unchanged

#### Scenario: A document that is not on the phase does not count

- **GIVEN** the phase "Realisatie" and a file attached to a different phase
- **WHEN** a client sets `concludingDocument` to that other file's id and `status` to `completed`
- **THEN** OpenRegister rejects the update

### Requirement: A completed phase whose document is gone is flagged

The phases page MUST flag a completed phase whose concluding document is no longer attached to it with the text "Concluding document missing". Tier: V1.

#### Scenario: A removed concluding document is flagged

- **GIVEN** the completed phase "Ontwerp" whose file "Ontwerpbesluit.pdf" was removed afterwards
- **WHEN** a project member opens the phases page
- **THEN** "Ontwerp" shows "Concluding document missing"
