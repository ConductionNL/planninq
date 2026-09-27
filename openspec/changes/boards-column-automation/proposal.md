---
kind: code
---

# Run an action when a card enters a board column

## Why

A project member cannot make a board do anything on its own. Moving a card only changes that card's position: the board's write is a PATCH of one field (`updateTaskStatus`, `src/store/projects.js:835-859`, called from `applyStatusMove` in `src/views/ProjectBoard.vue:577-595`), and no listener in `lib/Listener/` reacts to a column or status change. The column schema even promises more than the code does: its `type` property says "done columns auto-complete tasks" (`lib/Settings/planninq_register.json:892-900`), and nothing implements it. So a team that wants "whoever pulls a card into Review becomes its reviewer" or "cards in Blocked get high priority" has to remember to do it by hand, every time.

Kanboard binds actions to column moves per project (assign a specific user, close the task, and about fifty more). Jira runs automation rules on "issue transitioned", with actions such as assigning the issue or editing its fields.

Parity rows: `brd-column-automation` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because two competitors run actions when a card enters a column and boards are a core area of planninq.

This change extends the flat spec `openspec/specs/kanban-board.md` through a new capability, `column-automation`.

## What changes

- A project member can add rules to a board column that run when a card enters it: set the status, set the priority, assign a named project member, assign the person who moved the card, remove the assignee, or add a label.
- The rules run on the server whenever a task's column changes, from the board, the API or anywhere else, and they are part of the same save.
- The column header shows that a column has rules, and the moved card shows what the rules changed.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `brd-column-automation`.

### `brd-column-automation`: Have an action run automatically when a card enters a column, such as assigning it or closing it.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/store/projects.js:835 updateTaskStatus() only PATCHes `status`; no listener or automation runs on a status/column change anywhere in lib/Listener or src/."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/usability.md 'fifty event-to-action classes, bound per project' ; journeys.md 2 'A rule fires on a move' ; source read at v1.2.54: app/Template/action/index.php:5 'Add a new action'; app/Controller/ActionCreationController.php:102 save; app/Action/TaskAssignSpecificUser.php:36 on EVENT_MOVE_COLUMN, app/Action/TaskCloseColumn.php:35; app/Core/Action/ActionManager.php:126 attachEvents"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html, https://confluence.atlassian.com/automation/jira-automation-triggers-993924804.html): "corpus: jira-data-center/round4/M1-column.md 3.9 automation on 'issue transitioned' whose actions are 'editing issues, sending notifications, creating sub-tasks' and workflow post functions such as 'Assign to Current User' (3.8); a column maps to statuses (docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html) ; docs read 2026-09-26: https://confluence.atlassian.com/automation/jira-automation-triggers-993924804.html triggers include 'Issue transitioned', so a rule runs when a card moves to the column mapped to that status (read 2026-09-26)"


## Scope

### In scope

- An `automation` list on the `column` schema.
- A rules dialog per column on the board.
- A pre-save listener that applies the rules of the column a task enters.

### Out of scope

- Rules on leaving a column, on a timer, or on other events. OpenRegister flows (the Flows page, `src/manifest.json:222-228`) remain the tool for anything beyond "card enters column".
- Actions outside the task itself: sending mail, creating sub-tasks, calling webhooks.
- Conditions on a rule ("only when priority is high").
- The done column's own completion behaviour, which belongs to `boards-configurable-columns`.

## Impact

- Schema: `column.automation` (array of rules).
- Backend: a new `lib/Listener/ColumnAutomationListener.php` on OpenRegister's `ObjectCreatingEvent` and `ObjectUpdatingEvent`, registered in `lib/AppInfo/Application.php`.
- Views and dialogs: the column header in `src/views/ProjectBoard.vue`, a new `src/dialogs/ColumnRulesDialog.vue`.
- Depends on: `boards-configurable-columns` (lane A). Today the board's lanes are task statuses (`src/views/ProjectBoard.vue:283-292`), not the project's `column` objects, so there is no column to hang a rule on until that change lands.

## Risks

### Risk 1: a rule writes a value the schema would refuse
**Severity**: Medium
**Mitigation**: fields merged by a pre-save hook are not validated again, so the listener checks each value itself: status and priority against the schema's enums, an assignee against the project's members, a label against existing labels. An invalid rule is skipped and logged, and the rules dialog refuses to save one in the first place.

### Risk 2: two planninq pre-save listeners overwrite each other's changes
**Severity**: Low
**Mitigation**: the listener merges into whatever the event already carries instead of replacing it, and a unit test runs it after another listener that set a field.

### Risk 3: members are surprised by changes they did not make
**Severity**: Low
**Mitigation**: the column header shows the rule count, the card announces what the rules changed, and the task's audit trail records the change as part of the move.
