# Design: time-tracking

**Change ID:** time-tracking
**Status:** draft
**Created:** 2026-04-02

---

## Context

The `register-schemas` change defined the `TimeEntry` schema in `planix_register.json`. The `tasks` change built the Task detail view, backlog, and Pinia task store. This change builds the complete time-tracking interaction layer on top of those foundations.

Planix is a thin client: all data operations go through OpenRegister via `useObjectStore('planix', 'timeEntry')`. No PHP controllers are needed for time entry CRUD. Permission enforcement for edit/delete is client-side only (show controls only when `timeEntry.user === currentUserUid`). The `loggedDuration` for a task is computed at read time — it is not stored on the Task object.

---

## Goals

- Time duration parser that accepts multiple human formats and returns integer minutes.
- Time display formatter that converts integer minutes to "Xh Ym".
- Time entry CRUD via `useObjectStore('planix', 'timeEntry')`.
- Estimate field on task create dialog and task metadata sidebar.
- Progress indicator component (logged vs. estimated, over-estimate state).
- Time entries list on task detail with own-entry edit/delete.
- Personal timesheet view grouped by date with date range filter.
- Full i18n coverage (en + nl).

## Non-Goals

- Live/running timer (V1 — explicitly deferred in ADR-004).
- CSV export of timesheet (V1).
- Project-level time report or team timesheet (V1).
- Backend permission enforcement for time entry edit/delete (client-side only in MVP).
- Storing `loggedDuration` on the Task object (computed at read time).

---

## Decisions

### Decision 1: Time duration parser is a pure regex-based utility function

**Options considered:**
1. Parse duration inside each component that needs it.
2. Centralised `parseMinutes(input)` utility in `src/utils/timeDuration.js` (chosen).

**Rationale:** Duration parsing is used in the time entry form, the estimate field on task create/edit, and the timesheet filter total display. Duplicating the logic in each component would create divergence. A single pure function is trivially testable (no Vue dependency) and importable anywhere.

Accepted formats and their parsed results:

| Input | Result (minutes) |
|-------|-----------------|
| `"2h 30m"` | 150 |
| `"2h30m"` | 150 |
| `"2h"` | 120 |
| `"30m"` | 30 |
| `"150m"` | 150 |
| `"1.5h"` | 90 |
| `"90"` | 90 (bare number = minutes) |
| `"2h 30"` | 150 (trailing bare number = minutes) |

Invalid inputs that return `null` (not 0 — caller decides how to handle):
- Empty string or whitespace-only
- Negative values: `"-5"`, `"-1h"`
- Zero: `"0"`, `"0m"`, `"0h"`
- Non-numeric: `"lots"`, `"abc"`, `"two hours"`
- Values exceeding 999h (sanity ceiling)

`formatMinutes(minutes)` rules:
- If `minutes >= 60`: return `"Xh Ym"` (e.g. `150` → `"2h 30m"`; `120` → `"2h 0m"`)
- If `minutes < 60`: return `"Ym"` (e.g. `45` → `"45m"`)
- `formatMinutes(0)` → `"0m"`
- `formatMinutes(null)` → `""` (empty string — caller renders nothing)

### Decision 2: Time entries list on task detail is a dedicated sidebar tab

**Options considered:**
1. A collapsible section below the task description in the main content area.
2. A dedicated "Time" tab inside `CnObjectSidebar` (chosen).

**Rationale:** The `CnObjectSidebar` already has standard tabs (Files, Notes, Tags, Audit Trail) established by the `tasks` change. Adding a "Time" tab follows the same pattern and keeps the main content area clean for the task title and description. The Time tab renders: `TimeProgress.vue` at the top, a "Log Time" button, then `TimeEntriesList.vue`. This is consistent with how other Conduction apps (pipelinq) surface linked objects in the sidebar.

### Decision 3: Progress indicator uses CSS-based bar with cn- prefix variables

**Options considered:**
1. Use an `NcProgressBar` component from `@nextcloud/vue`.
2. Custom CSS bar using Conduction design token variables (chosen).

**Rationale:** `NcProgressBar` does not support a colour-change-on-overflow behaviour. The over-estimate (red) state requires the bar to visually indicate that the budget is exceeded, which means the bar must overflow its container and change colour. A custom CSS-based bar using `--cn-color-success` (green) and `--cn-color-error` (red) achieves this cleanly. The bar width is `min(100%, (logged / estimated) * 100%)` visually, with a separate overflow label when over estimate.

Component states:
| State | Condition | Display |
|-------|-----------|---------|
| No estimate | `task.estimatedDuration` is null | Show only `"Logged: Xh Ym"`, no bar |
| Under estimate | logged < estimated | Green bar, text `"Xh Ym / Yh Zm"` |
| At estimate | logged === estimated | Green bar at 100%, text `"Xh Ym / Yh Zm"` |
| Over estimate | logged > estimated | Red bar at 100% + overflow indicator, text `"Xh Ym / Yh Zm"` (logged in red) |

### Decision 4: `loggedDuration` is computed at read time from TimeEntry objects

**Options considered:**
1. Store `loggedDuration` as a field on the Task object, updated on every time entry write.
2. Sum `TimeEntry.duration` values for the task at read time (chosen).

**Rationale:** Storing a derived value on Task creates a consistency problem — if a time entry is deleted or edited, the task must be updated atomically. OpenRegister has no transaction support. Computing the sum at read time is always accurate and avoids stale cache issues. The `useTimeEntriesStore` exposes a computed `loggedDuration(taskId)` getter that sums durations from the loaded entries for that task.

Performance note: For MVP, entries are fetched when the Time tab is opened. The getter is reactive — adding/editing/deleting an entry in the same session updates the sum instantly without a re-fetch.

### Decision 5: Personal timesheet queries by `user` filter; grouping is client-side

**Options considered:**
1. Server-side grouping by date (not supported by OpenRegister).
2. Fetch all entries for the current user, group client-side (chosen).

**Rationale:** OpenRegister's filter API supports `user=currentUserUid`. The response is a flat list of `TimeEntry` objects ordered by `date` descending. Client-side grouping into date buckets is a simple `reduce` over the sorted array. The date range filter is applied as a server-side filter parameter (`date_gte`, `date_lte`) to avoid loading all historical entries.

### Decision 6: Date range presets use ISO week boundaries

**Options considered:**
1. Use calendar-library-computed "this week" (locale-aware, Monday vs. Sunday start).
2. ISO 8601 week (Monday start) for all presets (chosen).

**Rationale:** Planix targets Dutch government users (nl locale). ISO 8601 week (Monday = day 1) is the standard in the Netherlands. All date range presets — "This week", "Last week" — use Monday as the week start. "Custom" uses `NcDateTimePicker` with two date inputs.

### Decision 7: Permission model is client-side only for MVP

**Options considered:**
1. Backend enforcement — PHP controller checks `timeEntry.user === currentUser` before allowing PUT/DELETE.
2. Client-side only — show Edit/Delete buttons only when `timeEntry.user === currentUserUid`; rely on OpenRegister's object-level ownership for actual enforcement (chosen for MVP).

**Rationale:** OpenRegister's object ownership model already restricts writes to the object's creator. The frontend just needs to hide the controls for entries the user didn't create, to avoid confusing UX (the API would reject the request regardless). A full PHP permission layer is out of scope for MVP.

---

## Component Diagram

```
TimesheetView.vue
  ├── DateRangeFilter (inline, NcDateTimePicker for custom range)
  └── CnDataTable (entries grouped by date)
        ├── Date header row (date + daily total)
        └── Entry row (task title link, project, duration, description)

TaskDetail.vue (CnObjectSidebar "Time" tab)
  ├── TimeProgress.vue (logged/estimated bar)
  ├── NcButton "Log Time" → opens TimeEntryForm.vue (modal)
  └── TimeEntriesList.vue
        └── TimeEntryRow (date, duration, description, user avatar, edit/delete if own)
              └── TimeEntryForm.vue (inline edit mode)
```

---

## Data Flow

```
useTimeEntriesStore
  ├── fetchEntries(taskId)      → OpenRegister GET /objects?type=timeEntry&task=taskId
  ├── fetchMyEntries(filters)   → OpenRegister GET /objects?type=timeEntry&user=uid&date_gte=...
  ├── logTime(data)             → OpenRegister POST /objects (type=timeEntry)
  ├── updateEntry(id, data)     → OpenRegister PUT /objects/:id
  ├── deleteEntry(id)           → OpenRegister DELETE /objects/:id
  └── loggedDuration(taskId)    → computed: sum of entries[taskId].duration
```
