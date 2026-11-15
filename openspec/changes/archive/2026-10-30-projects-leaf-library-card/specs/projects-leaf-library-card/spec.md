# projects-leaf-library-card Delta

**Status**: proposed
**Scope**: planninq

## ADDED Requirements

### Requirement: The projects leaf takes the library card

The `planninq-projects` descriptor SHALL declare `chrome: "card"`. When the host passes `onHeader`, the leaf SHALL report
the project count once the list has settled, the budget as a note, "Open in Planninq" and (with an object in scope)
"New project" as actions, and its empty state when there are no projects, and SHALL NOT draw its own figure, empty line or
buttons. After a failed read it SHALL report neither a count nor an empty state.

#### Scenario: a case without projects

- **GIVEN** a dossiq case detail page whose case has no projects
- **THEN** the projects card shows a 0 badge beside its title, the two actions in its header and the library empty state
