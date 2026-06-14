# task-dependencies Specification

## Purpose
Defines directed dependencies between tasks of the same project: a `dependency` edge (`blocker → blocked`) stored in OpenRegister, managed from the task detail, kept acyclic by server-side validation in planix (self/duplicate/cross-project/cycle), and surfaced as a derived "Blocked" indicator that never writes `status` and never hard-blocks a move. Completes the flow-management story reserved in `tasks.md` Notes ("Task dependencies (V1) will be a separate `dependency` entity") and `kanban-board.md` ("Blocked task indicators").

## Requirements
### Requirement: Dependency edges between tasks [V1]
The system MUST allow project members to link two tasks of the same project with a directed dependency (blocker → blocked) and to remove such links. The task detail MUST show both directions — "Blocked by" (incoming) and "Blocks" (outgoing) — with each linked task's title and status, and provide a picker limited to tasks of the same project. Reads use the OpenRegister API directly; create and delete go through planix endpoints that perform validation (ADR-022 — validation is domain logic, not pass-through).

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

