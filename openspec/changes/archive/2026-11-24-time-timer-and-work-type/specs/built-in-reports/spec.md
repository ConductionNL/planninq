# built-in-reports delta for time-timer-and-work-type

## ADDED Requirements

### Requirement: The time report splits minutes per project

The Time spent report MUST show a Per project bar chart of the summed `duration`, grouped by
the project of each time entry's task. Choosing a bar MUST open the Timesheet filtered to that
project.

#### Scenario: Minutes per project

- **GIVEN** Anna logged 90 minutes on "Herinrichting Marktplein" and 30 minutes on "Digitale balie"
- **WHEN** a user opens `/reports/time`
- **THEN** the Per project chart shows a bar of 90 for "Herinrichting Marktplein" and 30 for "Digitale balie"
- **AND** choosing a bar opens the Timesheet filtered to that project
