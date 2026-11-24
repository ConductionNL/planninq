# project-templates delta for projects-templates-shared-workflow

## ADDED Requirements

### Requirement: A project manager keeps templates and starts projects from them

A project owner or manager MUST be able to mark a project as a template. Templates MUST be listed apart from working projects. Anyone who may create a project MUST be able to start one from a template, which copies the template's columns, phases and tasks with their dates shifted to the new start date. Tier: V1 (docs/FEATURES.md, "Project templates").

#### Scenario: Marking a project as a template

- **GIVEN** a project owner on the Details tab of the project settings sidebar at /projects/:id
- **WHEN** the owner turns on "Use as template" and saves
- **THEN** the project is listed under the Templates chip of the project list at /projects
- **AND** it is no longer listed under the Active chip

#### Scenario: Starting a project from a template

- **GIVEN** a template "Aanbesteding" with 4 columns, 2 phases and 12 tasks, and a start date of 1 March
- **AND** a user who may create projects
- **WHEN** the user presses "New project" on /projects, picks "Aanbesteding", enters the title "Aanbesteding wegbeheer" and the start date 1 June, and presses "Create project"
- **THEN** the new board at /projects/:id shows the same 4 columns and 12 tasks, all open
- **AND** a task that was due on 15 March in the template is due on 15 June

### Requirement: A project manager copies a project with its structure

A project owner or manager MUST be able to copy a project and choose which parts come along: columns, tasks, phases, dependencies and people. The copy MUST keep every reference between the copied objects intact and MUST NOT carry time entries or comments. A failed copy MUST leave nothing behind. Tier: V1 (docs/FEATURES.md, "Project templates").

#### Scenario: Copying a project without its people

- **GIVEN** a project manager on a project with 3 columns, 20 tasks, 2 dependencies and 4 people
- **WHEN** the manager chooses "Copy project" in the project settings sidebar, keeps columns, tasks and dependencies, clears "People", enters a title and confirms
- **THEN** a new project opens with 3 columns and 20 open tasks
- **AND** the 2 dependencies link the copied tasks, not the originals
- **AND** the manager is the only person on the new project

#### Scenario: A failed copy leaves nothing behind

@e2e exclude Failure injection needs a mocked ObjectService, asserted by ProjectCopyServiceTest (task 1.2)
- **GIVEN** a copy that fails while writing tasks
- **WHEN** `POST /apps/planninq/api/projects/{id}/copy` returns
- **THEN** the answer is 500 and names the failed step
- **AND** no project, column or task of the partial copy remains
