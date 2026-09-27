# shared-workflow delta for projects-templates-shared-workflow

## ADDED Requirements

### Requirement: An admin defines workflows that projects share

An admin MUST be able to define named workflows, each with an ordered set of columns, the labels on offer and an estimate scale. A project that follows a workflow MUST render the workflow's columns on its board and offer only the workflow's labels. A change to a workflow MUST reach every project that follows it without editing those projects. Tier: Enterprise (docs/FEATURES.md, "Custom task fields" and "Default column set"; no dedicated row).

#### Scenario: One change reaches every project on the workflow

- **GIVEN** an admin who created the workflow "Vergunningverlening" with the columns "Intake", "Beoordeling" and "Besluit"
- **AND** two projects that follow it
- **WHEN** the admin adds the column "Bezwaar" after "Besluit" in the workflows section of the planninq admin settings
- **THEN** both project boards at /projects/:id show "Bezwaar" as their last column

#### Scenario: The label picker offers the workflow's labels

- **GIVEN** a workflow that offers the labels "Spoed" and "Extern"
- **AND** a project member on a project that follows it
- **WHEN** the member opens the label picker on a task in TaskDetail
- **THEN** only "Spoed" and "Extern" are offered

### Requirement: A project follows a workflow's estimate scale

A workflow MUST set which estimate a task uses: none, hours, story points or t-shirt sizes, with the allowed values. Task cards and the task detail of a project on that workflow MUST show and offer only that estimate. Tier: Enterprise (docs/FEATURES.md, "Time estimate per task"; no dedicated row for a scale).

#### Scenario: Story points on a Fibonacci scale

- **GIVEN** a workflow with the estimate scale "Story points" and the values 1, 2, 3, 5, 8 and 13
- **AND** a project member on a project that follows it
- **WHEN** the member opens a task in TaskDetail and sets the estimate
- **THEN** the estimate field offers exactly those six values
- **AND** the task card on the board shows the chosen value with the unit "pt"

### Requirement: A project manager moves a project onto a workflow

A project owner or manager MUST be able to put a project on a workflow or take it off. Moving onto a workflow MUST map each task to the column with the same title and put the rest in the first column, and the dialog MUST say how many tasks move before it saves. Tier: Enterprise (docs/FEATURES.md has no dedicated row).

#### Scenario: Moving a project onto a workflow

- **GIVEN** a project with the columns "To Do", "Intake" and "Done", holding 2, 5 and 2 tasks
- **AND** the workflow "Vergunningverlening" with the columns "Intake", "Beoordeling" and "Besluit"
- **WHEN** the project owner picks that workflow in the project settings sidebar
- **THEN** the dialog says "4 tasks move to Intake because their column is not in this workflow"
- **AND** after confirming, the board shows the workflow's three columns with every task placed
