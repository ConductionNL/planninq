# projects-leaf-library-card Specification

## Purpose
How the projects leaf shows as a library card on a host page: it hands its count, note, actions and empty state to the host card through `onHeader` and draws none of them itself. Created by the archived change projects-leaf-library-card.

## Requirements

### Requirement: The projects leaf takes the library card

The `planninq-projects` descriptor SHALL declare `chrome: "card"`. When the host passes `onHeader`, the leaf SHALL report
the project count once the list has settled, the budget as a note, "Open in Planninq" and (with an object in scope)
"New project" as actions, and its empty state when there are no projects, and SHALL NOT draw its own figure, empty line or
buttons. After a failed read it SHALL report neither a count nor an empty state.

#### Scenario: a case without projects

- **GIVEN** a dossiq case detail page whose case has no projects
- **THEN** the projects card shows a 0 badge beside its title, the two actions in its header and the library empty state
