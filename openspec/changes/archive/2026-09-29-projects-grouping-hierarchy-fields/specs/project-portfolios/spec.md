# project-portfolios delta for projects-grouping-hierarchy-fields

## ADDED Requirements

### Requirement: Projects are grouped into portfolios

An admin MUST be able to create portfolios and name their managers. A project owner or manager MUST be able to put a project in one portfolio. The project list and the board picker MUST group projects by portfolio, in the portfolio's order, with projects outside any portfolio in their own section, and MUST offer a portfolio filter. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)").

#### Scenario: The project list groups by portfolio

- **GIVEN** an admin who created the portfolios "Ruimte" and "Dienstverlening"
- **AND** a user on three projects: two in "Ruimte" and one in no portfolio
- **WHEN** the user opens the project list at /projects
- **THEN** the list shows a "Ruimte" section with two projects and a "No portfolio" section with one
- **AND** each section heading is a button that folds the section and announces whether it is open

#### Scenario: A project manager moves a project into a portfolio

- **GIVEN** a project manager on the Details tab of the project settings sidebar at /projects/:id
- **WHEN** the manager picks "Dienstverlening" under "Portfolio" and saves
- **THEN** the project moves to the "Dienstverlening" section of /projects and of the board picker at /boards

### Requirement: Portfolio managers read every project in their portfolio

A portfolio manager MUST be able to read every project in that portfolio, with its tasks, risks and log, without being a project member. The right MUST follow the project when it moves between portfolios and MUST end when the person stops being a manager of the portfolio. A client MUST NOT be able to grant itself this right. Tier: Enterprise (docs/FEATURES.md, "Project portfolios (grouping across projects)").

#### Scenario: A portfolio manager sees a project they are not on

@e2e exclude The e2e suite has one admin account and no portfolio manager who is not a member; asserted by PlanninqRegisterSchemaTest::testPortfolioSchemaAndTheProjectReaderRule and ProjectHierarchyGuardListenerTest, live recipe in PR #714
- **GIVEN** a user who manages the portfolio "Ruimte" and is not a member of the project "Omgevingsvisie" in it
- **WHEN** the user opens the project list at /projects
- **THEN** "Omgevingsvisie" is listed under "Ruimte"
- **AND** its board at /projects/:id opens read-only for this user

#### Scenario: A client cannot write the derived readers list

@e2e exclude API-level guard with no screen, asserted by ProjectHierarchyGuardListenerTest (task 1.3)
- **GIVEN** a project member
- **WHEN** the member sends a PATCH to `/apps/openregister/api/objects/planninq/project/{id}` with `portfolioReaders` set to their own user id
- **THEN** the stored `portfolioReaders` equals the managers of the project's portfolio

### Requirement: A portfolio may use its own risk scale

A portfolio manager MUST be able to give a portfolio its own risk scale. The risk register of a project in that portfolio MUST use the portfolio's scale, and a project outside any portfolio MUST use the app-wide scale. Tier: V1 (tender 365739 requirement 64441; docs/FEATURES.md has no row).

#### Scenario: A portfolio with a three-level scale

- **GIVEN** the app-wide risk scale has 5 levels
- **AND** the portfolio "Dienstverlening" has its own scale of 3 levels
- **WHEN** a project member opens the Risks tab at /projects/:id/risks of a project in "Dienstverlening"
- **THEN** the heat map is three by three
- **AND** the Risks tab of a project in no portfolio shows five by five
