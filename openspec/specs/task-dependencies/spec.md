# task-dependencies Specification

## Purpose
Defines directed dependencies between tasks of the same project: a `dependency` edge (`blocker → blocked`) stored in OpenRegister, managed from the task detail, kept acyclic by server-side validation in planninq (self/duplicate/cross-project/cycle), and surfaced as a derived "Blocked" indicator that never writes `status` and never hard-blocks a move. Completes the flow-management story reserved in `tasks.md` Notes ("Task dependencies (V1) will be a separate `dependency` entity") and `kanban-board.md` ("Blocked task indicators").

## Requirements

### Requirement: Dependency edges between tasks [V1]
The system MUST allow project members to link two tasks of the same project with a directed dependency (blocker → blocked) and to remove such links. The task detail MUST show both directions — "Blocked by" (incoming) and "Blocks" (outgoing) — with each linked task's title and status, and provide a picker limited to tasks of the same project. Reads use the OpenRegister API directly; create and delete go through planninq endpoints that perform validation (ADR-022 — validation is domain logic, not pass-through).

#### Scenario: Add a blocked-by dependency
- GIVEN tasks "Deploy" and "Fix login" exist in the same project
- WHEN a project member opens "Deploy" and adds "Fix login" under "Blocked by"
- THEN a dependency edge (blocker: "Fix login", blocked: "Deploy") MUST be stored
- AND "Deploy" MUST list "Fix login" under "Blocked by"
- AND "Fix login" MUST list "Deploy" under "Blocks"

#### Scenario: Remove a dependency
- GIVEN "Deploy" is blocked by "Fix login"
- WHEN a project member removes the link from either task's Dependencies section
- THEN the edge MUST be deleted
- AND both tasks' dependency lists MUST no longer show it

#### Scenario: Cross-project dependency rejected
@e2e exclude API validation contract, covered by Newman 422 assertion
- GIVEN a task in Project A and a task in Project B
- WHEN a dependency between them is submitted
- THEN the system MUST reject it with a validation error

#### Scenario: Non-member cannot create dependencies
@e2e exclude API permission contract, covered by Newman 403 assertion
- GIVEN a user who is not a member of the tasks' project
- WHEN that user calls the dependency create or delete endpoint
- THEN the system MUST reject the request (authorization error)

### Requirement: Dependency graph stays acyclic [V1]
The system MUST reject, server-side, any dependency that would make a project's dependency graph cyclic — including self-dependencies and the degenerate two-task cycle — with an error that names the conflicting path. Duplicate edges MUST also be rejected. Diamond shapes (two paths to the same task without a cycle) are legal.

#### Scenario: Direct cycle rejected
@e2e exclude graph validation contract, covered by PHPUnit (testTwoNodeCycle) + Newman; the page shows the refusal, asserted by tests/e2e/task-dependencies.spec.ts "a cycle is refused with the server message"
- GIVEN "A" is blocked by "B"
- WHEN a member attempts to add "B" blocked by "A"
- THEN the system MUST reject it with an error naming the cycle
- AND no edge MUST be stored

#### Scenario: Transitive cycle rejected with path
@e2e exclude graph validation contract, covered by Newman 422-with-path assertion
- GIVEN edges A → B and B → C exist
- WHEN an edge C → A is submitted (A blocked by C reaching back)
- THEN the system MUST reject it
- AND the error MUST name the path that would close the cycle (e.g. "A → B → C → A")

#### Scenario: Self and duplicate edges rejected
@e2e exclude input validation, covered by PHPUnit on DependencyService
- GIVEN a task "A" and an existing edge A → B
- WHEN an edge A → A or a second identical edge A → B is submitted
- THEN each MUST be rejected with a validation error

#### Scenario: Diamond dependency is allowed
@e2e exclude graph validation contract, covered by PHPUnit on DependencyService
- GIVEN edges A → B and A → C exist
- WHEN edges B → D and C → D are submitted
- THEN both MUST be accepted (two paths to D form no cycle)

### Requirement: Derived blocked indicator [V1]
A task with at least one blocker whose status is not `done` or `cancelled` MUST display a "Blocked" indicator; the indicator MUST disappear once every blocker is completed or cancelled. The indicator is derived at render time and MUST NOT write to the task's `status` field; it MUST NOT prevent moving the task (soft signal, consistent with the WIP-limit philosophy). The task detail MUST list which open blockers cause the state. Derivation MUST tolerate edges whose blocker task no longer resolves (ignore them) and MUST terminate on any graph shape.

#### Scenario: Blocker completion clears the indicator
- GIVEN "Deploy" is blocked by "Fix login" (status `in_progress`) and shows a Blocked badge on its kanban card
- WHEN "Fix login" is moved to a done column (status `done`)
- THEN "Deploy" MUST no longer show the Blocked badge on board or detail

#### Scenario: Blocked task can still be moved
@e2e exclude board drag layer not yet built (tasks#REQ-Task-CRUD); the badge is derived-only and never gates a write — soft-signal invariant covered by Vitest/PHPUnit (no status write)
- GIVEN "Deploy" shows the Blocked indicator
- WHEN a member drags "Deploy" to another column
- THEN the move MUST succeed (the indicator never hard-blocks)
- AND the indicator MUST remain visible while blockers are open

#### Scenario: Dangling edge is ignored
@e2e exclude tolerant-read derivation, covered by Vitest on the isBlocked helper
- GIVEN a dependency edge whose blocker task was deleted out-of-band (UUID unresolvable)
- WHEN the blocked state is derived for the board
- THEN that edge MUST be ignored
- AND derivation MUST complete without error

### Requirement: Dependency lifecycle follows tasks [V1]
Deleting a task MUST also delete every dependency edge in which it participates (as blocker or blocked), alongside the existing TimeEntry cascade. Moving a task to another project MUST remove its dependency edges (the same-project invariant holds by construction).

#### Scenario: Task delete removes its edges
@e2e exclude cascade contract, covered by PHPUnit (testRemoveEdgesForTaskCascades); the task-delete UI caller is not yet built (tasks#REQ-Task-CRUD)
- GIVEN "Fix login" blocks "Deploy"
- WHEN "Fix login" is deleted (per the tasks spec's delete flow)
- THEN the edge MUST be deleted
- AND "Deploy" MUST no longer be blocked nor list the dependency

#### Scenario: Project move removes edges
@e2e exclude cascade contract, covered by Newman after a project-move call
- GIVEN "Deploy" (Project A) is blocked by "Fix login" (Project A)
- WHEN "Deploy" is moved to Project B
- THEN the edge MUST be removed
- AND neither task may list the dependency afterwards

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
