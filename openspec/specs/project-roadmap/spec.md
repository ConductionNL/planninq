# project-roadmap Specification

## Purpose
The project roadmap is the Roadmap view of a project's Timeline tab: the project's releases as markers on their target date and its epics as bars over time, with a list that carries the same facts for keyboard and screen-reader use. It is separate from the product's own "Features & roadmap" page.

## Requirements

### Requirement: A project member can mark a task as an epic and link tasks to it

A project member MUST be able to mark a task as an epic, which SHALL store `issueType: epic`, and MUST be able to link any other task of the same project to an epic through the task's `epic` field. The epic picker SHALL offer only epics of the task's own project and SHALL NOT offer the task itself. Tier: V1.

#### Scenario: A member makes an epic and links a task to it

- **GIVEN** a project member on the task detail page of "Self-service export"
- **WHEN** they switch on "This task is an epic", then open the task "Export to CSV" and pick "Self-service export" in the Epic field
- **THEN** "Self-service export" has `issueType` `epic`
- **AND** "Export to CSV" has `epic` referencing "Self-service export"

#### Scenario: Another project's epic is not offered

- **GIVEN** a project member on a task of project A, and an epic in project B
- **WHEN** they open the Epic field
- **THEN** the epic of project B is not offered

### Requirement: A project member can see the project's releases and epics on a roadmap

Each project MUST have a roadmap, the Roadmap view of the project's Timeline tab at /projects/:id/timeline?view=roadmap, that shows the project's releases as markers on their target date and its epics as bars on a time axis. An epic without its own start and due date SHALL span from the earliest start date to the latest due date of the tasks linked to it, and an epic with no dated task at all MUST be listed under "Not scheduled yet" rather than dropped. The page SHALL read only objects of that project, through the OpenRegister object API. Tier: V1.

#### Scenario: The member opens the roadmap from the project tabs

- **GIVEN** a project member on the project board at /projects/:id
- **WHEN** they open the Timeline tab and press "Roadmap"
- **THEN** the roadmap opens at /projects/:id/timeline?view=roadmap with the project's name in its breadcrumb
- **AND** a reload keeps the roadmap open

#### Scenario: The roadmap shows releases and epics over time

- **GIVEN** a project with the release "Version 2.0" due 2026-12-01 and the epic "Self-service export" without dates, whose linked tasks start on 2026-10-05 and are due by 2026-11-20
- **WHEN** a project member opens the roadmap
- **THEN** a marker labelled "Version 2.0" sits on 2026-12-01
- **AND** a bar labelled "Self-service export" runs from 2026-10-05 to 2026-11-20

#### Scenario: An epic with no dates is not dropped

- **GIVEN** an epic none of whose linked tasks has a date
- **WHEN** a project member opens the roadmap
- **THEN** the epic is listed under "Not scheduled yet"

### Requirement: The roadmap has a list equivalent

The roadmap MUST list the same releases and epics as the chart in a list ordered by date, with each release's progress and each epic's date span in text, so a keyboard or screen-reader user gets every fact the chart shows. Tier: V1.

#### Scenario: The release list works without the chart

- **GIVEN** a project member using only the keyboard on the roadmap
- **WHEN** they tab past the chart into the list
- **THEN** each release is a row with its name, target date and progress, reachable with Tab
- **AND** each epic is a row with its name and its span as text

### Requirement: The product roadmap stays separate from the user roadmap

The footer page "Features & roadmap" at /features-roadmap MUST keep describing planninq itself, and the project roadmap SHALL NOT be reachable from it or replace it. Tier: V1.

#### Scenario: The features and roadmap page is unchanged

- **GIVEN** a user in planninq
- **WHEN** they open "Features & roadmap" in the footer
- **THEN** it shows planninq's own feature roadmap as before
- **AND** it shows no project release or epic
