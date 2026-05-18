# Tasks Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [task-comments](../../) — adds comment count display to task cards and list rows

## Purpose

Extends the task display to surface a comment count badge on task cards (kanban board) and task list rows, giving teams a quick signal that a task has inline discussion without opening it. No changes to the task data model — `commentCount` is a derived display value from `ICommentsManager`.

## ADDED Requirements

### Requirement: REQ-TC-005-TASK — Comment Count Badge on Task Card and List [MVP]

Task cards on the kanban board and task rows in the backlog/list view MUST display the number of comments when the count is greater than zero.

The comment count MUST be sourced from a batch API call (`GET /apps/planix/api/tasks/comment-counts?ids[]=...`) that returns a map of task ID → comment count. This avoids N+1 per-task queries when rendering the board.

The badge MUST be omitted entirely (not rendered as zero) when a task has no comments.

`commentCount` is NOT a stored field on the task object in OpenRegister. It MUST NOT be written to the task schema.

#### Scenario: Task card shows comment count badge when comments exist

- **GIVEN** a task has 3 comments
- **WHEN** the kanban board renders the task card
- **THEN** the card MUST display a comment count badge with value `3`

#### Scenario: Task card shows no badge when there are no comments

- **GIVEN** a task has 0 comments
- **WHEN** the kanban board renders the task card
- **THEN** the card MUST NOT display a comment count badge

#### Scenario: Task list row shows comment count when comments exist

- **GIVEN** a task has 2 comments and the backlog list view is open
- **WHEN** the task list row is rendered
- **THEN** the row MUST display a comment count indicator showing `2`

#### Scenario: Batch comment counts fetched for visible tasks

- **GIVEN** the kanban board is loaded with 15 visible tasks
- **WHEN** the board finishes rendering
- **THEN** the system MUST issue a single batch request for comment counts covering all visible task IDs
- **AND** MUST NOT issue one request per task
