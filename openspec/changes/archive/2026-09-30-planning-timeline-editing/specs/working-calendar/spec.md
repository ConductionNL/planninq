# working-calendar delta for planning-timeline-editing

## ADDED Requirements

### Requirement: An admin maintains the working week and non-working days

The admin settings MUST let an admin choose the working weekdays (default Monday to Friday) and keep a list of non-working days, each a date with a name. The system SHALL reject a malformed value without storing it, and SHALL return both settings to every signed-in user through GET /apps/planninq/api/settings. Tier: V1.

#### Scenario: An admin adds a holiday

- **GIVEN** an admin on the planninq admin settings page, section "Working days and holidays"
- **WHEN** they add 25 December 2026 named "Christmas Day" and save
- **THEN** `non_working_days` holds that entry
- **AND** a project member's GET /apps/planninq/api/settings returns it

#### Scenario: An admin fills in the Dutch holidays for a year

- **GIVEN** an admin in the same section
- **WHEN** they choose "Add Dutch national holidays" for 2027
- **THEN** the list gains the Dutch national holidays of 2027, including Easter Monday 29 March and King's Day 27 April
- **AND** each added entry can be removed again before saving

#### Scenario: A malformed value is refused

- **GIVEN** an admin client
- **WHEN** it posts `non_working_days` with a value that is not a list of dated entries
- **THEN** the stored value is unchanged and the rejection is logged

### Requirement: Dates set on the timeline land on working days

When a member moves or edits a task on the timeline, the system MUST move a start date that falls on a non-working day to the next working day, and MUST count a task's length in working days, using the admin's working weekdays and non-working days. The timeline SHALL shade every non-working day and SHALL give a listed holiday its name as the day's accessible label. Tier: V1.

#### Scenario: A dropped start on a holiday moves to the next working day

- **GIVEN** a project member on the timeline, 28 December 2026 listed as a non-working day, and a two-working-day task
- **WHEN** they drop the task so it starts on 28 December 2026
- **THEN** the task starts on Tuesday 29 December and is due on Wednesday 30 December 2026

#### Scenario: A holiday is shaded and named on the timeline

- **GIVEN** 25 December 2026 listed as "Christmas Day"
- **WHEN** a project member opens a timeline that spans that date
- **THEN** the day is shaded like a weekend
- **AND** its tick has the accessible label "Christmas Day"
