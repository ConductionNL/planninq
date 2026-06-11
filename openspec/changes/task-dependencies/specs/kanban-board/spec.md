# Kanban Board Specification (Delta)

**Status**: proposed
**Scope**: planix
**OpenSpec changes**:
- [task-dependencies](../../) — adds the blocked-task indicator to the board card anatomy

## Purpose

Extends the kanban board capability with the blocked-task indicator the base spec already anticipated as a V1 item ("Blocked task indicators"). The blocked state itself is defined by the `task-dependencies` capability; this delta covers only its board presentation.

## ADDED Requirements

### Requirement: Blocked task indicator on cards [V1]
Task cards on the kanban board MUST show a compact "Blocked" indicator when the task's derived blocked state (per the `task-dependencies` capability: at least one blocker not `done`/`cancelled`) is true. The indicator MUST be visually consistent with the card's other status chips (priority, due-date badge), MUST not require opening the task to be understood, and MUST never prevent dragging the card (soft signal, same philosophy as the WIP limit). Board filters and the list-view toggle MUST render the indicator identically.

#### Scenario: Blocked badge shown on the card
- GIVEN a task on the board is blocked by an open task
- WHEN the board renders
- THEN the task's card MUST show a "Blocked" badge alongside its existing chips

#### Scenario: Badge visible in list view too
- GIVEN the same blocked task
- WHEN the user toggles the board to list view
- THEN the row MUST show the same blocked indication

#### Scenario: Dragging a blocked card is not prevented
- GIVEN a card shows the Blocked badge
- WHEN the user drags it to another column
- THEN the drop MUST succeed and the badge MUST remain while blockers stay open

## Acceptance Criteria

- [ ] Blocked badge appears on kanban cards and list-view rows for tasks with open blockers
- [ ] Badge styling matches the existing card chip language (incl. the due-date warning badge)
- [ ] Drag-and-drop behavior is unchanged for blocked cards

## Notes

- Derivation rules, tolerance for dangling edges, and the underlying data live in the `task-dependencies` capability spec; this delta is presentation-only.
- The in-flight `task-due-date-warning` change introduces the card badge styling baseline; this indicator reuses that visual language but shares no code requirement with it.
