# project-import delta for projects-import-from-other-tools

## ADDED Requirements

### Requirement: An admin imports a project from a Jira or Trello export

An admin MUST be able to import a Trello board export in JSON and a Jira project export in
CSV. Each import MUST create a planninq project. Lists or statuses MUST become columns, and
cards or issues MUST become tasks.

#### Scenario: Import a Trello board

- **GIVEN** an admin with a Trello board export "Vergunningen.json" with three lists
- **WHEN** they import it on the planninq admin page
- **THEN** a project "Vergunningen" exists with three columns named after the lists
- **AND** every card is a task with its title, description, assignee and due date

#### Scenario: Import a Jira project

- **GIVEN** an admin with a Jira project export in CSV with the statuses "To Do", "In Progress" and "Done"
- **WHEN** they import it on the planninq admin page
- **THEN** a project exists with those three columns
- **AND** every issue is a task in the column of its status
