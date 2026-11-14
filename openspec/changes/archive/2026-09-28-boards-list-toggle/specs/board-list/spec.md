# board-list delta for boards-list-toggle

## ADDED Requirements

### Requirement: A member can see the board as a list

The system MUST let a project member switch the board page between the board and a list of the
same cards, MUST show in the list every card the board shows (after the label filter) with its
column, priority and due date, in lane order and then card order, and MUST keep the choice in the
page address. Tier: V1.

#### Scenario: Switch to the list

- **GIVEN** project "Vergunningen" has two cards in To do and one in Done
- **WHEN** a member opens its board and chooses "List"
- **THEN** a list shows the three cards, the two To do cards first, each with its column
- **AND** after a reload the page still shows the list

#### Scenario: The list follows the label filter

- **GIVEN** one of the three cards carries the label "Urgent client"
- **WHEN** the member picks that label in the filter and chooses "List"
- **THEN** the list shows that one card
