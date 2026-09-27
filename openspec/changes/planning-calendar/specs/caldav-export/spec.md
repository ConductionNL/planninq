# caldav-export delta for planning-calendar

## ADDED Requirements

### Requirement: A user can show their assigned tasks in Nextcloud Tasks

The personal settings MUST offer a switch "Show my tasks in Nextcloud Tasks" that is off by default. When a user switches it on, the system SHALL create a task list named "Planninq" in that user's calendar home and export every task assigned to them to it as a VTODO, in the background. When they switch it off, the system SHALL remove the "Planninq" list. Tier: V1 (docs/FEATURES.md, integration: CalDAV/VTODO export).

#### Scenario: Switching the export on fills the Planninq list

- **GIVEN** a user assigned to three planninq tasks, with the export switched off
- **WHEN** they switch on "Show my tasks in Nextcloud Tasks" in the planninq personal settings
- **THEN** after the background job runs, the Nextcloud Tasks app shows a "Planninq" list with those three tasks and their due dates

#### Scenario: Switching the export off removes the list

- **GIVEN** a user with the export on and a "Planninq" list in their Tasks app
- **WHEN** they switch the export off
- **THEN** the "Planninq" list is gone from their calendar home

#### Scenario: The export is off by default

- **GIVEN** a user who never opened the planninq personal settings
- **WHEN** a task is assigned to them
- **THEN** no "Planninq" list is created in their calendar home

### Requirement: The exported VTODO follows the task, one way

For every user with the export on, the system MUST write a VTODO for each task assigned to them whenever the task is created or changed, MUST remove it from a user's list when the task is reassigned away from them or deleted, and SHALL map title, description, status, priority, start date, due date, percent complete and completion time to the matching VTODO properties. The VTODO MUST carry a link back to the task and the text "Managed by Planninq. Changes made here are replaced by the next change in Planninq.". Changes made to the VTODO outside planninq MUST NOT be read back. A failure to write the VTODO MUST NOT fail the task save. Tier: V1.

#### Scenario: A completed task is completed in the Tasks app

- **GIVEN** a user with the export on and the task "Export to CSV" in their "Planninq" list
- **WHEN** a project member moves "Export to CSV" to done on the project board
- **THEN** the VTODO has STATUS COMPLETED and a COMPLETED time

#### Scenario: Reassignment moves the VTODO

- **GIVEN** "Export to CSV" assigned to Anna, both Anna and Ben with the export on
- **WHEN** a project member reassigns the task to Ben
- **THEN** the VTODO is gone from Anna's "Planninq" list and present in Ben's

#### Scenario: An edit in the Tasks app is replaced by the next planninq change

- **GIVEN** a user who renamed the exported VTODO "Export to CSV" to "Export" in the Tasks app
- **WHEN** a project member changes the task's due date in planninq
- **THEN** the VTODO's summary is "Export to CSV" again and carries the new due date
- **AND** the task's title in planninq never changed to "Export"

#### Scenario: A failed export does not block the save

- **GIVEN** a user with the export on whose calendar write fails
- **WHEN** a project member changes a task assigned to them
- **THEN** the task change is saved and the failure is logged

### Requirement: The task stores the UID of its VTODO

The first export of a task MUST store the VTODO's UID in the task's `calendarEventUid`, and later exports of that task SHALL reuse it for every assignee. Storing the UID SHALL NOT trigger another export. Tier: V1.

#### Scenario: The UID is stored once

- **GIVEN** a task with an empty `calendarEventUid`, assigned to a user with the export on
- **WHEN** the task is exported for the first time
- **THEN** its `calendarEventUid` holds the VTODO's UID
- **AND** no second export runs because of that write
