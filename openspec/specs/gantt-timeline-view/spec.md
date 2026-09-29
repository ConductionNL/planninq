# gantt-timeline-view Specification

## Purpose
Read-only timelines: one project's tasks on a time axis with their dependencies, and several projects of a portfolio on one axis.

## Requirements

### Requirement: A project's tasks can be viewed on a time axis

The system MUST provide a per-project timeline that returns the project's tasks with their
`startDate`, `dueDate`, and `duration`, laid out for rendering on a time axis. Reads MUST go
through OpenRegister `ObjectService` (RBAC/tenancy-scoped); a caller MUST NOT see tasks of a
project they cannot access. Tasks with no dates MUST be returned flagged as "unscheduled"
(surfaced separately), never silently dropped. The timeline MUST NOT introduce a new schema,
new storage, or a scheduling engine — it is a read surface over existing task objects.

#### Scenario: A project timeline returns dated tasks positioned in time

- **GIVEN** a project whose tasks carry `startDate`/`dueDate`
- **WHEN** a permitted caller requests the project's timeline for a window
- **THEN** the system MUST return those tasks with their `startDate`/`dueDate`/`duration` so each can be drawn as a bar from start to due
- **AND** tasks with no dates MUST be returned flagged "unscheduled"

#### Scenario: Timeline access is scoped by OpenRegister RBAC

- **GIVEN** a caller with no access to a project
- **WHEN** they request that project's timeline
- **THEN** the system MUST NOT return its tasks (RBAC-scoped through `ObjectService`)

@e2e exclude the read/windowing/unscheduled-split is unit-tested against seeded tasks; a Playwright timeline smoke follows once seed data lands.

### Requirement: The timeline renders the existing dependency links, not a new copy

The timeline MUST draw dependency edges between task bars using the dependency links that
the `task-dependencies` capability already stores. It MUST NOT re-derive, duplicate, or
persist a separate copy of dependency state; it reads and renders what already exists.

#### Scenario: Dependency arrows come from stored links

- **GIVEN** two tasks with a stored predecessor→successor dependency
- **WHEN** the project timeline is rendered
- **THEN** an edge MUST be drawn between their bars sourced from the existing dependency link
- **AND** no new dependency object MUST be created by viewing the timeline

@e2e exclude edge sourcing asserted by the controller/view unit tests (reads dependency links, no writes).

### Requirement: Several projects can be viewed on one timeline

The portfolio timeline MUST show the projects of a chosen portfolio on one time axis, each as a summary bar that opens into its phases and tasks. It MUST read all projects through one RBAC-scoped request, MUST leave out projects the viewer cannot read, and MUST draw dependencies between tasks of different projects when both ends are shown. It MUST be read-only and operable by keyboard. Tier: V1 (docs/FEATURES.md has no row; it extends this capability's single-project timeline).

#### Scenario: A portfolio on one axis

- **GIVEN** a portfolio manager of "Ruimte", which holds three projects with planned dates
- **WHEN** the manager opens the portfolio timeline at /portfolio/timeline and picks "Ruimte"
- **THEN** three summary bars are drawn on one axis, sorted by start date
- **AND** moving focus to a bar and pressing Enter shows that project's phases and task bars under it

#### Scenario: Projects the viewer cannot read are left out

@e2e exclude API-level filtering with no screen, asserted by TimelineControllerTest (task 3.1)
- **GIVEN** a user who can read two of three requested projects
- **WHEN** the user sends `GET /apps/planninq/api/timeline?projects=a,b,c`
- **THEN** the answer holds the two readable projects
- **AND** lists the third under `skipped` without any of its tasks
