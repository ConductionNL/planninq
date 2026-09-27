# register-schemas delta for platform-demo-data

## REMOVED Requirements

### Requirement: Seed data loaded on install [MVP]

**Reason**: Sample projects, columns, tasks and time entries created on every install are demo data on a production instance, which hydra ADR-111 rule 3 forbids ("demo data NEVER installs itself"). They reference accounts such as `jdoe` that customer instances do not have.

**Migration**: The same projects, columns, tasks and time entries move to the example data that the setup wizard loads on request (capability `demo-data`). The five default labels stay on install (see the requirement below). Instances that already received the sample objects keep them; nothing is deleted.

## ADDED Requirements

### Requirement: Only the default labels are created on install

On a fresh install the register import MUST create the five default labels, Bug, Feature, Docs, Design and Infrastructure, with their colours, and SHALL create no project, column, task, phase, dependency or time entry. Tier: MVP.

#### Scenario: A fresh install has labels and no projects

- **GIVEN** planninq installed for the first time, with the setup wizard closed without loading example data
- **WHEN** an admin opens the Projects page and the label settings
- **THEN** there are no projects
- **AND** the labels Bug, Feature, Docs, Design and Infrastructure exist
