# record-retention delta for retention-on-planninq-records

## ADDED Requirements

### Requirement: Planninq records carry a retention category

The `project`, `task` and `plannedTimeEntry` schemas MUST declare a selectielijst category, and
the planninq admin settings MUST let an admin pick the category per schema from OpenRegister's
selection list. When a project is completed or archived, the project and its tasks and time
entries MUST get an archive action date from that category, so OpenRegister can put them on a
destruction list.

#### Scenario: An archived project reaches a destruction list

- **GIVEN** an admin set the category with a retention of one year for projects, tasks and time entries
- **AND** a project with 12 tasks that was archived more than a year ago
- **WHEN** OpenRegister's daily destruction check runs
- **THEN** a destruction list holds the project and its 12 tasks

#### Scenario: An active project is not listed

- **GIVEN** the same category and an active project created two years ago
- **WHEN** the daily destruction check runs
- **THEN** no destruction list holds that project or its tasks
