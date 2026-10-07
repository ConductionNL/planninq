# built-in-reports Specification

## Purpose
Planninq ships two ready-made reports on the Reports page: Task status and Time spent. Both are declarative dashboard pages in `src/manifest.json` over planninq's own register, drawn by the shared dashboard page of nextcloud-vue from OpenRegister's aggregation API. Planninq writes no controller for them. This spec describes what the two pages show on development at 131c691f, written after the fact (build-all decision 81). The reports a user builds and saves are a separate capability, `custom-reports`.

## Requirements

### Requirement: The Reports page offers Task status and Time spent

The Reports page (`/reports`, menu entry Reports in the settings section, `src/manifest.json:57` and `:273-293`) MUST list a Task status card under Work and a Time spent card under People and time. Each card MUST open its report page: Task status at `/reports/tasks`, Time spent at `/reports/time`.

#### Scenario: A user opens a report from the Reports page
@e2e exclude Covered by tests/e2e/app-chrome.spec.ts "Capacity is a card on Reports, not a main-nav entry", which checks the Task status and Time spent cards; that file carries no @e2e tag for this spec yet, and this round changes no test code

- **GIVEN** a signed-in user on the Reports page
- **WHEN** they choose the Task status card
- **THEN** the Task status report opens at `/reports/tasks`

### Requirement: Task status counts open, in progress and blocked tasks

The Task status report (`src/manifest.json:294-318`, page `TaskStatusReport`) MUST show three counts over the `task` schema: Open (`status` open), In progress (`status` in_progress) and Blocked (`status` blocked). Each count MUST link to the Boards page. The report MUST also show a donut of tasks by status, a donut of tasks by priority, and a Due soonest table of up to 8 open tasks sorted by due date, with the task, its due date and its assignee. Every filter on the page MUST be a single scalar equality, because OpenRegister's aggregation API ignores a multi-value or relative-date filter without saying so.

The report has no overdue count. A user sees overdue work only as the earliest dates in Due soonest.

#### Scenario: The report shows real counts
@e2e exclude Covered by tests/e2e/app-chrome.spec.ts "the task report renders real numbers, not empty cards"; that file carries no @e2e tag for this spec yet, and this round changes no test code

- **GIVEN** the register holds tasks with status open, in_progress and blocked
- **WHEN** a user opens `/reports/tasks`
- **THEN** the Open, In progress and Blocked cards each show a number
- **AND** the By status donut splits the tasks per status

#### Scenario: Due soonest lists open tasks by date
@e2e exclude Declarative widget configuration in src/manifest.json; no test asserts the table rows

- **GIVEN** three open tasks due on 3, 1 and 2 November
- **WHEN** a user opens the Task status report
- **THEN** Due soonest lists them in the order 1, 2, 3 November
- **AND** a task with status blocked is not in that table

### Requirement: Time spent sums logged minutes and splits them per person

The Time spent report (`src/manifest.json:319-339`, page `TimeSpentReport`) MUST show the number of time entries and the sum of their `duration` over the `plannedTimeEntry` schema, labelled Minutes logged because `duration` is stored in minutes. It MUST show a Per person bar chart of the summed duration grouped by `user`, with the ten largest and an other bucket, and a Most recent table of up to 8 entries with date, person, minutes and description. The two counts and the table MUST link to the Timesheet.

The report does not group or filter time by project, although its card on the Reports page says "the projects they went to".

#### Scenario: Minutes per person
@e2e exclude Covered in part by tests/e2e/app-chrome.spec.ts "the time report is reachable and titled", which checks the Minutes logged card; the per person chart has no test

- **GIVEN** Anna logged 90 minutes and Bram logged 30 minutes
- **WHEN** a user opens `/reports/time`
- **THEN** Minutes logged shows 120
- **AND** the Per person chart shows a bar of 90 for Anna and 30 for Bram
