# risk-register Specification

## Purpose
A project keeps a register of its risks, each scored by the server on the admin's likelihood and impact scale, shown per project as a list and a heat map and across projects under Projects.

## Requirements

### Requirement: A project member keeps a scored risk register

Every project MUST have a risk register. Each risk MUST carry a likelihood and an impact on the admin's risk scale, a score that the server calculates from them, a status, an owner, a response and its countermeasures. The Risks tab MUST show the risks as a list sorted by score and as a heat map of likelihood against impact. Tier: V1 (docs/FEATURES.md has no row; tender 365739 requirements 4003 and 4004).

#### Scenario: Adding a risk

- **GIVEN** a project member on the Risks tab at /projects/:id/risks
- **WHEN** the member presses "Add risk", enters the title "Supplier delivers late", sets likelihood 4 and impact 3, picks the response "Reduce", writes a countermeasure and saves
- **THEN** the risk appears in the list with the score 12
- **AND** the heat map cell for likelihood 4 and impact 3 shows the count 1 and the band name as text

#### Scenario: A client cannot write its own score

@e2e exclude Server-side calculation with no screen, asserted by a PHPUnit schema test and a live POST in task 3.1
- **GIVEN** a project member
- **WHEN** the member sends `POST /apps/openregister/api/objects/planninq/risk` with likelihood 2, impact 2 and score 25
- **THEN** the stored risk has the score 4

### Requirement: Projecten lists the risks of every project you can see

The Projecten menu MUST have a Risks sub-page that lists the risks of every project the user can read, highest score first, with the project, owner, status and review date. A user MUST NOT see risks of projects they cannot read. Tier: V1 (docs/FEATURES.md has no row; ADR-001 maps `risk-register-issue-tracking` to Projecten).

#### Scenario: A project leader scans risks across projects

- **GIVEN** a user on two projects with three open risks between them, and a third project the user is not on
- **WHEN** the user opens Risks under Projecten at /projects/risks
- **THEN** the page lists the three risks, highest score first, each with its project
- **AND** no risk of the third project is listed

### Requirement: An admin sets the risk scale

An admin MUST be able to set the number of likelihood and impact levels (3 to 5), a label per level, and the two score thresholds between low, medium and high. The heat map MUST colour its cells by band with NL Design tokens and MUST also show each band as text. Tier: V1 (docs/FEATURES.md has no row; tender 365739 requirement 64441).

#### Scenario: Switching to a three-level scale

- **GIVEN** an admin on the planninq admin settings page, and no risk uses a level above 3
- **WHEN** the admin sets the risk scale to 3 levels with the labels "Low", "Medium" and "High" and saves
- **THEN** the Risks tab of every project shows a three by three heat map with those labels

#### Scenario: A smaller scale is refused while risks use a higher level

- **GIVEN** an admin, and two risks with likelihood 5
- **WHEN** the admin tries to save a scale of 4 levels
- **THEN** the save is refused with "2 risks use level 5. Change them first."
