---
kind: code
---

# Proposal: Implement the Time Tracking MVP (Spec Exists, Frontend Does Not)

## Why

`openspec/specs/time-tracking.md` is a fully-written MVP spec (30
MUST/SHALL-bearing lines, 5 requirements, acceptance criteria) that names its
own implementation change: *"[time-tracking](../changes/time-tracking/) —
implements manual time logging, timesheet view, estimate input, progress
indicator"*. That change does not exist:

```
$ ls openspec/changes/ openspec/changes/archive/
adopt-apphost  archive
2026-06-14-due-date-reminder-dispatch  2026-06-14-label-management-admin
2026-06-14-task-collaboration-sidebar  2026-06-14-task-dependencies
2026-06-15-task-due-date-warning       retrofit-2026-05-24-annotate-planix
retrofit-2026-05-24-reverse-spec-projects-backlog
retrofit-2026-05-25-app-shell-and-data-store
retrofit-2026-05-31-retrofit-2026-05-26-planix-display-capabilities
```

No `time-tracking` slug, active or archived. The backend data model IS fully
built and even seeded — `lib/Settings/planix_register.json:345-360` declares
the `timeEntry` schema, `line 146` declares `estimatedDuration` on the task
schema, and seed fixtures at lines 672/697/701/709/717 create sample
`TimeEntry` objects. But the frontend never surfaces any of it:

```
$ grep -n "time\|estimate\|duration" src/views/TaskDetail.vue -i
(zero matches)
$ grep -rli "timeentry\|time.tracking\|timeTracking" src --include=*.vue --include=*.js
src/store/projects.js   # only a passing reference, no TimeEntry CRUD
```

There is no "Log time" button, no estimate input, no progress indicator, and
no Timesheet view anywhere in `src/views/` or `src/components/`. Per ADR-001
(planix's own accepted information architecture), Timesheet/time-tracking is
supposed to live at "Mijn werk > Tijdregistratie" — that placement doesn't
exist either; "Mijn werk" itself isn't a menu yet (see the companion change
`adopt-five-menu-navigation-ia`).

This is a data model shipped with literally zero UI to exercise it — an MVP
feature that reads as "done" in the register schema but is 0% complete in
the product a user actually touches.

## What Changes

- **Task detail** (`src/views/TaskDetail.vue`): add an estimate input
  (accepting `"2h 30m"`, `"150m"`, `"1.5h"`, `"90"`, `"2h"`; inline validation
  error on unparseable/zero/negative input), a "Log time" action that opens a
  new `src/components/dialogs/TimeEntryDialog.vue` (duration + date +
  optional description), and a progress indicator ("1h 30m / 3h", turning red
  with an overage label when logged time exceeds the estimate).
- **Task card** (wherever the kanban card component renders — `ProjectBoard`/
  `ProjectBacklog`): display the estimate badge when set.
- **New Timesheet view** (`src/views/Timesheet.vue`): entries grouped by date
  (newest first), each row showing task title / project / duration /
  description, a daily total per date group, a weekly total for the current
  filter, a date-range filter (defaulting to "This week"), and click-through
  to the task detail view with back-navigation preserving scroll position and
  filter state.
- **New store module** (`src/store/timeEntries.js`, following the existing
  `createObjectStore` pattern used by `src/store/projects.js`) for TimeEntry
  CRUD against the `timeEntry` schema. The per-owner guard already exists at
  the schema level — `planix_register.json:361-381` scopes `update`/`delete`
  to `match: { user: "$userId" }` OR `admin` — so a direct API call from a
  non-owner is already rejected by OR RBAC (ADR-022: consumed, not
  reimplemented). The frontend work is to also hide edit/delete controls in
  the UI for entries the viewing user doesn't own, matching the server-side
  rule (defence-in-depth in the other direction: UI hides what the API would
  reject).
- **New utility** (`src/utils/durationParser.js`): parse the five accepted
  estimate formats to integer minutes, and format minutes back to
  human-readable (`"1h 30m"`).
- **Navigation**: add a "Timesheet" entry (interim: under the existing
  Dashboard/Projects menu; final placement "Mijn werk > Tijdregistratie" once
  `adopt-five-menu-navigation-ia` lands — this change does not block on that
  one, it uses whatever menu structure is live at merge time).

## Impact

- Added: `~4` new/modified Vue files, 1 store module, 1 utility module.
- No backend controller or schema change needed: reads/writes go straight to
  OR's object API from the frontend store (ADR-022), and the RBAC guard
  already exists on the schema — this is a pure UI implementation of an
  already-existing, already-guarded data model.

## Dependencies

None.
