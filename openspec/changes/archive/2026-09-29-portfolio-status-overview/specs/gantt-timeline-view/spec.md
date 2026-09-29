# gantt-timeline-view delta for portfolio-status-overview

## ADDED Requirements

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
