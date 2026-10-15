# assignment-notification Specification

## Purpose
A person hears in the Nextcloud bell when a task is assigned to them, through rules declared on the task schema that OpenRegister delivers, and can switch that off for themselves. Built by change 2026-09-30-collaboration-notifications.

## Requirements

### Requirement: A person is notified when a task is assigned to them

The task schema MUST declare, in the `x-openregister-notifications` dialect, a rule that notifies the task's `assignedTo` user through a Nextcloud notification when a task is created with an assignee, and a rule that does the same when `assignedTo` changes to another user. The notification SHALL name the task and link to it. Planninq SHALL NOT dispatch these notifications from its own code. Tier: MVP (docs/FEATURES.md, notifications: task assigned).

#### Scenario: The assignee gets a notification with a link to the task

- **GIVEN** Anna and Ben, both members of a project, and the task "Export to CSV" assigned to nobody
- **WHEN** Anna assigns "Export to CSV" to Ben on its task page
- **THEN** Ben's Nextcloud notifications show "Task \"Export to CSV\" was assigned to you"
- **AND** opening it takes Ben to the task in planninq

#### Scenario: A task created for someone notifies them

- **GIVEN** Anna on a project board
- **WHEN** she creates the task "Plan demo" with Ben as assignee
- **THEN** Ben gets the same notification for "Plan demo"

#### Scenario: Clearing the assignee notifies nobody

- **GIVEN** the task "Export to CSV" assigned to Ben
- **WHEN** Anna removes the assignee
- **THEN** no assignment notification is sent

#### Scenario: The rule is declared, not dispatched

- **GIVEN** the planninq register descriptor `lib/Settings/planninq_register.json`
- **WHEN** the hydra gate `notification-dialect` runs on it
- **THEN** it finds the assignment rules in the canonical dialect
- **AND** planninq's `lib/` holds no call to Nextcloud's notification manager for task events

@e2e exclude a property of the register file and the source tree, asserted by tests/unit/Settings/PlanninqRegisterSchemaTest.php::testAssignmentRulesUseCanonicalDialect and the hydra gate-18 notification-dialect

### Requirement: A user can switch assignment notifications off

The planninq personal settings MUST offer the switch "Notify me when a task is assigned to me", on by default. Switching it off SHALL stop assignment notifications for that user only, through OpenRegister's per-user notification preference, and switching it on SHALL restore the default. Tier: MVP (docs/FEATURES.md, user settings: notify_assigned).

#### Scenario: Switching assignment notifications off stops them

- **GIVEN** Ben with "Notify me when a task is assigned to me" switched off
- **WHEN** Anna assigns a task to Ben
- **THEN** Ben gets no notification
- **AND** Carl, who kept the switch on, still gets one when a task is assigned to him
