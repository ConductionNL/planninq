# task-calendar delta for planning-calendar

## ADDED Requirements

### Requirement: A project member can see a project's tasks on a calendar

Each project MUST have a calendar page at /projects/:id/calendar, reached from the project board header, that shows the project's tasks on their due date in a month view and a week view. A task with a start date and no due date SHALL be shown on its start date and marked as starting. Tasks without either date SHALL NOT be shown on the calendar. Tier: V1 (docs/FEATURES.md, kanban board; this change adds the row).

#### Scenario: A member sees project tasks on their due dates

- **GIVEN** a project member and a project with the task "Export to CSV" due 2026-10-16 and the task "Import from CSV" due 2026-10-23
- **WHEN** they press "Calendar" on the project board and the calendar opens on October 2026
- **THEN** "Export to CSV" is listed on 16 October and "Import from CSV" on 23 October
- **AND** choosing "Export to CSV" opens its task page

#### Scenario: A member switches to week view

- **GIVEN** the same member on the October 2026 month view
- **WHEN** they choose "Week" and move to the week of 12 October
- **THEN** the view shows 12 to 18 October with "Export to CSV" on Friday 16 October

### Requirement: Every user has a calendar of the tasks assigned to them

The system MUST offer each user a "My calendar" page at /my-calendar that shows the tasks assigned to them or shared with them across every project they are a member of, on their due dates, with the project named on each task. It SHALL NOT show tasks that are neither assigned to nor shared with them. Tier: V1.

#### Scenario: My calendar shows only my tasks across projects

- **GIVEN** a user assigned to "Export to CSV" in project A and "Review budget" in project B, and a colleague's task "Plan demo" in project A
- **WHEN** they choose "Show as calendar" on their My tasks page
- **THEN** "Export to CSV" and "Review budget" are shown on their due dates with their project names
- **AND** "Plan demo" is not shown

### Requirement: The calendar works without a pointer and without the grid

The calendar MUST be operable with the keyboard alone, SHALL expose the month grid as a table with a caption naming the month, and MUST offer a list view that shows the same tasks grouped by date. Tier: V1.

#### Scenario: The calendar is operable with the keyboard

- **GIVEN** a project member using only the keyboard on the project calendar
- **WHEN** they tab to "Next", press Enter, then tab into the grid
- **THEN** the caption reads the next month's name
- **AND** each task in the grid is a link reachable with Tab

#### Scenario: The list view shows the same tasks

- **GIVEN** a project member on the October 2026 month view
- **WHEN** they choose "List"
- **THEN** the page lists every task of the month under its date, in date order
