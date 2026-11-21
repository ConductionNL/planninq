# flow-metrics Specification

## Purpose
How work flows through a project or a portfolio: tasks per column per day, and the lead and cycle time of finished tasks, replayed from the recorded history.

## Requirements

### Requirement: A project member sees a cumulative flow diagram

Every project MUST have a cumulative flow diagram that shows, for each day of a chosen period of at most 180 days, how many tasks were in each column at the end of that day, in board order. The numbers MUST come from the recorded history of the project's tasks and MUST also be available as a table. Only people who can read the project MUST get its flow. Tier: V1 (docs/FEATURES.md, "Cumulative flow diagram (per project)").

#### Scenario: A queue growing before review

- **GIVEN** a project with the columns "To do", "Doing", "Review" and "Done"
- **AND** over the last 14 days the number of tasks in "Review" grew from 2 to 9
- **WHEN** a project member opens the Flow tab at /projects/:id/flow with the period "Last 14 days"
- **THEN** the chart shows one band per column in board order
- **AND** the table view shows 2 tasks in "Review" on the first day and 9 on the last

#### Scenario: An outsider gets no flow

@e2e exclude API-level refusal with no screen, asserted by FlowControllerTest (task 1.2)
- **GIVEN** a signed-in user who is not on the project
- **WHEN** the user sends `GET /apps/planninq/api/projects/{id}/flow`
- **THEN** the answer is 403 and holds no counts

### Requirement: A project member sees lead time and cycle time

The Flow tab MUST show, for tasks finished in the period, the lead time from creation to finish and the cycle time from the first move off the first column to finish, per task and as an average and an 85th percentile, and MUST list the ten slowest tasks. Tasks whose finish time had to be taken from the history instead of `completedAt` MUST be counted and labelled as estimated. Tier: Enterprise (docs/FEATURES.md, "Cycle time tracking (column entry to exit)").

#### Scenario: Cycle time of finished tasks

- **GIVEN** a task created on 1 September, moved from "To do" to "Doing" on 3 September and moved to "Done" on 8 September
- **WHEN** a project member opens the Flow tab for September
- **THEN** that task shows a lead time of 7 days and a cycle time of 5 days
- **AND** it links to its task page from the list of slowest tasks when it is among the ten slowest

### Requirement: A portfolio manager sees flow across a portfolio

The flow reports MUST also be available for a portfolio, over every project in it that the viewer can read, with a filter per project. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)").

#### Scenario: Cycle time across a portfolio

- **GIVEN** a portfolio manager of "Ruimte", which holds three projects with finished tasks
- **WHEN** the manager opens the portfolio flow at /portfolio/flow and picks "Ruimte"
- **THEN** the average and 85th percentile cycle time cover the finished tasks of all three projects
- **AND** unticking one project in the project filter recalculates them without it
