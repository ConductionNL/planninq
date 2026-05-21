---
kind: code
---

# Proposal: Task Quick-Add

## Summary

Add an inline "Add task" button to the footer of each kanban column that lets users create a task with just a title — without opening the full task creation form. Pressing Enter commits the task to the column; pressing Escape cancels.

This change also replaces the current "Board view coming soon" placeholder in `ProjectBoard.vue` with a real column-rendering layout, making it the first concrete step toward the full kanban board implementation.

## Motivation

The kanban board is the primary interface for Planix, but `ProjectBoard.vue` currently shows only a placeholder. Users who open the board view today are sent back to the backlog for all work. The quick-add feature is the highest-value lowest-cost entry point for the real board UI: it delivers a usable column layout AND unblocks the most common board action (creating a task) in a single, focused change.

The existing spec acceptance criterion for "Empty column: clicking '+ Add task' MUST open the task creation form" remains — that path (modal, pre-selects column) is distinct from the footer quick-add. The quick-add is always-visible at the column footer for rapid entry; the modal path remains for tasks requiring description, priority, assignee, or due date at creation time.

## Affected Projects

- [x] Project: `planix` — Frontend-only: new `QuickAddTask.vue` component + column rendering loop in `ProjectBoard.vue`

## Scope

### In Scope

- Replace the "Board view coming soon" placeholder in `ProjectBoard.vue` with a horizontal column-rendering loop
- New `src/components/QuickAddTask.vue` — inline add-task input at the bottom of each column
- Keyboard handling: Enter to submit, Escape to cancel
- Store call to create the task via `useObjectStore` (OpenRegister `POST /objects`)
- Loading state during creation (button/input disabled)
- User-facing error feedback when creation fails (inline, no stale input)
- All strings via `t('planix', '...')` (ADR-007)
- CSS via Nextcloud CSS variables only (ADR-004)

### Out of Scope

- Full task card rendering (title, assignee, due date, priority chips) — that is part of the kanban-board change
- Drag-and-drop between columns
- WIP limit enforcement
- Column management (add / reorder / delete columns)
- View toggle (kanban ↔ list)
- Task form modal (separate component, separate change)
- Column data fetching — this change assumes columns are loaded by a future `useColumnsStore`; for MVP the column list is stubbed or loaded directly in `ProjectBoard.vue`

## Approach

1. `ProjectBoard.vue`: replace the `NcEmptyContent` placeholder with a `<div class="project-board__columns">` that iterates over `columns` and renders a column card per entry.
2. New `QuickAddTask.vue` component: sits at the bottom of each column card. Exposes a "+ Add task" button. On click, shows a `<textarea>` (single-line in appearance) bound to `draft`. On Enter (no Shift) — calls the task creation store action, clears the draft, hides the input. On Escape — clears the draft, hides the input. Loading and error states managed locally.
3. Task creation calls `useObjectStore` (configured in `store/store.js`) with `type: 'task'` and payload `{ title, column: column.id }`.

## New Dependencies

None — `useObjectStore`, `@nextcloud/axios`, and `t()` are all already present.

## Impact

- **Modified file**: `src/views/ProjectBoard.vue` — replace placeholder, add column loop, import `QuickAddTask.vue`
- **New file**: `src/components/QuickAddTask.vue` — inline quick-add component
- **No backend changes** — uses existing OpenRegister task creation endpoint
- **No schema changes** — task object already has `title` and `column` fields

## Capabilities

### Modified Capabilities

- `kanban-board`: Column rendering introduced (replaces placeholder). Quick-add inline task creation added as a new interaction pattern distinct from the full task creation modal. The empty-column `CnEmptyState` "+ Add task" button (modal path) and the always-visible column footer quick-add (inline path) are two distinct user paths — this change implements the inline footer path.

## Cross-Project Dependencies

None

## Risks

### Risk 1: Columns not yet loaded by a store
**Severity:** Low — **Mitigation:** For this change, `ProjectBoard.vue` fetches columns directly via `useObjectStore` using the project ID filter. A dedicated `useColumnsStore` is a future refactor. The direct fetch pattern is already established for similar entities in the app.

### Risk 2: Column schema register/schema IDs unknown at write time
**Severity:** Low — **Mitigation:** Register and schema IDs are read from `useSettingsStore` at runtime (same pattern as tasks and projects). Tasks artifact notes the exact settings keys to use.

## Rollback Strategy

Revert the single PR. No data changes, no schema changes, no migrations — purely additive frontend code. The placeholder is restored by reverting `ProjectBoard.vue`.
