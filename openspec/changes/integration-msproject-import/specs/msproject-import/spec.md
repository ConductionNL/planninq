# msproject-import delta for integration-msproject-import

## ADDED Requirements

### Requirement: A project owner can preview a Microsoft Project plan before importing it

The project owner, or an admin, MUST be able to upload a plan saved from Microsoft Project in its XML format on the project's timeline and see, before anything is written, how many phases, tasks, sub-tasks, milestones and links the import will create, and every part of the plan it cannot carry over. The preview SHALL write nothing. Other project members SHALL NOT be offered the import. Tier: V1 (docs/FEATURES.md, integration; this change adds the row).

#### Scenario: The owner previews a contractor plan

- **GIVEN** the owner of project "Renovatie stadhuis" on its timeline at /projects/:id/timeline
- **WHEN** they choose "Import from Microsoft Project" and select the contractor's plan saved as XML
- **THEN** the dialog shows, for example, "4 phases, 38 tasks, 6 sub-tasks, 3 milestones, 41 links"
- **AND** it lists what will not carry over, such as "12 tasks had resources, which are not imported"
- **AND** the project has no new objects yet

#### Scenario: A member who is not the owner cannot import

- **GIVEN** a project member who is not the project owner
- **WHEN** they open the project's timeline
- **THEN** there is no "Import from Microsoft Project" button
- **AND** a request from their client to the import endpoint is refused

### Requirement: Confirming the import builds the plan in the project

When the owner confirms, the system MUST create the plan in the project: top-level summary tasks as phases, their tasks with the phase set, deeper tasks as sub-tasks of their level-2 task, milestones as tasks whose start and due dates are equal, dates, estimated durations, progress and notes on each task, and finish-to-start links as blocking dependencies. Other link types SHALL become related links. The timeline SHALL then show the imported tasks and links. Tier: V1.

#### Scenario: The owner imports the plan and sees it on the timeline

- **GIVEN** the previewed plan with the phase "Ruwbouw" containing "Fundering" (1 to 12 March 2027) followed finish-to-start by "Metselwerk"
- **WHEN** the owner chooses "Import"
- **THEN** the project has a phase "Ruwbouw" with the tasks "Fundering" and "Metselwerk" in it
- **AND** the timeline shows "Fundering" from 1 to 12 March 2027 with an arrow to "Metselwerk"

### Requirement: The import refuses unsafe or oversized files

The system MUST refuse a file that declares a DOCTYPE or external entities, a file larger than 10 MB, and a plan with more than 2,000 tasks, before any mapping, and SHALL say why. A binary `.mpp` file SHALL be refused with the hint to save the plan as XML in Microsoft Project. Tier: V1.

#### Scenario: An mpp file is refused with a hint

- **GIVEN** the project owner in the import dialog
- **WHEN** they select "planning.mpp"
- **THEN** the dialog says "Save the plan in Microsoft Project with File, Save as, XML format, and import that file."

### Requirement: Importing a newer version updates the earlier import

When a plan is imported into a project that already holds objects from an earlier import of the same plan, the system MUST update the objects whose Microsoft Project task UID matches, create the new ones, and list, without deleting them, the objects whose UID is no longer in the file. Running the same import twice SHALL create no duplicates. Tier: V1.

#### Scenario: A newer plan moves a task instead of duplicating it

- **GIVEN** a project with "Fundering" imported earlier from 1 to 12 March 2027
- **WHEN** the owner imports the contractor's new version where "Fundering" runs 8 to 19 March 2027
- **THEN** the same "Fundering" task now runs from 8 to 19 March 2027 and there is only one "Fundering"

#### Scenario: A task dropped from the plan is listed, not deleted

- **GIVEN** a project with an imported task "Asbestsanering"
- **WHEN** the owner imports a newer file without it
- **THEN** the result lists "Asbestsanering" under "No longer in the plan"
- **AND** the task still exists
