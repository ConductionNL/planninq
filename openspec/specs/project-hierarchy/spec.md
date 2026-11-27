# project-hierarchy Specification

## Purpose
Projects nest under a programme, three levels deep at most, and a programme's overview rolls up the progress of its subprojects.

## Requirements

### Requirement: A project manager nests a project under a parent

A project owner or manager MUST be able to set a parent project, up to three levels deep. A project MUST NOT become its own ancestor. The parent's overview MUST list its subprojects with their progress, and its own progress MUST include their tasks. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)"; no dedicated row).

#### Scenario: A programme shows its subprojects

- **GIVEN** a project "Programma Wonen" and two projects whose parent is "Programma Wonen", with 3 of 10 and 5 of 5 tasks done
- **AND** "Programma Wonen" itself has no tasks
- **WHEN** a member of all three opens the Overview tab of "Programma Wonen" at /projects/:id/overview
- **THEN** the page lists both subprojects with "3 of 10" and "5 of 5"
- **AND** the programme's progress reads "8 of 15 tasks done, including subprojects"

#### Scenario: A cycle is refused

- **GIVEN** project A is the parent of project B
- **WHEN** a manager of A opens the Details tab of A's project settings sidebar and picks B as A's parent
- **THEN** the save is refused with "A project cannot sit under one of its own subprojects."
- **AND** A has no parent

#### Scenario: The project list shows subprojects under their parent

- **GIVEN** a user on "Programma Wonen" and on both of its subprojects
- **WHEN** the user opens the project list at /projects
- **THEN** the subprojects appear indented under "Programma Wonen"
- **AND** the button before "Programma Wonen" hides and shows them and announces whether they are shown
