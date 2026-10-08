# project-delivery delta for portfolio-finance

## ADDED Requirements

### Requirement: A project manager sets the delivery terms on screen

A project owner (the project's manager until projects-members-and-roles adds a manager role) MUST be able to set whether a project is billable, its billing model (none, fixed price or hourly), its budget in hours and in money, its hourly rate and its planned start and end dates, from the project's Finance tab. A zero or empty budget MUST still mean "no budget agreed". Tier: V1 (docs/FEATURES.md, "Time report (estimated vs actual, per project)"; the terms are specified in this capability's requirement on delivery and billing terms).

#### Scenario: Setting a fixed-price budget

- **GIVEN** a project manager on the Finance tab at /projects/:id/finance of a project without terms
- **WHEN** the manager turns on "Billable", picks "Fixed price", enters a budget of 56000 euro and 400 hours, and saves
- **THEN** the Finance tab shows "Budget € 56,000" and "400 hours"
- **AND** the project's entry on the project list at /projects shows the Billable badge and the budget

#### Scenario: Members do not see the money

@e2e exclude The e2e suite signs in as the admin only; asserted by PlanninqRegisterSchemaTest::testFinanceLineSchemaAndItsRules, FinanceLineListenerTest::testTheOwnersLineIsStampedForTheOwnerAndThePortfolioManagers and tests/vitest/finance.spec.js "owner, portfolio managers and admins see the amounts; members do not"
- **GIVEN** a project member with the role "Member"
- **WHEN** the member opens /projects/:id/finance
- **THEN** the tab shows the billing model and the planned dates
- **AND** no amount is shown
- **AND** a GET on `/apps/openregister/api/objects/planninq/financeLine?project={id}` with the member's session returns no lines

### Requirement: A project manager compares budget with cost

The Finance tab MUST show, per cost category and per phase, the budget, the commitments, the actual cost, the forecast and what remains. Booked time MUST count as labour cost at the entry's rate, else the project's rate. A manager MUST be able to add, change and remove manual cost lines; lines from the finance system MUST be read-only. Tier: V1 (docs/FEATURES.md, "Time report (estimated vs actual, per project)"; tender 365739 requirements 3998 and 3999).

#### Scenario: Budget against actual cost with booked time

- **GIVEN** a project with a budget of 10000 euro and an hourly rate of 100 euro
- **AND** 20 hours booked on its tasks, and a manual actual cost line of 1500 euro in the category "Materials"
- **WHEN** a project manager opens the Finance tab at /projects/:id/finance
- **THEN** the row "Labour (booked time)" shows 2000 euro actual
- **AND** the row "Materials" shows 1500 euro actual
- **AND** the total shows 3500 euro spent and 6500 euro remaining

#### Scenario: Lines from the finance system cannot be edited

- **GIVEN** an actual cost line with the source "import"
- **WHEN** a project manager opens it on the Finance tab
- **THEN** the line is shown with "From the finance system" and without edit or delete buttons
