# email-notifications Specification

## Purpose
A person who asks for it also gets assignment and due-date notifications by email, through email rules declared on the task schema whose recipient resolvers honour the email switch, the matching in-app switch and the account's address. Built by change 2026-09-30-collaboration-notifications.

## Requirements

### Requirement: A user can get planninq notifications by email

The planninq personal settings MUST offer the switch "Also send these to me by email", off by default. When a user switches it on, the system SHALL send them an email for each task assigned to them and for each due-date reminder, in addition to the Nextcloud notification, through rules declared on the task schema with the `email` channel. A user who has not switched it on SHALL NOT receive planninq email. Tier: V1 (docs/FEATURES.md, notifications; this change adds the row).

#### Scenario: An opted-in assignee gets an assignment mail

- **GIVEN** Ben with an email address and "Also send these to me by email" switched on
- **WHEN** Anna assigns the task "Export to CSV" to Ben
- **THEN** Ben receives an email naming "Export to CSV" with a link to the task
- **AND** Ben also gets the Nextcloud notification

@e2e exclude needs a mail catcher the CI e2e run does not have (task 2.4 stays open until one exists); the rule shape is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testEmailRulesMirrorInAppRules and who receives the mail by tests/unit/Notification/EmailOptInRecipientResolverTest.php::testOptedInAssigneeIsReturned

#### Scenario: Without opting in no mail is sent

- **GIVEN** Carl with an email address who never switched email on
- **WHEN** a task is assigned to Carl and later comes within a day of its due date
- **THEN** Carl receives no email from planninq

@e2e exclude needs a mail catcher the CI e2e run does not have (task 2.4 stays open until one exists); the rule shape is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testEmailRulesMirrorInAppRules and who receives the mail by tests/unit/Notification/EmailOptInRecipientResolverTest.php::testOptedOutAssigneeIsNotReturned

#### Scenario: A due-date reminder arrives by mail

- **GIVEN** Ben with email switched on and the task "Export to CSV" assigned to him, due tomorrow and not done
- **WHEN** OpenRegister's hourly due-soon run passes
- **THEN** Ben receives an email that "Export to CSV" is due soon

@e2e exclude needs a mail catcher the CI e2e run does not have (task 2.4 stays open until one exists); the rule shape is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testEmailRulesMirrorInAppRules and who receives the mail by tests/unit/Notification/EmailOptInRecipientResolverTest.php::testOptedInAssigneeIsReturned (DueSoonEmailRecipientResolver)

### Requirement: The email follows the user's in-app choices

The system MUST NOT email a user about assignments when their assignment notification switch is off, and MUST NOT email them due-date reminders when their due-date reminder switch is off, even with email switched on. Tier: V1.

#### Scenario: The in-app switch silences the matching mail

- **GIVEN** Ben with email switched on and "Notify me when a task is assigned to me" switched off
- **WHEN** Anna assigns a task to Ben
- **THEN** Ben receives neither a notification nor an email for it

@e2e exclude needs a mail catcher the CI e2e run does not have (task 2.4 stays open until one exists); the rule shape is asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testEmailRulesMirrorInAppRules and who receives the mail by tests/unit/Notification/EmailOptInRecipientResolverTest.php::testInAppSwitchOffSilencesMatchingMail, and the in-app half by tests/e2e/notifications.spec.ts

### Requirement: The email switch explains a missing address

When the signed-in user's Nextcloud account has no email address, the email switch MUST be disabled and SHALL show "Add an email address in your Nextcloud personal settings to get mail.". Tier: V1.

#### Scenario: The email switch is disabled without an email address

- **GIVEN** a user whose Nextcloud account has no email address
- **WHEN** they open the planninq personal settings
- **THEN** "Also send these to me by email" is disabled
- **AND** the hint "Add an email address in your Nextcloud personal settings to get mail." is shown
