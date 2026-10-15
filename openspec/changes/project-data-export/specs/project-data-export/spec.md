# project-data-export delta for project-data-export

## ADDED Requirements

### Requirement: A project owner or manager exports the whole project

The project settings sidebar MUST offer an "Export project" action to the project's owners and managers, and MUST NOT show it to members or viewers. Pressing it MUST queue a background export and MUST tell the user the zip will appear in their Files. The endpoint behind it, `POST /apps/planninq/api/projects/{id}/export`, MUST answer 403 to a caller who is not an owner or manager of that project, and 202 otherwise.

#### Scenario: A manager starts an export

- **GIVEN** a manager of project "Herinrichting Marktplein" on the Danger zone tab of the project settings sidebar
- **WHEN** the manager presses "Export project" and confirms
- **THEN** the dialog closes and the page says "The export is running. You will get a notification when it is in your Files."
- **AND** a background job for that project and that manager is queued

#### Scenario: A member does not see the action

- **GIVEN** a member of the project who is not an owner or manager
- **WHEN** the member opens the project settings sidebar
- **THEN** no "Export project" action is shown

#### Scenario: The endpoint refuses a member

@e2e exclude The 403 is asserted on the API by the Newman request of task 2.2; the UI hides the action, covered above
- **GIVEN** a member who is not an owner or manager
- **WHEN** the member sends `POST /apps/planninq/api/projects/{id}/export`
- **THEN** the answer is 403 and no job is queued

### Requirement: The export is one zip with every object, file and comment of the project

The export job MUST write one zip into the requester's Files at `Planninq exports/<project key>-<yyyy-mm-dd-hhmm>.zip`. The zip MUST hold `project.json`; one `<schema>.json` per project-scoped schema with every object of that project the requester may read, in stored values; `tasks.csv` with rendered values; `comments.json` with every comment on the project's tasks; the files attached to tasks under `attachments/<task key>/` and to phases under `phase-documents/<phase title>/`; and `manifest.json` naming the project, the start time, the app and register versions, the value mode and row count per file, and every skipped schema or file with its reason. Objects of other projects MUST NOT appear.

#### Scenario: The zip holds the project and nothing else

- **GIVEN** project A with 3 tasks, one with an attached PDF and two comments, and project B with 5 tasks
- **WHEN** an export of project A completes
- **THEN** `task.json` in the zip holds exactly the 3 tasks of project A
- **AND** `attachments/<key of that task>/` holds the PDF
- **AND** `comments.json` holds the two comments under that task's UUID
- **AND** `manifest.json` gives `task.json` a row count of 3 and value mode `stored`

#### Scenario: A file the requester cannot read is skipped, not fatal

- **GIVEN** a task attachment the requester has no read access to
- **WHEN** the export runs
- **THEN** the zip is written without that file
- **AND** `manifest.json` lists the file under `skipped` with the reason "no read access"

### Requirement: The export honours OpenRegister's export permission and audit trail

Before reading each schema, the job MUST ask OpenRegister's `ExportGate` with the profile name `planninq-project-export`. When the `project` or `task` schema is refused, the job MUST stop without writing a zip. When another schema is refused, its file MUST be left out and listed under `skipped`. Every completed export MUST be recorded through OpenRegister's `ExportAuditRecorder` with the requester, the profile and the total object count, and every refused export MUST be recorded as refused. Planninq MUST NOT write an audit row of its own.

#### Scenario: A manager without the export verb is refused

- **GIVEN** a manager whose OpenRegister role grants read but not export on the `task` schema
- **WHEN** the manager starts an export
- **THEN** no zip is written
- **AND** the manager gets the notification "Export of Herinrichting Marktplein failed: you do not have the export permission"
- **AND** the audit trail holds one refused export naming the manager and `planninq-project-export`

#### Scenario: A completed export is on the audit trail

- **GIVEN** a manager with the export verb
- **WHEN** the export of a project with 40 objects completes
- **THEN** the audit trail holds one completed export naming the manager, `planninq-project-export` and 40 rows

### Requirement: The requester is told when the export is ready or failed

When the job finishes, the requester MUST get a Nextcloud notification. On success it MUST read "Export of <project title> is ready" and open the zip in Files. On failure it MUST read "Export of <project title> failed: <reason>", and a half-written zip MUST be removed.

#### Scenario: The ready notification opens the zip

- **GIVEN** a running export started by a manager
- **WHEN** the job writes the zip
- **THEN** the manager gets a notification "Export of Herinrichting Marktplein is ready"
- **AND** clicking it opens the zip's folder in Files with the zip highlighted

#### Scenario: A full quota fails cleanly

@e2e exclude Filling a user's quota in the shared e2e instance would break other specs; covered by the unit test of task 3.3
- **GIVEN** a requester whose Files quota is too small for the zip
- **WHEN** the job runs
- **THEN** no partial zip stays in the requester's Files
- **AND** the requester gets "Export of <project title> failed: not enough storage space"
