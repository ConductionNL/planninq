# board-view-options Specification

## Purpose
A project member colours the board's cards by label or priority and splits the board into swimlanes by assignee, priority or epic. The choice is kept per person and per project.

## Requirements

### Requirement: A member can colour cards by label or priority

The system MUST let a project member colour the board's cards by their first label or by their
priority, as a coloured edge on the card, while the label or priority stays readable as text on
the card. Tier: MVP (docs/FEATURES.md).

#### Scenario: Colour by label

- **GIVEN** tasks labelled "Juridisch" (red) and "Financieel" (blue)
- **WHEN** a member chooses View, then Colour cards by label
- **THEN** the "Juridisch" cards carry a red edge and the "Financieel" cards a blue edge
- **AND** each card still shows its label chip with the label name

### Requirement: A member can split the board into swimlanes

The system MUST let a project member group the board into swimlanes by assignee, priority or epic,
with a row per value plus a row for tasks without one, each row collapsible and showing its card
count. Tier: V1.

#### Scenario: Swimlanes by assignee

- **GIVEN** Anna has three tasks, Bram two, and one task is unassigned
- **WHEN** a member chooses View, then Group by assignee
- **THEN** the board shows rows "Anna (3)", "Bram (2)" and "No assignee (1)", each with all lanes

### Requirement: Dragging across swimlanes changes the grouped field

The system MUST change a task's assignee or priority when its card is dropped in another assignee
or priority swimlane, and MUST refuse a drop in another epic swimlane. Tier: V1.

#### Scenario: Hand a task to a colleague

- **GIVEN** the board is grouped by assignee
- **WHEN** Anna drags one of her cards into Bram's row, in the same lane
- **THEN** the task's `assignedTo` is Bram and the card shows in his row

### Requirement: The board remembers each person's view

The system MUST remember a person's colour and grouping choice per project and apply it when they
open that board again. Tier: V1.

#### Scenario: Come back to a grouped board

- **GIVEN** Anna grouped the "Vergunningen" board by priority yesterday
- **WHEN** she opens it today
- **THEN** it is grouped by priority
- **AND** Bram's view of the same board is unchanged
