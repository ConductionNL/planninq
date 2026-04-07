# Design: Time Tracking MVP

**Status:** pr-created
**Spec:** [time-tracking](../../specs/time-tracking.md)
**ADR dependencies:** [task-status-model](../../architecture/task-status-model.md)

## Technical Approach

### Data Model

Add a `TimeEntry` schema to OpenRegister with properties:
- `task` (reference to Task object)
- `user` (string, Nextcloud user UID)
- `duration` (integer, minutes)
- `date` (date, ISO 8601)
- `description` (string, optional)

Add `estimatedDuration` (integer, minutes) property to the existing Task schema.

`loggedDuration` is computed as SUM of all TimeEntry.duration for the task — not stored, calculated on read.

### Frontend

- **Task detail page:** Add time section with estimate input + time log list + "Log time" button
- **Kanban card:** Show estimate badge (e.g., "2h 30m") when set
- **Timesheet view:** New route `/timesheet` showing personal time entries grouped by day, with weekly total
- **Duration input:** Parse human-friendly input ("2h 30m", "2.5h", "150m", "150") into minutes

### Backend

- **Register config:** Add TimeEntry schema to `planix_register.json`
- **No custom PHP:** All CRUD via OpenRegister API from frontend
- **useObjectStore:** TimeEntry uses the standard `useObjectStore` from `@conduction/nextcloud-vue`

### Integration

- OpenRegister `_files` metadata: attach receipts/screenshots to time entries (optional)
- Nextcloud Calendar: time entries do NOT create calendar events (out of scope for MVP)

## Affected Files

| File | Change |
|------|--------|
| `lib/Settings/planix_register.json` | Add TimeEntry schema + estimatedDuration to Task |
| `src/views/TaskDetail.vue` | Add time tracking section |
| `src/views/Timesheet.vue` | New view — personal timesheet |
| `src/components/TimeEntryForm.vue` | New component — log time form |
| `src/components/TimeEstimateBadge.vue` | New component — kanban card badge |
| `src/components/DurationInput.vue` | New component — human-friendly duration parser |
| `src/router/index.js` | Add `/timesheet` route |
| `src/navigation/MainMenu.vue` | Add Timesheet nav item |
