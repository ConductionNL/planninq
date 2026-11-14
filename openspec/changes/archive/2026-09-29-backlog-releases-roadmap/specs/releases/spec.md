# releases delta for backlog-releases-roadmap

## ADDED Requirements

### Requirement: A project member can create a release for a project

A project member MUST be able to create a release in their project with a name, an optional start date, a target date and an optional description, and the release SHALL be readable and writable only by members of that project and by admins. Tier: V1 (docs/FEATURES.md, backlog management; this change adds the row).

#### Scenario: A member creates a release

- **GIVEN** a project member on the roadmap at /projects/:id/timeline?view=roadmap
- **WHEN** they choose "New release", enter "Version 2.0" with a target date of 2026-12-01 and save
- **THEN** a `release` object with that title, `releaseDate` 2026-12-01, status `planned` and this project exists
- **AND** the release list on the roadmap shows "Version 2.0" with "0 of 0 tasks done"

#### Scenario: A non-member cannot see the project's releases
@e2e exclude The e2e suite signs in as the admin only; the read rule is the projectPhase members rule, asserted by PHPUnit PlanninqRegisterSchemaTest::testProjectScopedSchemasMatchTheMembersList and ProjectMemberAccessListenerTest::testReleaseIsStampedAndGated.

- **GIVEN** a user who is not a member of the project that owns "Version 2.0"
- **WHEN** they request the release through GET /apps/openregister/api/objects/planninq/release/{id}
- **THEN** OpenRegister refuses the read

### Requirement: A project member can plan a task against a release

A task MUST be able to reference at most one release of its own project, and a project member SHALL be able to set or clear it from the task detail page. Tier: V1.

#### Scenario: A member plans a task against a release

- **GIVEN** a project member on the task detail page of "Export to CSV"
- **WHEN** they pick "Version 2.0" in the Release field
- **THEN** the task's `release` references "Version 2.0"
- **AND** the release list on the roadmap shows "0 of 1 tasks done" for "Version 2.0"

#### Scenario: Only the project's own releases are offered

- **GIVEN** a project member on a task of project A, and a release "Version 9" in project B
- **WHEN** they open the Release field
- **THEN** "Version 9" is not offered

### Requirement: A project member can mark a release as released

A project member MUST be able to mark a planned release as released, which SHALL set its status to `released` and record `releasedAt`. When tasks of the release are neither `done` nor `cancelled`, the system MUST list them and let the member move them to another planned release, clear their release, or keep them, before the release is marked. Tier: V1.

#### Scenario: Shipping with everything done

- **GIVEN** the release "Version 2.0" whose three tasks are all done
- **WHEN** a project member chooses "Mark as released" and confirms
- **THEN** "Version 2.0" has status `released` and a `releasedAt`

#### Scenario: Shipping asks about unfinished tasks

- **GIVEN** the release "Version 2.0" with two done tasks and one open task, and the planned release "Version 2.1"
- **WHEN** a project member chooses "Mark as released"
- **THEN** the dialog lists the open task and offers "Move to Version 2.1", "Remove the release" and "Keep it on Version 2.0"
- **AND** after they choose "Move to Version 2.1" and confirm, the open task references "Version 2.1" and "Version 2.0" is released

### Requirement: Every release shows its progress

The release list MUST show, for every release, how many of its tasks are done or cancelled out of all tasks that reference it. Tier: V1.

#### Scenario: Progress counts done and cancelled tasks

- **GIVEN** the release "Version 2.0" with five tasks: two done, one cancelled, two open
- **WHEN** a project member opens the roadmap
- **THEN** "Version 2.0" shows "3 of 5 tasks done"
