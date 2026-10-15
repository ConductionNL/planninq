## ADDED Requirements

### Requirement: Logged hours are stored once, on humaniq's time entry

When a user logs time on a planninq task, the system SHALL write the hours to humaniq's `TimeEntry` object and SHALL NOT keep its own copy of `date`, `duration`, `user`, `description`, `billable`, `project` or `task`. The planninq `plannedTimeEntry` object MUST hold only `contractorRef`, `hourlyRate` and a reference to that humaniq `TimeEntry`.

#### Scenario: Logging time writes one humaniq time entry
- **WHEN** a project member logs 90 minutes on task T in project P on 7 October
- **THEN** the system SHALL create one humaniq `TimeEntry` with `date` 7 October, `hours` 1.5, `userId` of that member, `projectId` P, `domainObjectType` `task` and `domainObjectRef` T
- **AND** it SHALL create one `plannedTimeEntry` whose `timeEntry` reference points at that `TimeEntry`

#### Scenario: A rate stays in planninq
- **WHEN** a project owner sets an hourly rate on a logged entry
- **THEN** the system SHALL store the rate on the `plannedTimeEntry`
- **AND** the humaniq `TimeEntry` MUST NOT change

### Requirement: Planninq pages read the hours from humaniq

The timesheet, the task's logged time, the estimate comparison and the time reports SHALL read `date`, `hours` and `userId` from humaniq's `TimeEntry`, and SHALL show `hours` as the same human-readable duration the user typed (1.5 shows as "1h 30m").

#### Scenario: The timesheet shows the humaniq hours
- **WHEN** a user opens their timesheet after logging 90 minutes on a task
- **THEN** the system SHALL show one entry of "1h 30m" on that date, read from humaniq's `TimeEntry`

#### Scenario: An hour changed in humaniq shows in planninq
- **WHEN** an HR officer corrects that entry in humaniq to 2 hours
- **THEN** the planninq timesheet and the task SHALL show "2h" on the next read

### Requirement: Time widgets hide when humaniq is absent

Every dashboard widget that reads logged hours SHALL declare `requiredApp: humaniq`, and the system MUST hide such a widget when humaniq is not installed or not enabled. Logging time MUST be refused with a message that names humaniq, rather than failing silently.

#### Scenario: No humaniq, no empty widget
- **WHEN** a user opens the time report on an instance without humaniq
- **THEN** the system SHALL NOT render the four time widgets
- **AND** it SHALL NOT render them empty

#### Scenario: No humaniq, logging time says why
- **WHEN** a user chooses "Log time" on an instance without humaniq
- **THEN** the system SHALL show "Time logging needs the humaniq app" and SHALL NOT write any object

### Requirement: Existing entries move once, without loss

A repair step SHALL move every existing `timeEntry` row to one humaniq `TimeEntry` plus one `plannedTimeEntry`, converting `duration` minutes to `hours`, and MUST be idempotent. The retired `timeEntry` schema SHALL be pruned only after it owns no objects.

#### Scenario: Migration converts minutes to hours
- **WHEN** the repair step runs on an entry with `duration` 45
- **THEN** the system SHALL create a humaniq `TimeEntry` with `hours` 0.75 and a `plannedTimeEntry` that references it

#### Scenario: Running the repair twice changes nothing
- **WHEN** the repair step runs a second time
- **THEN** the system SHALL create no further `TimeEntry` or `plannedTimeEntry` objects
