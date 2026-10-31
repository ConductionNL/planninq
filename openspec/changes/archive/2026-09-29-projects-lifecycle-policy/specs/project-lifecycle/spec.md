# project-lifecycle delta for projects-lifecycle-policy

## ADDED Requirements

### Requirement: A project manager restores an archived project

A project owner or manager MUST be able to set an archived project back to active. Archive and restore MUST be declared as transitions on the `project` schema, so OpenRegister refuses them for anyone without update rights on the project. Tier: MVP (docs/FEATURES.md, "Project archiving").

#### Scenario: Restore from the project settings sidebar

- **GIVEN** a project owner whose project has the status "archived"
- **WHEN** the owner opens the Danger zone tab of the project settings sidebar at /projects/:id and presses "Restore project"
- **THEN** the project's status is "active"
- **AND** the project appears under the Active chip of the project list at /projects
- **AND** the Danger zone tab shows "Archive project" again

#### Scenario: Restore from the archived list

- **GIVEN** a project owner on the project list at /projects with the Archived chip selected
- **WHEN** the owner opens the actions of an archived project and chooses "Restore"
- **THEN** the project leaves the archived list and shows under Active

#### Scenario: A member without update rights cannot restore

@e2e exclude API-level refusal with no screen, asserted by a live call in task 1.2 and a PHPUnit schema test
- **GIVEN** a project member who is not the owner or a manager
- **WHEN** the member sends `POST /apps/openregister/api/objects/{id}/transition` with the action `restore` for an archived project
- **THEN** the request is refused with 403
- **AND** the project stays archived

### Requirement: An admin limits project creation to chosen groups

The creation policy MUST accept three values: all signed-in users, administrators only, and members of chosen Nextcloud groups. The server MUST enforce the policy on `POST /apps/planninq/api/projects`, and the "New project" button MUST follow the same answer. Tier: V1 (docs/FEATURES.md, "User settings, manage which users can create projects"; section 3.1 `allow_project_creation`).

#### Scenario: Only the chosen groups may create

- **GIVEN** an admin who sets "Allow project creation" to "Members of these groups" and picks "Projectleiders" on the planninq admin settings page
- **AND** a user in "Projectleiders" and a user who is not
- **WHEN** each opens the project list at /projects
- **THEN** the user in "Projectleiders" sees "New project"
- **AND** the other user does not
- **AND** a `POST /apps/planninq/api/projects` from the other user answers 403

### Requirement: A person outside the policy requests a project and a reviewer decides

When the admin turns on project requests, a person who may not create a project MUST be able to request one through a guided form. The request MUST be stored as a project with the status "requested" that only the requester and the reviewers can see. The reviewers are the people who may create projects. A reviewer MUST be able to approve the request, which makes it an active project, or reject it with a reason. The requester MUST be told the outcome. Tier: Enterprise (docs/FEATURES.md, "Advanced admin controls"; no dedicated row).

#### Scenario: A requester fills in the guided form

- **GIVEN** creation is limited to "Projectleiders", project requests are on, and a user who is not in that group
- **WHEN** the user presses "Request a project" on the project list at /projects, fills in the title, description, reason and desired start date over the two steps, and presses "Send request"
- **THEN** the project list shows the request under the "Requested" chip
- **AND** opening it shows "This project is waiting for review" instead of the board

#### Scenario: A reviewer approves a request

- **GIVEN** a user in "Projectleiders" and a waiting request from someone else
- **WHEN** the reviewer opens the request from the "Requested" chip and presses "Approve"
- **THEN** the project's status is "active" and its board shows the default columns
- **AND** the requester gets a Nextcloud notification that the request was approved

#### Scenario: A reviewer rejects a request with a reason

- **GIVEN** a user in "Projectleiders" and a waiting request from someone else
- **WHEN** the reviewer presses "Reject", writes "Fits in the existing portal project" and confirms
- **THEN** the project's status is "rejected"
- **AND** the requester sees "This request was not approved" with that reason when opening it
- **AND** the requester gets a Nextcloud notification that the request was not approved
