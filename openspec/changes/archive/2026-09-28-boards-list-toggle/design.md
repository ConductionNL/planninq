# Design: a list view of the board's own cards

## Context

Read at development `4806ed3`:

- `src/views/ProjectBoard.vue` renders lanes from the project's column objects and cards from
  `tasksByColumn`, a grouping of `visibleTasks` (the tasks after the label filter) by `column`
  (`src/utils/columnHelpers.js` `groupTasksByColumn`). A task with no column is in the backlog and
  not on the board.
- The page already keeps no view state in the URL; the backlog keeps its sort and filters in the
  query string (`src/views/ProjectBacklog.vue` `setQuery`).

## Decisions

### Decision 1: the list is the board flattened

`boardListRows(tasks, columns)` walks `groupTasksByColumn` in lane order and returns one row per
card with its column. The list therefore shows exactly the cards the board shows, in the order the
board shows them, and follows the label filter because it reads `visibleTasks`. Alternative: a
separate query. Rejected: two sources can disagree.

### Decision 2: the view is in the query string

`?view=list` opens the list; no parameter is the board. A switch of two buttons with
`aria-pressed` sits in the page header. Alternative: a user preference. Rejected: a link should
open what the sender saw, and the backlog already keeps its view state in the URL.

### Decision 3: the list is read-only in this change

A row opens the task. Moving and ranking stay on the board, where they already have a keyboard
path (the card menu). Non-goal: inline editing, column sorting of the list.

## Risks

- [A long board makes a long list] -> the list has no paging, like the board; the board fetches
  every task already.
