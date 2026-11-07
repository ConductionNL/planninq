# task-people-and-tags Specification

## Purpose
Who is responsible for a task and who shares it, its priority, and its labels, set by project members from the task page, the task dialog and the board card. Built by tasks-assignment-priority-labels (archived 2026-09-29).

## Requirements

### Requirement: A project member can assign a task to a member

The system MUST let a project member set and change the one person responsible for a task
(`assignedTo`), choosing from the project's members only, on the task page and in the task
dialog. Tier: MVP (docs/FEATURES.md).

#### Scenario: Assign a task on the task page

- **GIVEN** project "Vergunningen" has members Anna and Bram, and Anna is on the task page of an unassigned task
- **WHEN** she picks Bram as the responsible person
- **THEN** the task stores Bram's uid in `assignedTo`
- **AND** the board card shows Bram's avatar and display name

#### Scenario: Only members are offered

- **GIVEN** Carla is not a member of project "Vergunningen"
- **WHEN** a member opens the people picker on one of its tasks
- **THEN** Carla is not in the list

### Requirement: A task can be shared with more than one person

The system MUST let a project member add other project members who work on a task alongside the
responsible person, stored separately from `assignedTo`. Tier: V1.

#### Scenario: Two people on one task

- **GIVEN** Bram is responsible for a task
- **WHEN** Anna adds herself under "Also working on this"
- **THEN** the task stores Anna in `sharedWith` and Bram stays in `assignedTo`
- **AND** the card shows both avatars, Bram first

### Requirement: A project member can set a task's priority

The system MUST let a project member set a task's priority to low, normal, high or urgent on the
task page and from the card's action menu on the board. Tier: MVP.

#### Scenario: Raise a priority from the board

- **GIVEN** a project member is on the board and a card shows priority normal
- **WHEN** they open the card's action menu and choose Priority, then Urgent
- **THEN** the card shows the urgent chip without a page change
- **AND** the stored task has priority `urgent`

### Requirement: A project member can attach labels to a task

The system MUST let a project member attach and remove app-wide labels on the task page, writing
the task's `labels`. Tier: MVP.

#### Scenario: Attach a label

- **GIVEN** an admin created the label "Juridisch" and a member is on a task page
- **WHEN** the member picks "Juridisch" in the labels control
- **THEN** the task's card on the board shows the "Juridisch" chip
- **AND** the board's label filter for "Juridisch" includes the task
