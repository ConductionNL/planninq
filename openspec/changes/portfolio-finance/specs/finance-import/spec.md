# finance-import delta for portfolio-finance

## ADDED Requirements

### Requirement: The finance system fills cost lines on the project number

Planninq MUST accept cost lines written by the integration app, carrying the finance system's line id and project number. A line MUST land on the project whose key equals the project number. Writing the same finance line again MUST update it, not add a second one. Planninq itself MUST NOT contain code that reads the finance system. Tier: Enterprise (docs/FEATURES.md, "Import from CSV (tasks bulk import)" is the nearest row; tender 365739 requirements 4001, 4023 and 4127).

#### Scenario: An imported line lands on its project

@e2e exclude The writer is integriq's service account, asserted by the Newman requests of task 3.1
- **GIVEN** a project with the key "OMG" and the integration account in the group `planninq-finance-import`
- **WHEN** the integration account sends `POST /apps/openregister/api/objects/planninq/financeLine` with `projectKey` "OMG", `kind` "actual", `amount` 1200 and `externalRef` "FIN-778"
- **AND** sends the same line again with `amount` 1250
- **THEN** the project's Finance tab at /projects/:id/finance shows one line from the finance system of 1250 euro

### Requirement: An admin resolves finance lines that match no project

A cost line whose project number matches no project MUST be kept and listed in Beheer. An admin MUST be able to assign it to a project from that list. Tier: Enterprise (docs/FEATURES.md has no row; ADR-001 rule 5).

#### Scenario: Assigning an unmatched line

- **GIVEN** an imported line with `projectKey` "OMGV" and no project
- **WHEN** an admin opens "Unmatched finance lines" in Beheer, picks the line and assigns it to the project "Omgevingsvisie"
- **THEN** the line leaves the unmatched list
- **AND** it appears on the Finance tab of "Omgevingsvisie"
