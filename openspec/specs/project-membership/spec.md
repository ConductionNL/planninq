# project-membership Specification

## Purpose
Only the people on a project see its tasks and the other objects that belong to it. Planninq declares the rule on its register and OpenRegister evaluates it on every read; planninq keeps the list the rule reads on each object, and refuses a write into a project the writer is not on. This spec describes what the code does on development at 131c691f, written after the fact (build-all decision 81). Roles, group sharing and group ownership are added by the open change `projects-members-and-roles`, whose requirements land in this spec when it is archived.

## Requirements

### Requirement: Only a project's people and admins read the project and its tasks

The `project` schema MUST allow a read only to a user listed in its `members`, `managers`, `viewers` or `portfolioReaders`, to a user in one of its member, manager, viewer or owner groups, or to an admin (`lib/Settings/planninq_register.json:656`). The `task` schema MUST allow a read only to a user in the task's own `members`, `viewers` or `portfolioReaders` list, to a user in its member or viewer groups, or to an admin (`lib/Settings/planninq_register.json:65`). A user who is on no list of a project MUST NOT see that project in a project list and MUST NOT read its tasks through the OpenRegister object API.

#### Scenario: A non-member does not see the project
@e2e exclude Read rules are evaluated by OpenRegister; the person-level case is asserted by PHPUnit (PlanninqRegisterSchemaTest::testProjectAuthorizationEnforcesMembershipForRead) on the register descriptor, and the group case by tests/e2e/project-roles.spec.ts under the open change projects-members-and-roles

- **GIVEN** project "Vergunningen" has members Anna and Bram
- **WHEN** Carla, who is on no list of that project and is not an admin, lists projects
- **THEN** "Vergunningen" is not in her list
- **AND** a read of its tasks returns none of them

### Requirement: Planninq stamps the project's people on every object of the project

On every create and update of a `task`, `column`, `projectPhase`, `plannedTimeEntry`, `projectLogEntry`, `risk`, `projectStatusReport`, `projectRelease`, `boardFilter` or `forgeLink`, planninq MUST overwrite the object's `members` list with the members of its project, and the managers of the project's portfolio as `portfolioReaders`, whatever the client sent (`lib/Listener/ProjectMemberAccessListener.php`, registered in `lib/AppInfo/Application.php:776-797`). When a project's members change, planninq MUST copy the new list to every object of that project (`lib/Listener/ProjectMembershipSyncListener.php`, `ProjectMembershipService::syncProjectMembers()`). One object that refuses the write MUST be logged and MUST NOT stop the others.

#### Scenario: A new member reads the existing tasks
@e2e exclude Stamping on write is asserted by PHPUnit (ProjectMemberAccessListenerTest::testCreateByMemberStampsTheProjectMembers); the copy to existing objects (syncProjectMembers) has no test, and no e2e adds a person and reads back old tasks

- **GIVEN** project "Vergunningen" has 12 tasks and members Anna and Bram
- **WHEN** a manager adds Carla to the project
- **THEN** each of the 12 tasks lists Carla in its `members`
- **AND** Carla can open the project board and see the 12 tasks

#### Scenario: A client cannot choose who reads a task
@e2e exclude Asserted by PHPUnit (ProjectMemberAccessListenerTest)

- **GIVEN** Anna is a member of project "Vergunningen"
- **WHEN** she creates a task in it with `members` set to only herself
- **THEN** the stored task lists every member of the project

### Requirement: A write into a project is refused for someone not on it

Because OpenRegister checks `create` before the object exists, the create rule on `task`, `column` and `projectPhase` MUST admit any signed-in user, and planninq MUST refuse, with HTTP 422, a create or a move of a project-scoped object into a project the caller is not a member of. An admin MUST NOT be refused. A write without a user session, such as a background job, MUST be stamped and MUST NOT be refused.

#### Scenario: A non-member cannot add a task
@e2e exclude Asserted by PHPUnit (ProjectMemberAccessListenerTest::testCreateByNonMemberIsRefused, testAdminIsNotRefusedAndIsStillStamped, testSessionlessWriteIsStampedAndNotRefused)

- **GIVEN** Carla is not a member of project "Vergunningen"
- **WHEN** she posts a task with that project to the object API
- **THEN** the write is refused with HTTP 422
- **AND** no task is stored

### Requirement: The board says when the user has no access

The project board (`src/views/ProjectBoard.vue:6-14`) MUST show "You do not have access to this project" with "You are not a member of this project." when the project read answered 403, when OpenRegister hid the project (404), or when the loaded project gives the user no role in person or through a group (`src/utils/projectRole.js:189`). It MUST NOT show an empty board in that case.

#### Scenario: Following a link to someone else's project
@e2e exclude The group variant is driven by tests/e2e/project-roles.spec.ts "roles decide what each person sees" under the open change projects-members-and-roles; the person-level variant has no e2e

- **GIVEN** Carla is not on project "Vergunningen"
- **WHEN** she opens a link to its board
- **THEN** the page says "You do not have access to this project"
- **AND** no task card is shown

### Requirement: A project owner finds colleagues without admin rights

The member search on the Members tab MUST return matching users and groups to every signed-in project owner and manager, not only to Nextcloud admins and group sub-admins. It MUST respect the admin's sharing settings for user search, so a user the caller may not see is not listed. The member list MUST show each person's display name. Tier: MVP (docs/FEATURES.md, "Project members (add/remove users)").

#### Scenario: A regular owner adds a colleague by name

- **GIVEN** a project owner who is neither a Nextcloud admin nor a group sub-admin
- **AND** a colleague with the display name "Ada Jansen" who is not on the project
- **WHEN** the owner types "Ada" in the "Add member" field on the Members tab of the project settings sidebar at /projects/:id
- **THEN** "Ada Jansen" appears in the results with her avatar
- **AND** after the owner picks her, she appears in the member list as "Ada Jansen", not as her user id
- **AND** she can open the project board at /projects/:id

#### Scenario: The admin's search limits apply

- **GIVEN** the admin has limited user search to people in the caller's own groups
- **AND** a project owner who shares no group with a user named "Bram"
- **WHEN** the owner types "Bram" in the "Add member" field
- **THEN** "Bram" is not listed
- **AND** the field says "No one found. Your admin's sharing settings decide who you can find."

### Requirement: A project manager gives each person a role

Each person on a project MUST hold exactly one effective role: owner, manager, member or viewer. A manager MUST be able to set the role of any person except the owner. OpenRegister MUST enforce the rights of each role on the project, its tasks, columns and phases, so a request that bypasses the interface gets the same answer. Tier: Enterprise (docs/FEATURES.md, "Role-based project permissions (viewer/editor/admin)").

#### Scenario: A viewer reads the board and cannot change it

- **GIVEN** a project member whose role a manager set to "Viewer" on the Members tab
- **WHEN** the viewer opens the board at /projects/:id
- **THEN** the board shows every task
- **AND** the cards cannot be dragged and the quick add field is not shown
- **AND** a PUT to `/apps/openregister/api/objects/planninq/task/{id}` for one of its tasks, sent with the viewer's session, answers 403

#### Scenario: A member cannot manage members

- **GIVEN** a project member with the role "Member"
- **WHEN** the member opens the project settings sidebar
- **THEN** the Members tab lists the people on the project without a role picker, an add field or remove buttons
- **AND** a PATCH to `/apps/openregister/api/objects/planninq/project/{id}` that changes `members`, sent with the member's session, answers 403

#### Scenario: A manager cannot take over ownership
@e2e exclude API-level refusal with no screen, asserted by PHPUnit on the property rule and a live PATCH (task 2.2)

- **GIVEN** a project manager who is not the owner
- **WHEN** the manager sends a PATCH to `/apps/openregister/api/objects/planninq/project/{id}` that sets `owner` to their own user id
- **THEN** the request is refused
- **AND** the project's `owner` is unchanged

### Requirement: A project manager shares a project with a whole group

A project manager MUST be able to add a Nextcloud group to a project with a role. Every current and future member of that group MUST get that role's rights on the project, and a person removed from the group MUST lose them unless they hold a role of their own. Tier: V1 (docs/FEATURES.md has no row for group sharing; it extends "Project members (add/remove users)").

#### Scenario: A group member sees a project shared with the group

- **GIVEN** a project manager who adds the group "Vergunningen" with the role "Member" from the Members tab
- **AND** a user in "Vergunningen" who is not on the project in person
- **WHEN** that user opens the project list at /projects
- **THEN** the project is listed
- **AND** the user can create a task on the board at /projects/:id

#### Scenario: Leaving the group removes access

- **GIVEN** a user who could see a project only through the group "Vergunningen"
- **WHEN** an admin removes that user from "Vergunningen"
- **AND** the user reloads /projects/:id
- **THEN** the board shows "You do not have access to this project"

### Requirement: A group can own a project

A project owner MUST be able to hand the project to a Nextcloud group. Every member of the owning group MUST then hold owner rights, including archive and delete, so the project does not depend on one account. Only the owner, the owning group and admins MUST be able to change the owning group. Tier: V1 (docs/FEATURES.md, "Shared project access (multi-user)").

#### Scenario: A colleague in the owning group manages the project after the creator leaves

- **GIVEN** a project whose owner set "Owned by group" to "Team infra" on the Members tab
- **AND** a user in "Team infra" who has no role of their own on the project
- **WHEN** the original owner leaves the project through the "Leave project" dialog
- **THEN** the user in "Team infra" can still open the project settings sidebar and change the project title
- **AND** the Members tab shows "Owned by group: Team infra"

### Requirement: A deleted account or group never orphans a project

When a Nextcloud user or group is deleted, planninq MUST remove it from every role list of every project. When the deleted user owned a project without an owning group, ownership MUST pass to the first manager, or else to the first remaining member in alphabetical order. Tier: MVP (docs/FEATURES.md, "Project-level access control (members only)").

#### Scenario: The owner's account is deleted
@e2e exclude Account deletion runs outside the app, asserted by ProjectPrincipalCleanupListenerTest (task 5.3)

- **GIVEN** a project owned by "kees" with the manager "anna" and the member "bert"
- **WHEN** an admin deletes the account "kees" in Nextcloud user management
- **THEN** the project's `owner` is "anna"
- **AND** "kees" no longer appears in any role list of the project
- **AND** "anna" can open the project settings sidebar and edit the project
