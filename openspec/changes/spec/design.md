# Time Tracking MVP

**Status**: pr-created
**Spec reference**: [time-tracking](../../specs/time-tracking.md)
**Priority**: MVP

## Summary

Add basic time tracking to Planix. Users can log time entries against tasks
and view a simple timesheet. No complex reporting — just CRUD for time entries
and a per-task time log display.

## Scope (MVP only — 2 tasks)

- Backend: TimeEntry service + controller (CRUD via OpenRegister)
- Frontend: Time log section on task detail + simple "Log time" form

## Out of scope

- Timesheet views, burndown charts, CSV export (V1)
- Timer/stopwatch functionality (V1)
- Approval workflows (V1)

## Architecture

- TimeEntry stored as OpenRegister objects (schema already defined in register)
- Backend: TimeEntryController with standard CRUD endpoints
- Frontend: TimeLog.vue component embedded in task detail view
