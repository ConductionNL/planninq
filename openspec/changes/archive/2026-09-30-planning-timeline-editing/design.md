# Design: reschedule tasks on the timeline, move dependent work along, and plan in working days

## Context

What exists at de35541:

- `GET /apps/planninq/api/projects/{projectId}/timeline` (`appinfo/routes.php`, `lib/Controller/TimelineController.php:123-204`) reads the project through `ObjectService` with RBAC on (403 when the caller cannot see it), then returns the tasks split into scheduled and unscheduled rows (`timelineRow` at `:310-321`: id, title, status, priority, startDate, dueDate, `duration` from `estimatedDuration`, percentComplete) and the dependency edges between them (`fetchProjectDependencies` at `:264-297`). The edge rows carry `id`, `blocker` and `blocked` only; the `type` the dependency schema declares (`lib/Settings/planninq_register.json:1152-1164`: blocks, relates, duplicates, clones, splits, causes) is dropped, so the chart draws a "relates" link as the same arrow as a "blocks" link.
- `src/api/timeline.js` wraps that GET and is "deliberately NOT a Pinia store: the timeline is a read-only surface".
- `src/views/ProjectTimeline.vue` renders bars from the pure helpers `toScheduled` and `buildLayout` (`src/utils/timelineHelpers.js:75-103`), with a day, week and month zoom (`PX_PER_DAY` at `:24`). Bars are `div`s with a title tooltip and nothing else (`src/views/ProjectTimeline.vue:97-104`). Weekend ticks are flagged by UTC weekday (`:297-315`) and shaded by CSS (`:509`). The doc comment states the view never mutates an object (`:140-150`).
- The capability `gantt-timeline-view` requires that the timeline "MUST NOT introduce a new schema, new storage, or a scheduling engine" (`openspec/specs/gantt-timeline-view/spec.md:12-13`).
- The board already writes task fields with `updateTask(taskId, patch)`, a PATCH straight to OpenRegister that OpenRegister authorizes per object (`src/store/projects.js:871-890`). The task schema lets any member of the task's project update it (`lib/Settings/planninq_register.json:98-120`).
- Dependencies are acyclic by construction: `DependencyService::create` rejects self edges, duplicates, cross-project edges and cycles (`lib/Service/DependencyService.php:112-141`, `:215-236`).
- Admin settings are IAppConfig keys with defaults in `SettingsService::ADMIN_CONFIG_DEFAULTS` (`lib/Service/SettingsService.php:60-64`), validated per key on write (`setAdminSettings` at `:239-279`), and returned to any signed-in user by `GET /api/settings` (`lib/Controller/SettingsController.php:73-80`, `SettingsService::getSettings` at `:340-361`). The admin screen is `src/views/settings/Settings.vue`, one `CnSettingsSection` per topic.

What is missing: any write from the timeline, a keyboard path to change dates, a rule for moving dependent work, and a working calendar.

## Goals / non-goals

Goals:
- Change a task's dates where the member sees them in time, by pointer and by keyboard.
- Let a project opt in to pushing blocked work later when its blocker slips, with a preview.
- One working calendar that every date calculation and the shading use.

Non-goals:
- Capacity-aware or resource-levelled scheduling, per-person calendars.
- Moving work earlier automatically.
- A server-side scheduler.

## Decisions

### Decision 1: what stays read-only
The timeline endpoint stays a GET that creates, updates and deletes nothing, and the timeline still adds no schema of its own. What changes is that the timeline view may write the existing `startDate` and `dueDate` of existing tasks, through the same object API and the same member rights the board uses. That is why the modified requirement drops "or a scheduling engine" and "it is a read surface over existing task objects", and keeps "no new schema, no new storage" and every read rule (RBAC scoping, unscheduled tasks listed, not dropped). The reason for lifting it: a planning chart that cannot be edited sends the member elsewhere for the one thing the chart is best at, and four competitors in the matrix edit on the chart.

### Decision 2: pointer and keyboard paths to the same change
Each bar becomes a focusable button. Pointer: dragging the middle moves the bar; dragging the left or right edge (an 8 px handle) changes the start or due date. Keyboard: Left and Right move the task by one working day, Shift with Left or Right moves only the due date, and Enter opens `TaskDatesDialog` (in `src/dialogs/`) with a start and a due date field. Every change is announced in a live region ("Export to CSV now runs from 12 October to 16 October"). This is the ADR-059 equivalent of drag and drop, the same idea as the board's "Move task to another column" menu (`src/views/ProjectBoard.vue:142-165`).

### Decision 3: writes go through `updateTask`, one PATCH per task
A move sends `{ startDate, dueDate }` for that task through `updateTask`. OpenRegister enforces membership on the write. A failed write puts the bar back and shows the error. After a cascade the view reloads from the timeline GET, so the chart only ever shows saved dates. The alternative, a new `POST /api/projects/{id}/reschedule` that computes and writes on the server, would be a controller that ends in the same per-object writes (OpenRegister has no multi-object transaction), so it buys no atomicity and adds an endpoint to secure.

### Decision 4: auto-scheduling is opt-in per project, forward-only, and previewed
New `project.autoSchedule` boolean, default `false`, set by the project owner in `ProjectSettingsSidebar`. When it is on and a member moves a task's due date later, the pure helper `cascade(tasks, edges, changedId, calendar)` in `src/utils/scheduling.js` walks the `blocks` edges from that task: every successor whose start is not after its blocker's new due date moves to start on the next working day after it, keeping its length in working days, and so on down the chain. Only `type: blocks` edges count (edges without a type are `blocks`, the schema default). Moves go later only. `RescheduleDialog` lists every task that would move with its old and new dates and offers "Move all", "Only this task" and "Cancel". OpenProject's manual-by-default, automatic-per-choice model is the reference; the difference is that the choice here is per project, not per task, to keep one switch.

### Decision 5: the timeline payload names the edge type
`fetchProjectDependencies` adds `type` (default `blocks`) to each edge row. The timeline draws `blocks` edges as arrows, as today, and other types as dotted lines without arrowheads, so a member sees which links will move work.

### Decision 6: one app-wide working calendar in the admin settings

Amended at build (30 Sep): the two keys live in `WorkingCalendarService`, saved and read through `SettingsController` next to `TimetableGridService`, not in `SettingsService::ADMIN_CONFIG_DEFAULTS`, because `SettingsService` is at phpmd's complexity and coupling limits. The admin section is its own component, `src/components/WorkingCalendarSettings.vue`. The Dutch holidays added are New Year's Day, Easter Sunday and Monday, King's Day (26 April when 27 April is a Sunday), Liberation Day, Ascension Day, Whit Sunday and Monday, Christmas Day and Boxing Day, each named in the admin's language.

Two new admin keys: `working_weekdays` (JSON array of ISO weekday numbers, default `[1,2,3,4,5]`) and `non_working_days` (JSON array of `{ date, name }`, default `[]`), validated like `default_columns` (a malformed value is rejected and logged). They are returned by `GET /api/settings` to every signed-in user, so the timeline can read them without an admin call. The admin edits them in a new "Working days and holidays" section of `src/views/settings/Settings.vue`, which can also fill in the Dutch national holidays for a chosen year (a pure helper computing the fixed dates and the Easter-based ones). This sits in Beheer per ADR-001 rule 5. The alternative, a `workingCalendar` schema in the register, would put configuration among the business objects and make every member a potential writer.

### Decision 7: the working calendar drives every date the timeline computes
`src/utils/workingCalendar.js` offers `isWorkingDay`, `nextWorkingDay`, `addWorkingDays` and `workingDaysBetween`. A bar dropped on a non-working start moves to the next working day, and the due date keeps the task's length in working days. The axis shades every non-working day, weekends and listed holidays alike, and a holiday tick carries its name as its accessible label. The weekday test uses the date in UTC, as the axis does today, so a date never shifts with the browser's time zone.

## Risks / trade-offs

- [A cascade half-writes] -> Preview first, writes in dependency order, stop on the first failure, report, reload.
- [Large chains] -> The preview lists every task; a chain of more than 50 tasks shows the count and the first 50, and still moves all of them only after confirmation.
- [Members on a project without dependencies see no benefit from auto-scheduling] -> The switch explains that it follows "blocks" links, and it stays off by default.
- [Editing on a phone] -> Dragging needs a pointer; the Enter dialog works on touch as a tap on the bar.
