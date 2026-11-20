# task-dependencies delta for planning-dependencies-on-task-page

## ADDED Requirements

### Requirement: The task page carries the dependency editor

The system MUST show a Dependencies section on every task page with the task's blockers, the
tasks it blocks and its related links, a picker limited to tasks of the same project, and a
remove button per link. A link the server refuses MUST show the server's reason next to the
picker. Tier: V1 (docs/FEATURES.md).

#### Scenario: Add a blocker from the task page

- **GIVEN** tasks "Deploy" and "Fix login" in one project, and a member on the task page of "Deploy"
- **WHEN** they pick "Fix login" under "Blocked by" and choose Add
- **THEN** "Deploy" lists "Fix login" under "Blocked by"
- **AND** the task page of "Fix login" lists "Deploy" under "Blocks"

#### Scenario: A cycle is refused on the page

- **GIVEN** "Deploy" is blocked by "Fix login"
- **WHEN** a member on the task page of "Fix login" adds "Deploy" under "Blocked by"
- **THEN** the page shows the server's message naming the cycle
- **AND** no link is stored

### Requirement: Board cards show the blocked badge

The system MUST show a Blocked badge on a board card while at least one of its blockers is not
done or cancelled, with the number of open blockers in its accessible name, and MUST remove it
once all blockers are finished. Tier: V1.

#### Scenario: The badge appears and clears

- **GIVEN** "Deploy" is blocked by "Fix login", which is in progress
- **WHEN** a member opens the board
- **THEN** the "Deploy" card shows the Blocked badge
- **AND** after "Fix login" moves to Done the badge is gone

### Requirement: A member can link related tasks without blocking

The system MUST let a project member link two tasks of the same project as "relates to" or
"duplicates". Such a link MUST NOT make either task blocked and MUST NOT take part in the cycle
check. Tier: V1.

#### Scenario: Link two related tasks

- **GIVEN** tasks "Update the form" and "Update the manual" in one project
- **WHEN** a member on the first task's page adds the second as "Relates to"
- **THEN** both task pages list the other under "Related"
- **AND** neither card shows a Blocked badge

### Requirement: A link made on the task page shows on the timeline

The system MUST draw a blocking link created on the task page as an arrow on the project timeline
between the two tasks' bars. Tier: V1.

#### Scenario: Arrow on the timeline

- **GIVEN** a member made "Fix login" block "Deploy" on the task page, and both tasks have dates
- **WHEN** they open /projects/:id/timeline
- **THEN** an arrow runs from the "Fix login" bar to the "Deploy" bar
