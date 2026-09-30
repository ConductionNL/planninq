# code-forge-links delta for integration-code-forge-links

## ADDED Requirements

### Requirement: A task shows the code linked to it

The task page MUST show a "Code" section that lists the task's linked commits, branches, merge requests and issues, newest first, each with its kind in text, its title as a link to the forge, its repository, its state in text and its date. Links SHALL be readable only by members of the task's project and by admins. Tier: V1 (docs/FEATURES.md, integration: GitHub/GitLab sync).

#### Scenario: A task lists its linked merge request

- **GIVEN** the task VC-12 with a linked merge request "Fix printer driver" in repository acme/portal, state merged
- **WHEN** a project member opens the task page
- **THEN** the Code section shows "Merge request", "Fix printer driver" linking to the forge, "acme/portal" and "Merged"

#### Scenario: A non-member cannot read the links

@e2e exclude API read rule; asserted by Newman "non-member cannot read a forge link" and PHPUnit PlanninqRegisterSchemaTest::testForgeLinkIsProjectScoped

- **GIVEN** a user who is not a member of the project of VC-12
- **WHEN** their client lists `forgeLink` objects through the OpenRegister API
- **THEN** no link of VC-12 is returned

### Requirement: A project member can add and remove a link by hand

A project member MUST be able to paste a URL onto a task as a code link; the system SHALL derive the kind, repository and number from GitHub, GitLab and Gitea URLs and store any other URL as a plain link. A project member SHALL be able to remove a link that was added by hand; a link written by the integration SHALL only be removable by an admin. Tier: V1.

#### Scenario: A member pastes a merge request link

- **GIVEN** a project member on the task page of VC-12
- **WHEN** they choose "Add link" and paste https://gitlab.example.org/acme/portal/-/merge_requests/42
- **THEN** the Code section lists a merge request "!42" in repository acme/portal, added by hand

#### Scenario: A member removes a manual link

- **GIVEN** the manual link above
- **WHEN** a project member removes it
- **THEN** it is gone from the Code section

#### Scenario: A member cannot remove an integration link

@e2e exclude The e2e run has one account, the admin; asserted by PHPUnit PlanninqRegisterSchemaTest::testForgeLinkIsProjectScoped (update and delete rules) and vitest forgeUrl.spec.js "a member removes only a link added by hand"

- **GIVEN** a link written by integriq on VC-12
- **WHEN** a project member who is not an admin opens its menu
- **THEN** it offers no "Remove"

### Requirement: Forge activity that names a task key is linked through integriq

When an admin has imported planninq's integriq configuration and connected a GitHub repository, a commit, branch or merge request whose message, name or title contains a task key MUST be linked to the task with that key, and a later event about the same merge request SHALL update its state instead of adding a second link. Planninq SHALL make no call to the forge; the task SHALL be resolved from the key by planninq, and an unknown key SHALL create no link. Tier: V1.

#### Scenario: A pushed commit naming the key appears on the task

@e2e exclude CI runs no integriq and no GitHub; asserted by PHPUnit IntegriqConfigurationTest::testAPushMapsItsHeadCommit and ForgeLinkResolveListenerTest::testKeyInForgeTextResolves

- **GIVEN** integriq connected to the GitHub repository acme/portal with planninq's configuration
- **WHEN** a developer pushes a commit "VC-12 handle paper jams"
- **THEN** the Code section of VC-12 lists that commit with its message and author

#### Scenario: A merged pull request shows merged

@e2e exclude CI runs no integriq and no GitHub; asserted by PHPUnit IntegriqConfigurationTest::testAMergedPullRequestMapsToAValidLink and ForgeLinkResolveListenerTest::testLaterIntegriqEventReplacesTheLink

- **GIVEN** a linked pull request of VC-12 in state open
- **WHEN** it is merged on GitHub and the event reaches integriq
- **THEN** the same link shows "Merged" and no second link is added

#### Scenario: An unknown key links nothing

@e2e exclude Server refusal; asserted by PHPUnit ForgeLinkResolveListenerTest::testUnknownKeyIsRejected and Newman "a link naming an unknown key is refused"

- **GIVEN** no task has the key ZZ-9
- **WHEN** a commit "ZZ-9 cleanup" is pushed
- **THEN** no `forgeLink` object is created

### Requirement: Admins find the forge setup in Beheer

The admin settings MUST have a "Code forges" section that explains how to import planninq's integriq configuration, how to add the webhook in the forge and how to name task keys, and SHALL offer the configuration file. Tier: V1.

#### Scenario: The admin finds the setup steps and the file

- **GIVEN** an admin on the planninq admin settings
- **WHEN** they open "Code forges"
- **THEN** they see the three setup steps and can download the integriq configuration
