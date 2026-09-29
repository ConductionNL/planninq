# task-editing delta for tasks-create-edit-delete

Extends the flat main spec `openspec/specs/tasks.md` (Task CRUD).

## ADDED Requirements

### Requirement: A project member can create a task

The system MUST let a member of a project create a task in that project with a title, and MAY
take a Markdown description, a status and a priority. A new task MUST default to status `open`
and priority `normal`, and MUST record the creator as `reporter`. Tier: MVP (docs/FEATURES.md).

#### Scenario: Create a task from the board header

- **GIVEN** a project member has the board of project "Vergunningen" open at /projects/:id
- **WHEN** they choose "New task", enter the title "Draft the permit letter" and save
- **THEN** the task is stored with status `open`, priority `normal` and the member as reporter
- **AND** its card appears at the bottom of the board's first lane without a page reload

#### Scenario: A non-member cannot create a task

- **GIVEN** a user who is not a member of project "Vergunningen"
- **WHEN** they POST a task for that project to the OpenRegister objects API
- **THEN** the request is refused with 403 and no task is stored

@e2e exclude needs a second, non-member account the CI e2e run does not have; covered by tests/unit/Listener/ProjectMemberAccessListenerTest.php::testCreateByNonMemberIsRefused on the real listener

### Requirement: A project member can quick-add a task in a board lane

The system MUST offer a one-line add field at the foot of every board lane. Pressing Enter MUST
create a task with that title in that lane and MUST keep focus in the field. Tier: MVP.

#### Scenario: Quick add two tasks in a row

- **GIVEN** a project member is on the board with the In progress lane visible
- **WHEN** they type "Call the applicant" in that lane's add field, press Enter, type "Check the drawings" and press Enter
- **THEN** both cards appear in the In progress lane in that order
- **AND** the add field is empty and still focused

### Requirement: A project member can edit a task's title, description and status

The system MUST let a project member change a task's title, description and status from the
task page, and MUST send only the changed fields. Tier: MVP.

#### Scenario: Rename a task

- **GIVEN** a project member is on the task page of "Draft the permit letter"
- **WHEN** they choose "Edit", change the title to "Draft and send the permit letter" and save
- **THEN** the task page shows the new title
- **AND** the stored task keeps its project, priority and other fields unchanged

### Requirement: The task description supports Markdown

The system MUST render a task description as Markdown on the task page, with headings, lists
and links, and MUST NOT execute HTML or script from it. The board card MUST show a short
plain-text excerpt. Tier: V1.

#### Scenario: A checklist-style description renders as a list

- **GIVEN** a task whose description is "## Steps" followed by two lines starting with "- "
- **WHEN** a project member opens the task page
- **THEN** they see a heading "Steps" and a two-item bulleted list

#### Scenario: Script in a description stays text

- **GIVEN** a task whose description contains `<script>alert(1)</script>`
- **WHEN** a project member opens the task page
- **THEN** the text is shown literally and no script runs

### Requirement: The reporter, the project owner or an admin can delete a task

The system MUST let the task's reporter, the project's owner or an admin delete a single task
after a confirmation, and MUST refuse other members. A task with logged time MUST NOT be
deleted; the confirmation MUST offer to cancel it instead. Tier: MVP.

#### Scenario: Delete a task without logged time

- **GIVEN** the reporter of a task with no logged time is on its task page
- **WHEN** they choose "Delete task" and confirm
- **THEN** the task is deleted, its dependency links are removed, and they return to the board without the card

#### Scenario: A task with logged time is cancelled instead

- **GIVEN** the project owner is on the task page of a task with two hours logged
- **WHEN** they choose "Delete task"
- **THEN** the dialog says the task has logged time and offers "Cancel task"
- **AND** choosing it sets the status to `cancelled` and keeps the time entries

#### Scenario: A plain member cannot delete someone else's task

- **GIVEN** a project member who is neither the reporter nor the owner
- **WHEN** they open the task page
- **THEN** no "Delete task" action is shown
- **AND** a DELETE on the OpenRegister objects API for that task answers 403

@e2e exclude needs a second member account the CI e2e run does not have; covered by tests/unit/Listener/TaskReporterGuardListenerTest.php::testAPlainMemberCannotDeleteSomeoneElsesTask (code and status 403) and tests/vitest/taskEditing.spec.js canDeleteTask
