## 1. week grouping helper

- [ ] 1.1 Add `groupTasksByDay(tasks, weekStart)` to `src/utils/taskHelpers.js`, next to `groupTasksByStatus` and `BOARD_STATUSES`, returning one bucket per day of the week plus an `unscheduled` bucket for tasks without a `dueDate`
- [ ] 1.2 Build the seven day keys with `currentWeekRange()` from `src/utils/timesheetHelpers.js` and day arithmetic with `MS_PER_DAY`/`parseDay()` from `src/utils/timelineHelpers.js`; no new week calculation
- [ ] 1.3 Keep the helper pure: no store access, no `Date.now()` inside, the week start passed in as an argument
- [ ] 1.4 Add `tests/vitest/weekGrouping.spec.js` covering a task on each day, a task outside the week, a task without a due date, and an empty week

## 2. view mode and URL persistence

- [ ] 2.1 Add a `viewMode` state to `src/views/ProjectBoard.vue` with the values `kanban`, `list` and `week`, initialised from the URL query
- [ ] 2.2 Add the week option to the existing view toggle in `src/views/ProjectBoard.vue`
- [ ] 2.3 Write the selected mode to the URL query on change and read it back on mount, so a reload returns to the week view
- [ ] 2.4 Keep the existing label and assignee filters applied when the mode changes

## 3. week render

- [ ] 3.1 Render seven day columns, Monday through Sunday, in `src/views/ProjectBoard.vue` when `viewMode` is `week`, using the existing card markup
- [ ] 3.2 Label each column with its weekday name and date
- [ ] 3.3 Render an empty state in a day column that has no tasks
- [ ] 3.4 Render the `unscheduled` bucket as an "Unscheduled" area beside the seven columns
- [ ] 3.5 Leave the kanban and list renders unchanged

## 4. current-user highlight

- [ ] 4.1 Read the current user with the existing `getCurrentUser` import in `src/views/ProjectBoard.vue`
- [ ] 4.2 Add a predicate for "assigned to the current user" that does not shadow the existing `isHighlighted(task)` deep-link predicate
- [ ] 4.3 Add a current-user mark to the card in the week view, as a wrapper class on the card plus a text or icon mark so the mark is not colour alone
- [ ] 4.4 Confirm the mark is absent for tasks assigned to another user and for unassigned tasks

## 5. drag a task to another day

- [ ] 5.1 Add `dragover` and `drop` handlers per day column in `src/views/ProjectBoard.vue`, alongside the existing column drag handlers
- [ ] 5.2 On drop, call the existing `updateTask(taskId, { dueDate })` action from `src/store/projects.js`; no new endpoint, controller or store action
- [ ] 5.3 Move the card to the target column immediately and revert it to its original column when the update fails, in the same optimistic-then-revert shape as the existing status move
- [ ] 5.4 Show a message to the user when the move fails
- [ ] 5.5 Skip the update when the card is dropped on the day it already has
- [ ] 5.6 Allow a card in the "Unscheduled" area to be dropped on a day column and leave the area

## 6. tests

- [ ] 6.1 Vitest: `groupTasksByDay` places a task on the correct day, excludes a task outside the week, and buckets a task without a due date as unscheduled
- [ ] 6.2 E2E in `tests/e2e/kanban-board.spec.ts` using `openFixtureProjectBoard` from `tests/e2e/nav.ts`: switching to the week view shows seven columns, Monday to Sunday
- [ ] 6.3 E2E: a task assigned to the current user carries the current-user mark, a task assigned to another user does not
- [ ] 6.4 E2E: drag a task from Monday to Wednesday, reload, and confirm the task is still on Wednesday
- [ ] 6.5 E2E: open the day view for Wednesday after the drag and confirm the task is listed there
- [ ] 6.6 E2E: a task without a due date appears in the "Unscheduled" area and not in a day column

## 7. spec and docs

- [ ] 7.1 Merge the MODIFIED and ADDED requirements from `specs/kanban-board/spec.md` into `openspec/specs/kanban-board.md` at archive time
- [ ] 7.2 Note in `design.md` that a day drag writes `dueDate` and can therefore fire the `taskDueSoon` notification
