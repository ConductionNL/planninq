# task-collaboration Specification

## Purpose
TBD - created by archiving change task-collaboration-sidebar. Update Purpose after archive.
## Requirements
### Requirement: Comments on tasks via Nextcloud comments [MVP]
The task detail view MUST provide a Comments tab on its `CnObjectSidebar`, backed by Nextcloud's `ICommentsManager` through OpenRegister's per-object notes API (objectType `openregister`, object id = task UUID). Users with read access to the task MUST be able to list and add comments; users MUST be able to edit and delete only their own comments. Planix MUST NOT define a comment schema in `planix_register.json` and MUST NOT add a planix comments controller.

#### Scenario: Add a comment to a task
- GIVEN a project member has the task detail open on the Comments tab
- WHEN the member writes "Waiting on the API contract" and submits
- THEN the comment MUST be stored via the OpenRegister notes API (ICommentsManager-backed)
- AND it MUST appear in the tab with the member's display name, avatar, and a relative timestamp

#### Scenario: Edit and delete own comment only
- GIVEN a task has a comment by `alice` and a comment by `bob`
- WHEN `alice` views the Comments tab
- THEN edit and delete actions MUST be available on `alice`'s comment
- AND MUST NOT be available on `bob`'s comment

#### Scenario: Comments respect task access
@e2e exclude API permission contract, covered by Newman RBAC negative test
- GIVEN a user who has no read access to a task's OpenRegister object
- WHEN that user requests the task's notes via the OpenRegister notes API
- THEN no comments MUST be returned
- AND an attempt to create a comment on that task MUST be rejected

#### Scenario: No app-local comment storage
@e2e exclude static register-definition assertion, covered by PHPUnit against planix_register.json
- GIVEN the planix register definition `lib/Settings/planix_register.json`
- WHEN its `components.schemas` keys are enumerated
- THEN no comment, note, or message schema may be present

### Requirement: File attachments on tasks via Nextcloud Files [MVP]
The task detail view MUST provide a Files tab on its `CnObjectSidebar`, backed by OpenRegister's object-files API. Users with access to the task MUST be able to upload, list, download, and remove attachments. Attached files MUST be stored in Nextcloud Files (OR-managed object folder) — planix MUST NOT store file content or maintain its own attachment schema.

#### Scenario: Attach a file to a task
- GIVEN a project member has the task detail open on the Files tab
- WHEN the member uploads `design-notes.pdf`
- THEN the file MUST be attached to the task via the OpenRegister object-files API
- AND it MUST appear in the Files tab with name, size, and modification date

#### Scenario: Attachment is a real Nextcloud file
@e2e exclude storage-location contract, covered by Newman against the Files WebDAV/OR files API
- GIVEN a file has been attached to a task
- WHEN the OR-managed folder for that object is inspected in Nextcloud Files
- THEN the attached file MUST exist there as a regular Nextcloud file (shareable, versioned by NC)

#### Scenario: Remove an attachment
- GIVEN a task has an attached file
- WHEN a project member removes it from the Files tab and confirms
- THEN the attachment MUST no longer be listed on the task

### Requirement: Audit trail tab on tasks [MVP]
The task detail view MUST provide an Audit Trail tab on its `CnObjectSidebar`, rendering OpenRegister's per-object audit trail read-only: acting user, timestamp, and changed fields with old → new values. Planix MUST NOT record its own change log.

#### Scenario: Field change appears in the audit trail
- GIVEN a task's status was changed from `open` to `in_progress` by `alice`
- WHEN a project member opens the task's Audit Trail tab
- THEN an entry MUST show `alice`, the change time, and `status: open → in_progress`

#### Scenario: Audit trail is read-only
- GIVEN the Audit Trail tab is open
- THEN the tab MUST offer no edit or delete actions on trail entries

### Requirement: Task events published to Nextcloud Activity [MVP]
Planix MUST publish task lifecycle events to the Nextcloud Activity stream via `OCP\Activity\IManager`, driven by a listener on OpenRegister's object events scoped to the planix register's `task` schema. Subjects MUST cover at minimum: task created, status changed, assignee changed, due date changed, and task deleted — each rendered human-readably in English and Dutch by a registered `OCP\Activity\IProvider`, with a "Planix" `IFilter` in the Activity app. The audience is the task's project members; the acting user MUST NOT receive an activity entry for their own change. This is an activity-stream record, not a notification: it MUST NOT create Nextcloud notifications and is independent of the `task_assigned` notification specced in `tasks.md`.

#### Scenario: Status change appears in a member's activity stream
- GIVEN `alice` and `bob` are members of a project
- WHEN `alice` moves a task to the Done column (status becomes `done`)
- THEN `bob`'s Nextcloud Activity stream MUST contain a Planix entry stating that `alice` changed the task's status
- AND `alice` MUST NOT see an activity entry for her own change

#### Scenario: Planix filter in the Activity app
- GIVEN task events have been published
- WHEN a project member opens the Nextcloud Activity app
- THEN a "Planix" filter MUST be available
- AND selecting it MUST show only planix task entries

#### Scenario: Non-task and foreign-register events are ignored
@e2e exclude listener scoping, covered by PHPUnit on TaskActivityListener
- GIVEN OpenRegister dispatches object events for other schemas (project, column, timeEntry, label) and for other apps' registers
- WHEN the planix activity listener handles them
- THEN no activity MUST be published for those events

#### Scenario: Malformed event does not break dispatch
@e2e exclude resilience path, covered by PHPUnit on TaskActivityListener
- GIVEN an OR object event with a payload missing the task's project reference
- WHEN the planix activity listener handles it
- THEN the listener MUST log and skip without throwing

