# projects-leaf-summary Delta

**Status**: proposed
**Scope**: planninq

## ADDED Requirements

### Requirement: The projects leaf reports a summary to a linked card

When the host passes `onSummary`, the projects leaf SHALL draw nothing and SHALL report, once the list has settled,
`{ count, noun, rows, actions }`: a row per listed project linking to it, "New project" when an object is in scope, and
"Open in Planninq". It SHALL report nothing while loading or after a failed read.

#### Scenario: a case without projects in the Gekoppeld card

- **GIVEN** a dossiq case without projects
- **THEN** the leaf reports count 0, the noun "projecten" and the actions "Nieuw project" and "Openen in Planninq"

### Requirement: The empty state names a case on a case

On a case the projects leaf's empty state SHALL read "No projects for this case yet" (nl "Nog geen projecten voor deze
zaak"); on other host objects it keeps "No projects for this client yet".
