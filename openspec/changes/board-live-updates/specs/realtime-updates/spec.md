# realtime-updates delta for board-live-updates

## ADDED Requirements

### Requirement: The project board updates live (REQ-Live-Board)

The project board MUST show a colleague's change to a task of that project without a reload.
A moved card MUST move to its new lane, a new task MUST appear and a deleted task MUST
disappear. A card the user is dragging MUST NOT be moved by an update until the user drops it.

#### Scenario: A colleague moves a card

- **GIVEN** Anna and Bram have the board of project "Vergunningen" open
- **WHEN** Bram moves the card "Bouwtekening controleren" from "To do" to "Doing"
- **THEN** Anna sees the card in "Doing" without reloading the page

#### Scenario: A card being dragged stays under the pointer

- **GIVEN** Anna is dragging the card "Bouwtekening controleren"
- **WHEN** Bram changes the title of that task
- **THEN** the card stays where Anna holds it
- **AND** the new title shows once Anna drops the card
