# people-capacity Specification

## Purpose
The Capacity report at /portfolio shows open work per person and per project across the active projects the viewer can read, so a team lead sees who holds how much work, and where. Built by portfolio-people-capacity (2026-09-29).

## Requirements

### Requirement: A team lead sees open work per person across projects

The Capacity report MUST show one row per person who holds open work in the projects the viewer can read, with open tasks, overdue tasks, remaining estimated hours, the number of open tasks without an estimate, open tasks due in the next 14 days, and tasks shared with them. Open work without an assignee MUST have its own row. Each row MUST open into a breakdown per project. Tier: V1 (docs/FEATURES.md, "Team workload report (tasks per user)").

#### Scenario: Two people across two projects

- **GIVEN** a user on the projects "Omgevingsvisie" and "Wegbeheer"
- **AND** "Ada Jansen" has 3 open tasks in "Omgevingsvisie" with 10 hours remaining and 2 open tasks in "Wegbeheer" without an estimate
- **AND** "Bram de Vries" has 1 open task in "Wegbeheer", past its due date, with 4 hours remaining
- **AND** 2 open tasks in "Wegbeheer" have no assignee
- **WHEN** the user opens the Capacity report at /portfolio
- **THEN** the "By person" view shows "Ada Jansen" with 5 open tasks, 10 h and "2 tasks without an estimate"
- **AND** "Bram de Vries" with 1 open task, 1 overdue and 4 h
- **AND** an "Unassigned" row with 2 open tasks
- **AND** opening "Ada Jansen" shows 3 tasks in "Omgevingsvisie" and 2 in "Wegbeheer"

#### Scenario: Shared tasks do not double the hours

- **GIVEN** an open task assigned to "Ada Jansen", shared with "Bram de Vries", with 6 hours remaining
- **WHEN** a user opens the "By person" view at /portfolio
- **THEN** "Ada Jansen" counts the 6 hours
- **AND** "Bram de Vries" shows the task under "Shared" without hours

### Requirement: The report can be narrowed and switched to projects

The Capacity report MUST let the viewer limit it to one portfolio or to chosen projects, and MUST keep the per-project view one control away. The page and its Reports card MUST describe what the page shows. Tier: V1 (docs/FEATURES.md, "Team workload report (tasks per user)").

#### Scenario: Switching between people and projects by keyboard

- **GIVEN** a user on the Capacity report at /portfolio with the "By person" view
- **WHEN** the user moves focus to "By project" and presses Enter
- **THEN** the table shows one row per project with members, open and overdue tasks
- **AND** "By project" reports itself as pressed

#### Scenario: Limiting to a portfolio

- **GIVEN** a portfolio manager of "Ruimte" who is also on projects outside it
- **WHEN** the manager picks "Ruimte" in the portfolio picker on /portfolio
- **THEN** the rows count only open work in the projects of "Ruimte"
