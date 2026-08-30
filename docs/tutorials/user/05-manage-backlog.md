---
sidebar_position: 5
title: Manage the backlog
description: Capture work in the backlog without a column, prioritise the list, and pull items onto the board when the team has capacity.
---

# Manage the backlog

The backlog is where work goes that isn't on the board yet — captured ideas, future tickets, anything not actively being worked on. A task in Planninq lives in the backlog when it has no column assignment. The board is the pull view; the backlog is the *yet-to-pull* view. Bringing a task from backlog to board is the same drag-and-drop action used to move cards across columns.

## Goal

By the end you will have added a task to the backlog, reordered the backlog so the top of the list is the next thing to pull, and dragged the top item onto the kanban board.

## Prerequisites

- A project in Planninq you are a member of (see [Create your first project](02-create-project.md)).
- At least one column on the project's board (the default four are fine).

## Steps

1. From the project board, click **View Backlog** in the header. The backlog list opens — every task in this project that has no column assignment, ordered by **backlog position** (a Planninq-managed integer index).

   ![Project backlog view](/screenshots/tutorials/user/05-manage-backlog-01.png)

2. Add a task directly to the backlog. Click **+ Add to backlog**. The same task form opens as on the board, except no column is preselected. Fill in title, priority, optional assignee / due date / labels. Save.

   ![Adding a task to the backlog](/screenshots/tutorials/user/05-manage-backlog-02.png)

3. Reorder the backlog. Drag the task you just added to the top — Planninq updates its backlog-position. The convention: the top of the list is the next thing to pull onto the board. Treat the backlog as an ordered intake, not a bag.

   ![Backlog after reorder](/screenshots/tutorials/user/05-manage-backlog-03.png)

4. Pull a task onto the board. Open the task detail and set its **Column** field (or, more commonly, drag the task from the backlog into a column on the board). The task moves: it disappears from the backlog list, appears on the chosen column, the column's task-count ticks up.

   ![Task pulled from backlog to To Do](/screenshots/tutorials/user/05-manage-backlog-04.png)

5. To send a task back to the backlog (you over-committed; the work isn't ready), clear its **Column** field on the task detail. The card disappears from the board, lands at the bottom of the backlog (reorder it to put it back in priority order).

   ![Task sent back to backlog](/screenshots/tutorials/user/05-manage-backlog-05.png)

## Verification

The backlog list shows tasks without a column, in the order you arranged them. The board count for a column matches the number of cards that column holds. Moving a task between backlog and board is reversible — you can shuffle work freely as the team's capacity changes.

## Common issues

| Symptom | Fix |
|---|---|
| Backlog is empty when you expect tasks | The tasks all have column assignments — they're on the board. Use the kanban board view instead. |
| Backlog reorder doesn't stick | Drag was on the wrong handle; drag from the task title row, not from a label/avatar chip. |
| Card pulled onto the board doesn't appear | A board filter is active; clear filters and the card shows. |
| Two members reorder simultaneously and the order is unexpected | Last-write wins on the backlog-position field; refresh and reorder, no merge. |
| Screenshots may be missing | App not yet installed in the test environment; rerun `npm run test:e2e:docs` once it is. |

## Reference

- [Work with the kanban board](03-work-with-boards.md) — what happens on the receiving end.
- [Add and manage tasks](04-manage-tasks.md) — task fields and the lifecycle.
- [Your dashboard and My Work](07-my-work-and-dashboard.md) — assigned tasks across all projects regardless of board/backlog.
- [Kanban board reference](../../features/kanban-board.md) — column / backlog model.
