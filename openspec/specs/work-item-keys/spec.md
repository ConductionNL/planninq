# work-item-keys Specification

## Purpose
A project has a short unique key, and every task in it gets a readable key made of that key and a number, such as VERG-42. Built by `tasks-readable-keys` (archived 2026-09-29).

## Requirements

### Requirement: A project has a short unique key chosen at creation

The system MUST ask for a project key in the New project dialog, suggest one from the title, and
refuse a key that another project already uses. A key MUST be two to ten characters, letters A-Z
and digits, starting with a letter. Tier: MVP (docs/FEATURES.md).

#### Scenario: Create a project with a key

- **GIVEN** a user allowed to create projects opens "New project" on the Projects page
- **WHEN** they enter the title "Vergunningen Centrum", change the suggested key to "VERG" and create
- **THEN** the project stores `key` "VERG"

#### Scenario: A used key is refused

- **GIVEN** a project with key "VERG" exists that the user cannot see
- **WHEN** the user tries to create another project with key "VERG"
- **THEN** the dialog says "This key is already used by another project." and nothing is created
- **AND** the message does not name the other project

### Requirement: Every new task gets a readable key

The system MUST give every task created in a project with a key the next number in that project,
as `{projectKey}-{n}`, and MUST keep a key the task already carries. Two tasks in one project MUST
NOT get the same key. Tier: V1.

#### Scenario: Numbered on create

- **GIVEN** project "VERG" whose last task key is VERG-41
- **WHEN** a member creates a task
- **THEN** the task's key is VERG-42 and its card shows "VERG-42"

#### Scenario: Two members create at the same moment

- **GIVEN** two members create a task in project "VERG" at the same time
- **WHEN** both creates finish
- **THEN** the two tasks carry different keys

@e2e exclude two creates at the same instant cannot be timed from one browser; covered by tests/unit/Listener/WorkItemKeyListenerTest.php::testTwoCreatesOnOneCounterGetDifferentKeys (a held lock, then the next number)

#### Scenario: An imported key is kept

- **GIVEN** an import creates a task with key "PLX-7" in project "VERG"
- **WHEN** the create finishes
- **THEN** the task's key is still "PLX-7"

### Requirement: Existing tasks are numbered when a project first gets a key

The system MUST number the keyless tasks of a project, oldest first, once the project's owner
sets a key for the first time. Tier: V1.

#### Scenario: Number an existing project

- **GIVEN** a project without a key that holds three tasks
- **WHEN** its owner sets the key "HAND" in the settings sidebar
- **THEN** the three tasks get HAND-1, HAND-2 and HAND-3 in creation order

@e2e exclude the numbering runs in a queued background job that CI e2e does not drive; covered by tests/unit/Listener/WorkItemKeyListenerTest.php::testTheFirstKeyQueuesTheNumberingOfExistingTasks and ::testTheJobNumbersTheKeylessTasksInOrder
