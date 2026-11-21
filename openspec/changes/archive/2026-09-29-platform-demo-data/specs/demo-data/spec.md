# demo-data delta for platform-demo-data

## ADDED Requirements

### Requirement: Loaded example data is visible to the admin who loads it

When an admin loads the example data from the setup wizard, the system MUST make that admin the owner and a member of every example project and the assignee of some example tasks, so the example projects SHALL appear on their Projects list, Boards page, Portfolio and My tasks. No example object SHALL reference a user account the instance does not have. Tier: MVP (docs/FEATURES.md, admin settings: OpenRegister setup).

#### Scenario: The admin loads example data and sees the projects

- **GIVEN** an admin on a fresh planninq install with the setup wizard open
- **WHEN** they choose "Example data" and then "Load the example data"
- **THEN** the Projects page lists "Client Portal v2", "Infrastructure Migration" and "Onboarding Automation"
- **AND** the admin is shown as owner and member of each

#### Scenario: My tasks shows example tasks due around today

- **GIVEN** the example data loaded today
- **WHEN** the admin opens My tasks
- **THEN** it lists example tasks assigned to them, with one overdue by a few days and others due in the coming days

### Requirement: Example data is consistent and follows planninq's rules

Every reference between example objects MUST point at an object the example data contains, every example object SHALL validate against its schema, and every example dependency MUST join two different tasks of the same project without forming a cycle. Tier: MVP.

#### Scenario: The example board shows cards in columns

- **GIVEN** the example data loaded
- **WHEN** the admin opens the board of "Client Portal v2"
- **THEN** its columns show the project's example tasks as cards

#### Scenario: The example timeline draws dependency arrows

- **GIVEN** the example data loaded
- **WHEN** the admin opens the timeline of "Client Portal v2"
- **THEN** the example tasks appear as bars with arrows between different tasks
- **AND** no task has an arrow to itself

### Requirement: Example dates are placed around the load day

The system MUST shift every date in the example data so that the dataset's anchor date falls on the day the data is loaded. Tier: MVP.

#### Scenario: Dates follow the load day

- **GIVEN** an example task due three days after the dataset's anchor date
- **WHEN** the admin loads the example data on 5 October 2026
- **THEN** the task is due on 8 October 2026
