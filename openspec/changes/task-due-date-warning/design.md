# Design: Task Due Date Warning

**Status**: pr-created

## Summary

Add a visual warning indicator to tasks that are approaching or past their due date. Tasks due within 2 days get a yellow warning badge, and overdue tasks get a red badge on the kanban board and task list.

## Motivation

Users currently have no visual cue that a task is overdue or about to become overdue. This makes it easy to miss deadlines. A simple color-coded badge on the task card solves this without adding complexity.

## Approach

- Add a computed property `dueDateStatus` to the task model that returns `null`, `"approaching"`, or `"overdue"` based on the current date and `dueDate`.
- Render a small badge on TaskCard.vue showing the status:
  - No badge if no due date or due date is > 2 days away
  - Yellow badge with "Due soon" if due date is within 2 days
  - Red badge with "Overdue" if due date is in the past
- Use existing NcChip or badge component from @nextcloud/vue.

## Scope

- Only the frontend task card display is affected.
- No API changes needed — `dueDate` is already available on the task object.
- No new dependencies.
