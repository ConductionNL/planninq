# project-logs delta for projects-overview-logs-risks

## ADDED Requirements

### Requirement: A project member keeps logs of issues, lessons, meetings and decisions

Every project MUST have a log where its members record issues, lessons learned, meetings and decisions. Each entry MUST carry its type, a title, a date and a body, and the author and time MUST come from the server. Only people who can read the project MUST see its log. Tier: V1 (docs/FEATURES.md has no row; tender 365739 requirement 4012).

#### Scenario: Recording a meeting

- **GIVEN** a project member on the Log tab at /projects/:id/log
- **WHEN** the member presses "Add entry", picks "Meeting", fills in the title "Kick-off", the date and two attendees, and saves
- **THEN** the entry appears at the top of the log with the type "Meeting", the author's name and the time it was saved
- **AND** choosing the filter "Meetings" shows it and hides the other types

#### Scenario: An outsider cannot read the log

@e2e exclude API-level refusal with no screen, asserted by a PHPUnit schema test and a live GET in task 2.1
- **GIVEN** a signed-in user who is not on the project
- **WHEN** the user sends `GET /apps/openregister/api/objects/planninq/projectLogEntry?project={id}`
- **THEN** the answer contains none of that project's entries

### Requirement: An action from a log entry is a normal task

A project member MUST be able to create an action from an issue, meeting or decision entry. The action MUST be a task in the same project, linked from the entry, so it can be assigned, dated and moved on the board like any other task. Tier: V1 (docs/FEATURES.md has no row; tender 365739 requirement 4012).

#### Scenario: Turning a meeting outcome into an action

- **GIVEN** a meeting entry "Kick-off" in the project log
- **WHEN** a project member presses "Add action" on that entry, types "Send the planning to the steering group", assigns it to "Ada Jansen" and saves
- **THEN** the task appears on the project board at /projects/:id
- **AND** the entry lists the action with its status
- **AND** the Log tab filter "Actions" shows the task
