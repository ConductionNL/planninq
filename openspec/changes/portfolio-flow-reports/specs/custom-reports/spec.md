# custom-reports delta for portfolio-flow-reports

## ADDED Requirements

### Requirement: A user builds and saves a report over tasks

A signed-in user MUST be able to build a report by choosing its projects or portfolio, equality filters on task fields, a grouping, a count or a sum, and a table, bar or donut display, and MUST be able to save it under a title. Saved reports MUST be listed on the Reports page for their owner. Tier: V1 (docs/FEATURES.md, "Team workload report (tasks per user)" and "Label/category distribution chart"; no dedicated row for a builder).

#### Scenario: Open tasks per assignee across two projects

- **GIVEN** a user on the projects "Omgevingsvisie" and "Wegbeheer"
- **WHEN** the user presses "New report" on the Reports page at /reports, picks both projects, filters status "open", groups by assignee, counts tasks, picks "Bar" and saves it as "Open work per person"
- **THEN** the report page at /reports/custom/:id shows a bar per assignee with their number of open tasks across both projects
- **AND** "Open work per person" appears under "My reports" on /reports

### Requirement: A shared report never shows more than the viewer may read

A report owner MUST be able to share a report with everyone who can read its projects. Every viewer MUST run the report with their own rights, and the report MUST say how many of its projects the viewer cannot see. Tier: V1 (docs/FEATURES.md, "Project-level access control (members only)").

#### Scenario: A viewer on fewer projects

- **GIVEN** a shared report over five projects
- **AND** a user who can read two of those projects
- **WHEN** the user opens the report
- **THEN** the chart counts only tasks of the two projects the user can read
- **AND** the page says "3 of 5 projects in this report are not visible to you"
