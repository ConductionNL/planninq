# my-work Specification

## Purpose
My tasks lists a user's open work across every project they can read, the dashboard shows their task figures, and "My projects" keeps their own pinned order. Built by portfolio-my-work-dashboard (2026-09-29); it implements the flat spec openspec/specs/dashboard-my-work/spec.md.

## Requirements

### Requirement: A user sees all their open tasks across projects in one list

The system MUST offer a My tasks page listing every open task, in every project the user is a
member of, that is assigned to the user or shared with them, grouped as Overdue, Due this week and
Later, each group sorted from urgent to low priority. Tier: MVP (docs/FEATURES.md).

#### Scenario: Tasks from two projects

- **GIVEN** Anna is responsible for an overdue task in "Vergunningen" and a task due Friday in "Handhaving"
- **WHEN** she opens My tasks
- **THEN** the first shows under Overdue and the second under Due this week, each with its project name

#### Scenario: Change a status without leaving

- **GIVEN** a task is listed on My tasks
- **WHEN** Anna sets its status to In progress from the row
- **THEN** the task is saved with that status and she stays on My tasks

#### Scenario: Nothing assigned

- **GIVEN** Bram has no open tasks assigned or shared
- **WHEN** he opens My tasks
- **THEN** he sees "No tasks assigned to you" and a "Browse projects" action

### Requirement: The dashboard shows my task figures

The system MUST show on the dashboard the number of my open tasks, my overdue tasks, my tasks in
progress and my tasks completed today, and each figure MUST open My tasks filtered to it. Tier: MVP.

#### Scenario: Figures for a user

- **GIVEN** Anna has four open tasks, one of them overdue and two in progress, and finished one today
- **WHEN** she opens the dashboard
- **THEN** it shows Open 4, Overdue 1, In progress 2 and Completed today 1
- **AND** choosing Overdue opens My tasks showing that one task

### Requirement: "Projects I am in" counts only my projects

The system MUST count only projects whose members include the user in the "Projects I am in"
figure, also for an admin. Tier: MVP.

#### Scenario: An admin's count

- **GIVEN** an admin who is a member of two of ten projects
- **WHEN** they open the dashboard
- **THEN** "Projects I am in" shows 2

### Requirement: A user can put their projects in their own order

The system MUST let a user pin projects and order them in the dashboard's "My projects" panel, and
MUST keep that order for that user only. Tier: V1.

#### Scenario: Pin a project

- **GIVEN** Anna is a member of five projects
- **WHEN** she pins "Handhaving" in My projects
- **THEN** "Handhaving" is listed first, also after a reload
- **AND** Bram's dashboard order is unchanged

### Requirement: The Nextcloud dashboard shows my active projects through OpenRegister's integrations widget

Planninq MUST NOT register a Nextcloud dashboard widget of its own. Its `planninq-projects` leaf
MUST declare the `user-dashboard` surface, so OpenRegister's integrations widget on the Nextcloud
dashboard shows the user's active projects (`status` `active`) under "Projects", each opening the
project in planninq. The user's tasks are not listed on the Nextcloud dashboard; they stay on My
tasks and on the planninq dashboard. Tier: V1.

#### Scenario: Active projects on the Nextcloud dashboard

@e2e exclude Rendered by OpenRegister's dashboard widget, outside planninq's own pages; the scoping is asserted by Vitest (projectScope.spec.js)
- **GIVEN** Anna can read the active project "Handhaving" and the closed project "Vergunningen 2025"
- **WHEN** she opens the Nextcloud dashboard and expands "Projects" in OpenRegister's integrations widget
- **THEN** "Handhaving" is listed
- **AND** "Vergunningen 2025" is not listed
- **AND** choosing "Handhaving" opens the project in planninq
