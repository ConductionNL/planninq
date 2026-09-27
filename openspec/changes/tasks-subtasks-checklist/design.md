# Design: break a task into subtasks and a checklist

## Context

Read at development `de35541`:

- Schema `task`: `parent` (uuid, `$ref` task, "sub-task support"), `epic` (uuid), and
  `estimatedDuration` (minutes). No checklist property.
- `src/utils/taskHelpers.js:34` hides the library's `tasks` sidebar tab.
- `src/views/TaskDetail.vue:54-121` holds the time section: estimate input, a progress line that
  compares this task's logged minutes with its own estimate (`:76-88`), and the entry list.
- `src/store/projects.js:775` `fetchTasks(projectId)` reads all of a project's tasks through the
  object store; filters are passed through to OpenRegister, so `{ project, parent }` works the same.
- `src/store/timeEntries.js` loads the entries of one task.
- `docs/ARCHITECTURE.md` section 5 question 2: sub-task depth is one level, all tiers.

## Goals / non-goals

Goals: subtasks one level deep, a checklist, rollups on the parent, duplicate with children, a
safe parent delete. Non-goals: epics, nesting, copying collaboration data.

## Decisions

### Decision 1: subtasks are ordinary tasks with `parent`

A subtask is a task in the same project with `parent` set. It keeps its own status, assignee and
dates and shows on the board like any card, with a small chip naming the parent. The TaskDetail
Subtasks section lists children (`{ project, parent: id }`), shows "2 of 5 done", and has an add
field that calls `createTask` with `parent`. A task that already has a parent gets no Subtasks
section (one level).

### Decision 2: the checklist is an array on the task

New optional property `checklist`: an array of `{ id, text, done }`. Items are added, ticked,
reordered and removed inline, and each change PATCHes the whole array. The card shows "3/5" when
a checklist exists. Alternative: model each item as a subtask. Rejected: a checklist item has no
assignee, date or status of its own, and making each one a card floods the board.

### Decision 3: rollups are computed on the client

The parent's time section adds a line "Subtasks: {estimate} estimated, {logged} logged" and a
total. The subtask estimates come from the children already loaded; their logged time from one
time-entry query per child. Alternative: store a rolled-up field on the parent. Rejected: it goes
stale on every child edit.

### Decision 4: duplicate copies the task, its checklist and its children

"Duplicate" creates a copy titled "Copy of {title}" with status `open`, the same description,
priority, labels and checklist (all items unticked), then copies each subtask the same way under
the new parent. Dates, assignees, time, comments and attachments are not copied.

### Decision 5: deleting a parent asks what happens to its children

`TaskDeleteDialog.vue` (from `tasks-create-edit-delete`) gains a branch: "This task has
subtasks." with "Delete subtasks too" and "Keep subtasks as separate tasks" (clears `parent`).
The logged-time refusal applies to every task it would delete.

## Risks / trade-offs

- [One time-entry query per child] -> fine for the handful of subtasks one level allows; the
  query can move to a single `task IN (...)` filter if OpenRegister supports it.
