# Design: turn the backlog placeholder into a working backlog

## Context

Read at development `de35541`:

- `src/views/ProjectBacklog.vue:1-30` renders a breadcrumb and an `NcEmptyContent` placeholder; the
  script hydrates the project on a deep link.
- `src/manifest.json:146` page `ProjectBacklog`, route `/projects/:id/backlog`, component
  `ProjectBacklog`, note "an ordered task list scoped to :id".
- `openspec/specs/projects.md` requirement "Project Backlog Route" with the scenario "Placeholder
  until task management is implemented" and a note to retire it when task management lands.
- Schema `task`: `column` ("null = backlog"), `columnOrder` (order within the column), `priority`,
  `dueDate`; Open Register adds a created timestamp in the object's `@self` metadata.
- `src/views/ProjectBoard.vue:43-48` opens the backlog; the card action menu is at `:152-165`.
- `src/store/projects.js:775` `fetchTasks(projectId)` passes filters to Open Register.

## Goals / non-goals

Goals: a real backlog with create, rank, sort, filter and move to and from the board.
Non-goals: bulk actions, sprints, statistics.

## Decisions

### Decision 1: the backlog is the set of tasks with no column

The page loads `fetchTasks(projectId)` and keeps tasks whose `column` is empty and whose status is
not `done`; cancelled tasks show only under the "Cancelled" filter. Alternative: a `backlog`
boolean. Rejected: the schema already defines the backlog as no column, and a second flag could
disagree with it.

### Decision 2: rank reuses `columnOrder`

For a task without a column, `columnOrder` is its backlog rank. Rows drag with a handle and move
with "Move up" and "Move down" in the row menu; writes use the sparse steps of the board lanes. A
new task goes to the bottom.

### Decision 3: sort is a view, rank is data

Sorting by priority, due date or creation date changes only the display and disables dragging with
a hint "Sort by rank to reorder". The sort choice is in the query string, like the filter.

### Decision 4: moving between backlog and board

"Move to board" opens a small menu of the project's columns; choosing one PATCHes `column`,
`columnOrder` (bottom of that lane) and the column's mapped status. On the board, the card menu gains
"Move to backlog", which clears `column` and sets `columnOrder` to the bottom of the backlog.

### Decision 5: create into the backlog

"New task" on the backlog opens `TaskFormDialog` without a column. The main spec's "Create a task"
scenario (placed in the backlog by default) is met here.

Amended at build (28 Sep): `TaskFormDialog` does not exist at HEAD (it belongs to
`tasks-create-edit-delete`, not built yet). The backlog has an inline "New task" field and an Add
button that create the task with status open, priority normal, no column and the bottom rank.

### Decision 6: filters without the board's filter bar

Amended at build (28 Sep): `boards-filters` is not built yet, so the backlog carries its own two
filters: a priority choice and a "Cancelled" switch, both in the query string with the sort. When
`boards-filters` lands, its bar replaces the priority choice here.

### Decision 7: a card taken off the board is open again

"Move to backlog" clears `column`, puts the card last in rank and sets status `open`, so a card
taken off a done lane shows in the backlog (done tasks are hidden there) and the server clears
its `completedAt`.

## Risks / trade-offs

- [Done tasks without a column stay hidden] -> they are finished work; the Cancelled filter and the
  board's done column cover the rest.
