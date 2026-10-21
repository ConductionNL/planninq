# mobile-web Specification

## Purpose
Planninq's daily flows in a phone browser: My tasks, the task page, logging time, the timesheet and a one-column board with a column switcher, with 44 pixel targets and no sideways scroll, kept so by the phone projects of the Playwright suite.

## Requirements

### Requirement: The daily flows work in a phone browser

On a screen 360 CSS pixels wide with touch only, a user MUST be able to open My tasks, open a task, read and add comments and attachments, change the task's status, log time, and read their timesheet, without the page scrolling sideways and without any action that needs hover. Every button and link in these flows SHALL be at least 44 by 44 CSS pixels. Tier: V1 (docs/FEATURES.md, platform; this change adds the row).

#### Scenario: My tasks on a phone opens a task

- **GIVEN** a user in a phone browser 360 pixels wide, signed in to Nextcloud
- **WHEN** they open planninq and tap a task in My tasks
- **THEN** the task page opens and fits the screen width
- **AND** its status, comments and attachments are reachable by tapping

#### Scenario: Log time on a phone

- **GIVEN** the same user on a task page
- **WHEN** they tap "Log time", enter 30 minutes and save
- **THEN** the entry is saved and shown on their timesheet

#### Scenario: The timesheet shows a day list on a phone

- **GIVEN** a user with entries on three days this week
- **WHEN** they open the Timesheet on a phone
- **THEN** each day is a list of its entries with task and duration, with the week total at the top

### Requirement: The board works with a finger on a phone

On screens narrower than 600 CSS pixels the project board MUST show one column at a time with a column switcher that names every column with its card count, and a user SHALL be able to move a card to another column with the card's move menu. Tier: V1.

#### Scenario: The phone board shows one column and switches

- **GIVEN** a project member on the project board in a phone browser 360 pixels wide
- **WHEN** they tap "In progress (3)" in the column switcher
- **THEN** the board shows the In progress column with its three cards
- **AND** the switcher marks "In progress" as the current column

#### Scenario: A card moves by its menu on a phone

- **GIVEN** the same member on the To do column
- **WHEN** they tap the card's move menu and choose "In progress"
- **THEN** the card is in In progress and its task has the column's status

### Requirement: Phone layouts are tested on every change

The end-to-end suite MUST run the phone flows in an Android and an iPhone device profile wherever the suite runs, and SHALL fail when a page in those flows scrolls sideways or a flow's action cannot be reached by tapping. Tier: V1.

#### Scenario: A sideways scroll fails the suite

- **GIVEN** a change that makes the task page wider than the phone screen
- **WHEN** the phone projects of the Playwright suite run
- **THEN** the check "task page has no sideways scroll" fails
