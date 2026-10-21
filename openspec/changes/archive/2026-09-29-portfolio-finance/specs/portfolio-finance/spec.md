# portfolio-finance delta for portfolio-finance

## ADDED Requirements

### Requirement: A portfolio manager sees money totalled across a portfolio

The Portfolio menu MUST have a finance sub-page that, for a chosen portfolio, lists every project in it with budget, commitments, actual cost including labour, forecast and remaining, and totals them. Only people who can read a project's finance lines MUST see its figures. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)"; tender 365739 requirement 4000).

#### Scenario: Totals for a portfolio

- **GIVEN** a portfolio manager of "Ruimte", which holds two projects with budgets of 50000 and 30000 euro and actual costs of 20000 and 35000 euro
- **WHEN** the manager opens the portfolio finance page at /portfolio/finance and picks "Ruimte"
- **THEN** the page lists both projects with their figures
- **AND** the totals row shows a budget of 80000 euro and an actual cost of 55000 euro
- **AND** the second project's remaining amount is shown as over budget, in text as well as colour

#### Scenario: A project outside your reach is not totalled

@e2e exclude The e2e suite signs in as the admin only, who sees every project's money; asserted by tests/vitest/portfolioFinance.spec.js "totals nothing for a member who holds no role on the portfolio"

- **GIVEN** a user who is a member but not a manager of one project in "Ruimte", and holds no role on the portfolio
- **WHEN** the user opens /portfolio/finance and picks "Ruimte"
- **THEN** the page says "You cannot see the money of any project in this portfolio"
