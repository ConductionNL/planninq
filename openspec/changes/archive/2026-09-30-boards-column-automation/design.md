# Design: run an action when a card enters a board column

## Context

What exists at de35541:

- `column` (`lib/Settings/planninq_register.json:754-902`): `title`, `project`, `order`, `wipLimit`, `color`, `type` (`active` or `done`, described as "done columns auto-complete tasks" at `:892-900`, which no code does). Members of the column's project may read, create, update and delete columns (authorization block from `:767`).
- `task.column` (`:241-248`) is a nullable `$ref: column`; null means the backlog.
- The board today groups cards by `status`, not by `column` objects (`src/views/ProjectBoard.vue:283-305`), and a move writes only `status` (`applyStatusMove` at `:577-595` calling `updateTaskStatus`, `src/store/projects.js:835-859`). The lane A change `boards-configurable-columns` moves the board onto the project's `column` objects and writes `task.column` on a move.
- `TaskActivityListener` and `TaskDependencyCleanupListener` show how planninq subscribes to OpenRegister object events (`lib/AppInfo/Application.php:536-563`) and scopes to its own task schema (`TaskScopeResolver::isPlanninqTask`, used at `lib/Listener/TaskActivityListener.php:131-136`).
- OpenRegister (read at 63ddfd5) dispatches `ObjectUpdatingEvent` before it writes an update and `ObjectCreatingEvent` before a create. A listener may stop propagation to reject the write, or call `setModifiedData()`; the mapper then merges that data into the object before it stores it (`lib/Db/MagicMapper.php:7302-7319` in OpenRegister). The merge happens after schema validation.

What is missing: somewhere to store a rule, a screen to write one, and code that runs it.

## Goals / non-goals

Goals:
- Simple "when a card enters this column" rules that a project owner can set up on their own board, and that every member's moves trigger.
- Rules that hold for every client, applied in the same save as the move.

Non-goals:
- A general automation engine; conditions; actions outside the task; triggers other than entering a column.

## Decisions

### Decision 1: rules are stored on the column
`column.automation` is an array of `{ action, value }` objects. Actions: `setPriority` (value: a priority), `assign` (value: a user id), `assignMover` (no value), `unassign` (no value), `addLabel` (value: a label id). Stored on the column because a rule belongs to one column of one board and is read at exactly the moment a task enters that column. A separate `automationRule` schema would add a register entry and a second read per move for no gain.

### Decision 2: no status action, and only the owner edits rules
`boards-configurable-columns` gives every column an optional `status` that a move writes, and narrows column create, update and delete to the project owner and admins. Column rules therefore carry no status action (the column already decides the status, and two sources for one field would fight), and editing rules is an owner action like renaming a column: the rules dialog sits in the owner's column menu, and the column schema's owner-only update rule is what OpenRegister enforces. Every member's move still triggers the rules.

### Decision 3: rules run in a pre-save listener, in the same write
`ColumnAutomationListener` listens to `ObjectUpdatingEvent` and `ObjectCreatingEvent`. For a planninq task whose `column` differs from the old value (or is set on create), it reads the target column through `ObjectService` as a system read, turns its rules into field values, and merges them into the event with `setModifiedData()`, keeping anything another listener already put there. The move and its consequences are one stored version with one audit-trail entry. Rule order is list order; a later rule wins on the same field. `assignMover` uses the current user from `IUserSession`; with no user (a background job) it is skipped.

Alternatives considered: running rules in the Vue board after the drop (any other client, such as the API, a flow or an import, would skip them); a post-save listener that writes a second time (two versions per move, and the listener must guard against re-triggering itself); OpenRegister flows (authored in Beheer by admins, run after the save, and heavier than a per-column switch a project member sets up).

### Decision 4: the listener validates what it merges
Because merged data is not validated again, the listener checks every value: `setPriority` against the task schema's priority enum (`lib/Settings/planninq_register.json:199-211`), `assign` against the project's `members`, `addLabel` against existing labels. A failing rule is skipped and logged with the column and rule index; the move itself still succeeds. `addLabel` appends only when the label is not already on the task.

### Decision 5: the board shows rules and their effect
The column header menu (owner only) gets a "Rules" entry that opens `ColumnRulesDialog` (in `src/dialogs/`): a list of "When a card enters this column" rules with an action select, a value select that only offers valid values (members of this project, existing labels, enum values), and remove buttons. A column with rules shows an icon with the accessible label "2 rules run when a card enters this column". After a move, the board replaces the card with the object OpenRegister returns, so the card shows the new assignee or priority at once, and a live region says what changed ("Rules applied: assigned to Anna, priority high").

## Risks / trade-offs

- [Merged data skips validation] -> The listener validates each value (Decision 4).
- [Two pre-save listeners, this one and the `completedAt` stamp of `boards-configurable-columns`] -> Merge, never replace; covered by a unit test.
- [A member leaves the project but stays in a rule] -> The rule is skipped at run time and the dialog marks it "No longer a project member".
