# board-filters Specification

## Purpose
A project member narrows the board by assignee, label, priority and due date, each "is" or "is not", keeps the filter in the page address, and saves it privately or for the project. Built by change `openspec/changes/archive/2026-09-30-boards-filters`.

## Requirements

### Requirement: A member can filter the board by assignee, label, priority and due date

The system MUST let a project member narrow the board by assignee (including "Me" and
"Unassigned"), label, priority and due date (overdue, due this week, no date), and lane counts MUST
follow the filter. Tier: MVP (docs/FEATURES.md).

#### Scenario: Only urgent work

- **GIVEN** a board with two urgent and five normal tasks
- **WHEN** a member sets Priority is Urgent in the filter bar
- **THEN** only the two urgent cards show
- **AND** the bar says "Showing 2 of 7 tasks"

#### Scenario: My tasks

- **GIVEN** Anna is responsible for three tasks on the board
- **WHEN** she sets Assignee is Me
- **THEN** only her three cards show

### Requirement: A member can exclude a value

The system MUST let a member invert any filter dimension to "is not". Tier: V1.

#### Scenario: Everything not assigned to me

- **GIVEN** Anna is responsible for three of ten tasks
- **WHEN** she sets Assignee is not Me
- **THEN** the other seven cards show, including unassigned ones

### Requirement: The filter survives a reload and can be shared as a link

The system MUST keep the active filter in the page address, restore it on load, and open the same
filtered view for anyone who follows the link and may see the project. Tier: V1.

#### Scenario: Send the view to a colleague

- **GIVEN** Anna filtered the board to Label is "Juridisch" and copied the address
- **WHEN** Bram, a member of the same project, opens that address
- **THEN** his board shows the same filter applied

### Requirement: A member can save a filter for themselves or for the project

The system MUST let a project member save the active filter under a name, privately or shared
with the project, and apply it again from a list. Only the owner of a saved filter or an admin MAY
change or delete it. Tier: V1.

#### Scenario: A shared saved filter

- **GIVEN** Anna saved "Overdue legal work" with "Share with the project" on
- **WHEN** Bram opens the saved filters menu on the same board
- **THEN** he sees "Overdue legal work" and applying it filters his board
- **AND** he is offered no rename or delete for it

@e2e exclude needs a second member account the CI e2e run does not have; the read rule (owner, or members when shared) and the owner-only update and delete rules are asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testBoardFilterSchemaIsOwnedAndSharedWithTheProject, the owner stamp by tests/unit/Listener/BoardFilterOwnerListenerTest.php, the hidden rename and delete by tests/vitest/boardFilter.spec.js "only the owner or an admin renames or deletes a saved filter", and save, apply, rename and delete as the owner by tests/e2e/board-filters.spec.ts

#### Scenario: A private saved filter

- **GIVEN** Anna saved "My follow-ups" without sharing
- **WHEN** Bram opens the saved filters menu
- **THEN** "My follow-ups" is not listed

@e2e exclude needs a second member account the CI e2e run does not have; the read rule that lists a private filter only to its owner is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testBoardFilterSchemaIsOwnedAndSharedWithTheProject
