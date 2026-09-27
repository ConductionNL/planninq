# time-timer delta for time-timer-and-work-type

## ADDED Requirements

### Requirement: A person can start a timer on a task

A signed-in person MUST be able to start a timer on a task they can see, from the task page or from their Timesheet, and SHALL have at most one running timer. The running timer MUST show the task and the elapsed time on their My tasks page, their Timesheet and the task's page, and SHALL still be running after a page reload or on another device. Starting a timer SHALL NOT write any time entry. Tier: V1 (docs/FEATURES.md, time tracking: timer).

#### Scenario: A person starts a timer on a task and sees it on My tasks

- **GIVEN** Anna on the task page of "Export to CSV"
- **WHEN** she presses "Start timer" and then opens My tasks
- **THEN** My tasks shows "Export to CSV" with a running time and Stop and Discard buttons
- **AND** no time entry exists yet for Anna on that task

#### Scenario: The timer survives a reload

- **GIVEN** Anna with a timer running for 20 minutes
- **WHEN** she reloads the page
- **THEN** the timer still shows about 20 minutes and keeps counting

#### Scenario: Starting a second timer asks first

- **GIVEN** Anna with a timer running on "Export to CSV"
- **WHEN** she presses "Start timer" on "Printer 2nd floor"
- **THEN** she is asked "A timer is running on Export to CSV. Stop it and start one on Printer 2nd floor?"

### Requirement: Stopping a timer prepares a time entry the person confirms

Stopping a timer MUST open the time entry form for that task with the elapsed time rounded up to whole minutes, at least one, and today's date, and SHALL book nothing until the person saves. Cancelling the form SHALL book nothing. When the timer ran longer than 12 hours, the form MUST warn before saving. Discarding a timer SHALL clear it without booking. The saved entry SHALL go through the same write path as a manually logged entry. Tier: V1.

#### Scenario: Stopping opens the form with the measured time

- **GIVEN** Anna with a timer on "Export to CSV" that has run 42 minutes and 10 seconds
- **WHEN** she presses Stop
- **THEN** the "Log time" form opens for "Export to CSV" with duration "43m" and today's date
- **AND** after she saves, her Timesheet lists 43 minutes on "Export to CSV"

#### Scenario: Cancelling the form books nothing

- **GIVEN** the form opened by Stop
- **WHEN** Anna cancels it
- **THEN** no time entry is created and no timer is running

#### Scenario: Discard books nothing

- **GIVEN** Anna with a running timer
- **WHEN** she presses Discard
- **THEN** the timer is gone and no time entry is created

#### Scenario: A long timer is flagged

- **GIVEN** a timer started yesterday at 17:00 and stopped today at 07:00
- **WHEN** Anna presses Stop
- **THEN** the form says "This timer ran for 14 hours. Check the duration before you save."
