# Design: filter the board by person, label, priority and due date, and save the filter

## Context

Read at development `de35541`:

- `src/views/ProjectBoard.vue:69-95` renders label chips; `:249` `activeLabelId` in `data()`;
  `:320-321` `visibleTasks()` returns `filterTasksByLabel(this.tasks, this.activeLabelId)`; lane
  counts read from `visibleTasks`. `:335` `labelFilterChips()`.
- `src/utils/labelHelpers.js:154` `taskHasLabel`, `:174` `filterTasksByLabel` (keep with label, or
  all).
- Task fields: `assignedTo`, `sharedWith` (from `tasks-assignment-priority-labels`), `labels`,
  `priority`, `dueDate`.
- No saved-view storage exists. User settings go through `SettingsController::updateUser`
  (`lib/Controller/SettingsController.php:147`), which holds preferences, not shared objects.

## Goals / non-goals

Goals: four filter dimensions with include and exclude, a reload-safe and linkable filter, saved
filters private or shared. Non-goals: search, cross-project filters, reports.

## Decisions

### Decision 1: one filter model, one pure matcher

A filter is `{ assignee: {op, values}, label: {op, values}, priority: {op, values}, due: {op, values} }`
with `op` `is` or `isNot`. `matchesFilter(task, filter, currentUid)` in `src/utils/boardFilter.js`
is pure; "Me" resolves to the current uid; "Unassigned" matches an empty `assignedTo`. The label
chips become one dimension of the bar. `visibleTasks` applies it.

### Decision 2: the filter lives in the query string

`?assignee=me&priority!=low&label=<uuid>` style parameters are written with `$router.replace` on
every change and read on mount. A copied URL opens the same view. Alternative: local storage.
Rejected: it is per browser and cannot be shared.

### Decision 3: saved filters are Open Register objects

New schema `boardFilter`: `project`, `name`, `owner`, `shared` (boolean), `criteria` (object).
Authorization: read for the owner, and for project members when `shared`; create for project
members; update and delete for the owner and admins. A "Saved filters" menu lists them; "Save
filter" asks for a name and "Share with the project". Alternative: user preferences through
`SettingsController`. Rejected: preferences cannot be shared with a team.

### Decision 4: accessible bar

Each dimension is an `NcSelect` with `inputLabel` and a toggle button "is" or "is not" with
`aria-pressed`. The count line is announced through a polite live region.

## Risks / trade-offs

- [A saved filter referencing a deleted label] -> unknown values are dropped when the filter is
  applied and the menu marks the filter as changed.
