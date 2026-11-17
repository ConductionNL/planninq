# Design: search tasks and change several tasks at once

## Context

Read at development `de35541`:

- `src/views/ProjectBoard.vue:320` `visibleTasks()` returns the fetched tasks narrowed by the
  label filter (`filterTasksByLabel`, `src/utils/labelHelpers.js:174`); lane counts read from it.
- `src/views/ProjectList.vue:32-37` searches projects through `useListView`'s `searchTerm`.
- `src/views/ProjectBacklog.vue:20-30` is a placeholder; `backlog-list` turns it into a list.
- `src/store/projects.js:871` `updateTask(taskId, patch)` PATCHes one task.
- Open Register's unified search provider already returns planninq tasks (matrix row
  `col-unified-search`, built).

## Goals / non-goals

Goals: in-project text search on the board and backlog; bulk status, assignee, priority and label
changes on the backlog. Non-goals: cross-project search, board multi-select, saved searches.

## Decisions

### Decision 1: client-side search over the loaded tasks

A board already loads every task of the project (`fetchTasks`), so a pure helper
`matchesSearch(task, term)` (case-insensitive, trimmed, on `title`, `description` and `key`) runs
inside `visibleTasks`. The field sits in the board header next to the label chips; Escape clears
it. Alternative: pass `_search` to Open Register. Rejected for the board: it adds a request per
keystroke for data already in memory. The backlog uses the same helper.

### Decision 2: bulk actions on the backlog list only

The backlog list gets a checkbox per row, "Select all" for the visible rows, and a bar with
Change status, Assign to, Priority and Labels (add or remove). Each selected task is PATCHed
through `updateTask`; results are collected and reported once. Alternative: multi-select on the
kanban board. Rejected for now: selection on a drag surface conflicts with drag and with the
card's click-to-open.

### Decision 3: labels in bulk add or remove, never replace

"Labels" offers add a label or remove a label, so a bulk action never wipes labels the user
did not see.

## Risks / trade-offs

- [Many PATCHes for a large selection] -> run them with a small concurrency limit (four) and
  show progress in the bar.
