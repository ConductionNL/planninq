# project-status-report delta for portfolio-status-overview

## ADDED Requirements

### Requirement: A project leader reports status on six aspects

A project owner or manager MUST be able to write a dated status report that sets money, organisation, time, information, quality and risk each to on track, at risk or off track, with a note per aspect. The overall status MUST be the worst of the six and MUST be calculated by the server. Earlier reports MUST stay readable as history. Tier: Enterprise (docs/FEATURES.md has no row; tender 365739 requirements 4020, 4129, 24322 and 64437).

#### Scenario: Writing a status report

- **GIVEN** a project manager on the Status tab at /projects/:id/status
- **WHEN** the manager presses "New report", sets time to "At risk" with the note "Permit delayed by 3 weeks", sets the other five aspects to "On track" and saves
- **THEN** the tab shows the report with today's date, the manager's name and the overall status "At risk"
- **AND** the report history lists it above the earlier reports

#### Scenario: The server decides the overall status

@e2e exclude Server-side calculation with no screen, asserted by a PHPUnit schema test and a live POST in task 1.1
- **GIVEN** a project manager
- **WHEN** the manager sends `POST /apps/openregister/api/objects/planninq/projectStatusReport` with `statusQuality` "offTrack", the other statuses "onTrack", and `overall` "onTrack"
- **THEN** the stored report has `overall` "offTrack"

### Requirement: Planninq suggests a status for money, time and risk

The report form MUST show a suggested status with its reason for money, time and risk, based on the project's budget and costs, its task due dates and end date, and its open risks. It MUST NOT choose a status for the project leader. Tier: Enterprise (docs/FEATURES.md has no row).

#### Scenario: A time suggestion from late tasks

- **GIVEN** a project with 3 open tasks past their due date
- **WHEN** a project manager opens "New report" on the Status tab
- **THEN** next to "Time" the form shows "Suggested: at risk. 3 tasks are past their due date."
- **AND** no status is selected for "Time" until the manager picks one
