# Kanban Board Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [task-quick-add](../../) — introduces column rendering in ProjectBoard.vue, basic task cards in each column, and inline quick-add task creation at the column footer

## Purpose

Replaces the "Board view coming soon" placeholder with a real column layout and adds an inline quick-add interaction at the footer of each column. This is the first concrete implementation step for the kanban board view.

---

## ADDED Requirements

### Requirement: Column Layout Rendering [MVP]

`ProjectBoard.vue` MUST render the project's columns as a horizontally-arranged list, replacing the "Board view coming soon" placeholder.

#### Scenario: Columns are rendered in order
- GIVEN a project has columns with configured `order` values
- WHEN a project member opens the board view
- THEN the system MUST display columns in ascending `order` sequence
- AND each column MUST show its `title` in a column header

#### Scenario: No columns yet
- GIVEN a project has no columns
- WHEN a project member opens the board view
- THEN the system MUST display a `NcEmptyContent` with message "No columns yet"
- AND a descriptive hint MUST guide the user to add columns via project settings

#### Scenario: Columns loading
- GIVEN the board view is opening and the column fetch is in-flight
- WHEN the component is mounted
- THEN the system MUST show a loading indicator
- AND MUST NOT render the empty-state or column list until the fetch completes

---

### Requirement: Quick-Add Task in Column [MVP]

Each kanban column MUST have a permanently-visible "+ Add task" trigger at its footer that allows a user to create a task with only a title, without opening the full task creation form.

This is distinct from the empty-column `CnEmptyState` "+ Add task" button (which opens the task creation modal with the column pre-selected). The footer quick-add is always visible regardless of whether the column has tasks.

#### Scenario: Trigger renders at column footer
- GIVEN a project has one or more columns displayed on the board
- WHEN the board is rendered
- THEN each column footer MUST show a "+ Add task" button

#### Scenario: Clicking trigger expands inline input
- GIVEN a column footer shows the "+ Add task" button
- WHEN the user clicks the button
- THEN the button MUST be replaced by an inline `<textarea>` input
- AND the `<textarea>` MUST receive focus immediately

#### Scenario: Enter key creates the task
- GIVEN the inline input is expanded and contains a non-empty title
- WHEN the user presses Enter (without Shift)
- THEN the system MUST POST the new task to OpenRegister with `title` and `column` set
- AND on success the input MUST collapse back to the trigger button
- AND focus MUST return to the trigger button

#### Scenario: Enter key ignored when input is empty
- GIVEN the inline input is expanded and the draft is empty or whitespace-only
- WHEN the user presses Enter
- THEN the system MUST NOT submit a task creation request

#### Scenario: Escape key cancels without saving
- GIVEN the inline input is expanded with or without draft text
- WHEN the user presses Escape
- THEN the system MUST discard the draft
- AND the input MUST collapse back to the trigger button
- AND NO task creation request MUST be made

#### Scenario: Loading state during creation
- GIVEN the inline input has been submitted
- WHEN the task creation POST is in-flight
- THEN the `<textarea>` MUST be disabled
- AND the Save button MUST show a loading indicator and be disabled
- AND the Cancel button MUST be disabled

#### Scenario: Error feedback on creation failure
- GIVEN the inline input has been submitted
- WHEN the task creation POST returns an error
- THEN the system MUST display an inline error message below the `<textarea>`
- AND the `<textarea>` MUST retain the draft text so the user can retry
- AND the system MUST NOT clear the input or collapse to the trigger

#### Scenario: Shift+Enter inserts newline (not submit)
- GIVEN the inline input is expanded
- WHEN the user presses Shift+Enter
- THEN the system MUST insert a newline into the draft
- AND MUST NOT submit the task creation request

---

## MODIFIED Acceptance Criteria

The following acceptance criterion from the base kanban-board spec is updated to distinguish the two "+ Add task" paths:

**Old (base spec line 139)**:
> Empty columns show a CnEmptyState with a "+ Add task" button; clicking pre-selects that column in the task form

**New (this delta)**:
> Empty columns show a `NcEmptyContent` with a "+ Add task" button; clicking opens the full task creation form with that column pre-selected (modal path).
>
> In addition, every column — regardless of whether it is empty — MUST display a "+ Add task" trigger at its footer that expands to an inline text input (quick-add path). These are two distinct interaction paths; the modal path is for tasks that require description, priority, assignee, or due date at creation time. The inline path is for rapid title-only entry.

---

## ADDED Acceptance Criteria

- [ ] ProjectBoard.vue renders columns in configured order; "Board view coming soon" placeholder is removed
- [ ] An empty project (no columns) shows `NcEmptyContent` with "No columns yet" message
- [ ] Each column body renders existing tasks as basic `TaskCard` components (title, priority dot, due date, assignee)
- [ ] An empty column body shows a "No tasks" hint alongside the quick-add footer
- [ ] Each column footer permanently shows a "+ Add task" button
- [ ] Clicking "+ Add task" expands an inline `<textarea>` that receives focus immediately
- [ ] Enter (without Shift) submits the task with the draft title and target column; input collapses on success
- [ ] Enter is ignored when draft is empty or whitespace-only
- [ ] Escape cancels without creating a task; input collapses; draft is discarded
- [ ] Shift+Enter inserts a newline; does not submit
- [ ] While saving: textarea, Save button, and Cancel button are all disabled
- [ ] On creation failure: inline error message shown; draft preserved; input stays expanded
- [ ] `<label>` is associated to the `<textarea>` via matching `for`/`id`
- [ ] All user-visible strings use `t('planix', '...')`
- [ ] All CSS uses Nextcloud CSS variables

## Non-Functional Requirements

- **Accessibility**: The quick-add form is fully keyboard-navigable (WCAG AA). Tab order: trigger button → textarea → Save → Cancel. `role="form"` and associated `aria-label` on the expanded form. Error span uses `role="alert"`.
- **Performance**: Column fetch is a single OpenRegister query filtered by project ID; no N+1 calls per column.
- **Internationalisation**: All strings available for Dutch and English translation (ADR-007).

## Notes

- Basic task card rendering (`TaskCard.vue`) is in scope for this change. Full cards (drag-and-drop, WIP limits, clickable navigation) are in the kanban-board change.
- The `columnId` and `projectId` are passed as props to `QuickAddTask.vue`; the component does not read from the router or DOM.
- The quick-add creates a task with `title` and `column` only. All other task fields (`description`, `priority`, `assignee`, `dueDate`) use their OpenRegister schema defaults.
