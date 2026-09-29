# task-breakdown delta for tasks-subtasks-checklist

Extends the flat main spec `openspec/specs/tasks.md` (sub-tasks, delete guard).

## ADDED Requirements

### Requirement: A project member can add subtasks one level deep

The system MUST let a project member add subtasks to a task from its task page. A subtask MUST be
a task in the same project with `parent` set, and a subtask MUST NOT get subtasks of its own.
Tier: V1 (docs/FEATURES.md).

#### Scenario: Add a subtask

- **GIVEN** a project member is on the task page of "Prepare the council decision"
- **WHEN** they type "Collect the advice" in the Subtasks add field and press Enter
- **THEN** a task "Collect the advice" exists in the same project with `parent` pointing at the first task
- **AND** the Subtasks section shows "0 of 1 done"

#### Scenario: A subtask has no subtasks section

- **GIVEN** a project member opens the task page of a subtask
- **WHEN** the page loads
- **THEN** no Subtasks section is shown

### Requirement: A project member can keep a checklist inside a task

The system MUST let a project member add, tick, reorder and remove checklist items on a task,
and MUST show the done count on the board card. Tier: V1.

#### Scenario: Tick a checklist item

- **GIVEN** a task with a checklist of five items, two ticked
- **WHEN** a project member ticks a third item on the task page
- **THEN** the card on the board shows "3/5"

### Requirement: A parent task shows its subtasks' estimates and logged time

The system MUST show on a parent task the sum of its subtasks' estimates and logged time next to
its own figures. Tier: V1.

#### Scenario: Rollup on the parent

- **GIVEN** a parent with its own estimate of 1 hour and two subtasks estimated at 2 and 3 hours, with 90 minutes logged on one of them
- **WHEN** a project member opens the parent's task page
- **THEN** the time section shows "Subtasks: 5h estimated, 1h 30m logged" and a total estimate of 6h

### Requirement: A project member can duplicate a task with its subtasks

The system MUST let a project member duplicate a task. The copy MUST carry the description,
priority, labels and checklist with every item unticked, MUST copy the subtasks under the new
task, and MUST NOT copy dates, assignees, time, comments or attachments. Tier: V1.

#### Scenario: Duplicate a task tree

- **GIVEN** a task with two subtasks and a checklist with one ticked item
- **WHEN** a project member chooses "Duplicate" on its task page
- **THEN** a task "Copy of {title}" with status open appears with two copied subtasks
- **AND** its checklist has the same items, none ticked

### Requirement: Deleting a parent asks what happens to its subtasks

The system MUST NOT silently orphan subtasks. When a parent is deleted, the confirmation MUST
offer to delete the subtasks too or to keep them as separate tasks. Tier: V1.

#### Scenario: Keep the subtasks

- **GIVEN** the reporter deletes a parent task with two subtasks and no logged time
- **WHEN** they choose "Keep subtasks as separate tasks"
- **THEN** the parent is deleted and both subtasks remain with no parent
