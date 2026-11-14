# project-overview delta for projects-overview-logs-risks

## ADDED Requirements

### Requirement: A project member opens one overview of the project

Every project MUST have an overview page that shows its description, planned dates, the people on it, its progress, its highest open risks and its latest log entries. Progress MUST count done tasks against all tasks that are not cancelled. Tier: MVP (docs/FEATURES.md, "Project progress (tasks done / total)").

#### Scenario: The overview shows where the project stands

- **GIVEN** a project member on a project with 10 tasks, of which 4 are done and 1 is cancelled
- **WHEN** the member opens the Overview tab at /projects/:id/overview
- **THEN** the page shows the project's description and its start and end dates
- **AND** it lists the people on the project by display name
- **AND** it shows progress as "4 of 9 tasks done"

#### Scenario: An empty project says so

- **GIVEN** a project member on a project with no tasks, no risks and no log entries
- **WHEN** the member opens /projects/:id/overview
- **THEN** the progress block says "No tasks yet"
- **AND** the risks and log blocks each offer a link to add the first one

### Requirement: The project pages share one row of tabs

The board, backlog, timeline, overview, risks and log pages of a project MUST show the same row of tabs, with the current page marked for assistive technology. The tabs MUST be reachable and operable by keyboard. Tier: MVP (docs/FEATURES.md, "Kanban board view per project").

#### Scenario: Moving between project pages by keyboard

- **GIVEN** a project member on the board at /projects/:id
- **WHEN** the member moves focus with Tab to the row of project tabs and activates "Risks" with Enter
- **THEN** the page at /projects/:id/risks opens
- **AND** the "Risks" tab carries `aria-current="page"`
