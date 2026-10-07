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
