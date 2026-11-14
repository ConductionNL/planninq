# project-public-share delta for projects-public-share

## ADDED Requirements

### Requirement: A project manager shares a read-only board by link

A project owner or manager MUST be able to create a link to a read-only view of the project's board. The link MUST have an end date of at most one year, MAY have a password, and MUST be revocable at any time. Anyone with the link MUST see the columns and cards without signing in, and MUST NOT be able to change anything. Tier: V1 (docs/FEATURES.md has no row; it extends "Shared project access (multi-user)").

#### Scenario: Creating a link and opening it without an account

- **GIVEN** a project manager on the Share tab of the project settings sidebar at /projects/:id
- **WHEN** the manager presses "Create link", keeps the end date 30 days ahead, sets no password and confirms
- **THEN** the tab shows the new link with a copy button and its end date
- **AND** opening that link in a browser with no Nextcloud session shows the board's columns and cards with their titles, due dates and labels
- **AND** the page offers no way to edit, move or add a card

#### Scenario: A password-protected link

- **GIVEN** a link created with a password
- **WHEN** a visitor without an account opens it
- **THEN** the page asks for the password before showing anything
- **AND** a wrong password shows "That password is not right" and no board

#### Scenario: A revoked link stops working

- **GIVEN** a live link that a visitor opened earlier
- **WHEN** a project manager presses "Switch off" for that link on the Share tab
- **AND** the visitor reloads the link
- **THEN** the page says "This link does not work any more"

### Requirement: A shared link never reveals people or descriptions

Nothing served through a project link MUST contain the names or ids of assignees, reporters, watchers or contractors, or task descriptions. The rule MUST be enforced where the data is served, not only where it is rendered. Tier: V1 (docs/FEATURES.md, "GDPR-compliant (no external data transfer)").

#### Scenario: The raw link answer carries no people

@e2e exclude Checks the raw JSON of the OpenRegister endpoint, asserted by the Newman requests of task 1.2
- **GIVEN** a shared project whose tasks have assignees, watchers and descriptions
- **WHEN** a client without a session sends `GET /apps/openregister/api/public/links/{anchor}`
- **THEN** no object in the answer contains `assignedTo`, `reporter`, `watchers`, `contractorRef` or `description`

### Requirement: Managers see every live link on their project

The Share tab MUST list every live link on the project, whoever created it, with its end date, whether it has a password, its creator and how often it was used. Tier: V1 (docs/FEATURES.md, "Audit trail on all task changes (CnObjectSidebar)").

#### Scenario: A link made by a member shows up for the manager

@e2e exclude The member mints through the API, which has no screen; the listing half is covered by e2e "creating a link and opening it without an account"
- **GIVEN** a project member who minted a link on the project's view through `POST /apps/openregister/api/access-links`
- **WHEN** a project manager opens the Share tab
- **THEN** the link is listed with that member as its creator
- **AND** the manager can switch it off
