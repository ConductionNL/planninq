# Test Plan: Task Due Date Warning

## Test Cases

### TC-1: dueDateStatus returns null for task without due date
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to null
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `null`
- **test command**: Unit test (Jest/Vitest) — `npm run test`

### TC-2: dueDateStatus returns null for due date far in the future
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to 5 days from today
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `null`
- **test command**: Unit test — `npm run test`

### TC-3: dueDateStatus returns "approaching" for due date tomorrow
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to tomorrow
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `"approaching"`
- **test command**: Unit test — `npm run test`

### TC-4: dueDateStatus returns "approaching" for due date today
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to today
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `"approaching"`
- **test command**: Unit test — `npm run test`

### TC-5: dueDateStatus returns "approaching" for due date exactly 2 days away
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to exactly 2 days from today
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `"approaching"`
- **test command**: Unit test — `npm run test`

### TC-6: dueDateStatus returns "overdue" for past due date
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-status-helper`
- **type**: functional
- **preconditions**: A task object with `dueDate` set to yesterday
- **steps**: Call `dueDateStatus(task)`
- **expected result**: Returns `"overdue"`
- **test command**: Unit test — `npm run test`

### TC-7: Yellow badge on approaching task card
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-badge-on-task-card`
- **type**: functional
- **persona**: Sem (developer managing daily tasks)
- **preconditions**: A project with a task due tomorrow, visible on the kanban board
- **steps**: Open the project's kanban board view
- **expected result**: The task card displays a yellow badge with text "Due soon"
- **test command**: `/test-functional`

### TC-8: Red badge on overdue task card
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-badge-on-task-card`
- **type**: functional
- **persona**: Sem (developer managing daily tasks)
- **preconditions**: A project with a task due 3 days ago, visible on the kanban board
- **steps**: Open the project's kanban board view
- **expected result**: The task card displays a red badge with text "Overdue"
- **test command**: `/test-functional`

### TC-9: No badge on task with distant due date
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-badge-on-task-card`
- **type**: functional
- **preconditions**: A project with a task due 10 days from now, visible on the kanban board
- **steps**: Open the project's kanban board view
- **expected result**: The task card does NOT display a due date warning badge
- **test command**: `/test-functional`

### TC-10: No badge on task without due date
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#requirement-due-date-badge-on-task-card`
- **type**: functional
- **preconditions**: A project with a task that has no due date, visible on the kanban board
- **steps**: Open the project's kanban board view
- **expected result**: The task card does NOT display a due date warning badge
- **test command**: `/test-functional`

### TC-11: Badge accessibility — screen reader
- **spec_ref**: `openspec/changes/task-due-date-warning/specs/kanban-board/spec.md#non-functional-requirements`
- **type**: accessibility
- **preconditions**: A project with overdue and approaching tasks on the board
- **steps**: Navigate the kanban board with a screen reader
- **expected result**: Badge text ("Due soon" / "Overdue") is announced by the screen reader. Color is not the sole indicator of status.
- **test command**: `/test-accessibility`

### TC-12: Regression — existing card layout unaffected
- **spec_ref**: N/A (regression)
- **type**: regression
- **preconditions**: A project with tasks that have no due date or distant due dates
- **steps**: Open the kanban board, verify task cards display correctly (title, assignee, labels, priority)
- **expected result**: All existing card elements render correctly. No layout shifts or visual regressions.
- **test command**: `/test-regression`

## Coverage Summary

| Requirement | Test Cases | Covered |
|------------|-----------|---------|
| Due Date Status Helper | TC-1 through TC-6 | ✅ All 6 scenarios |
| Due Date Badge on Task Card | TC-7 through TC-10 | ✅ All 4 scenarios |
| Non-Functional: Accessibility | TC-11 | ✅ |
| Non-Functional: Performance | — | ✅ Implicitly (O(1) helper, no API calls) |
| Regression: Existing layout | TC-12 | ✅ |

## Out of Scope

- **Internationalization testing** (Dutch translations): Deferred until i18n strings are added. The unit tests verify English text; Dutch translation coverage will be part of the general i18n test pass.
- **Backlog and My Work views**: This change only covers the kanban board. Badge in other views is a separate change.
