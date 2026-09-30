# Design: see tasks in a calendar, and export them one way to Nextcloud Tasks

## Context

What exists at de35541:

- `task.calendarEventUid` (`lib/Settings/planninq_register.json:311-316`), nullable string, "UID of the linked NC Tasks / CalDAV VTODO event". Nothing reads or writes it.
- The task fields the VTODO needs are all present: `title`, `description`, `status`, `priority`, `assignedTo`, `dueDate`, `startDate`, `percentComplete`, `completedAt` (`lib/Settings/planninq_register.json:177-323`). `docs/ARCHITECTURE.md:94-130` maps each to its VTODO property (SUMMARY, DESCRIPTION, STATUS, PRIORITY, ATTENDEE, DUE, DTSTART, PERCENT-COMPLETE, UID, COMPLETED).
- `docs/ARCHITECTURE.md` section 5 question 1 fixes the direction: one-way export to the Nextcloud Tasks app, planninq writes, the Tasks app is not read back. `docs/ARCHITECTURE.md:343` names `OCP\Calendar\IManager` as the integration point.
- `TaskActivityListener` shows the pattern for reacting to task changes: it is registered for OpenRegister's `ObjectCreatedEvent`, `ObjectUpdatedEvent` and `ObjectDeletedEvent` (`lib/AppInfo/Application.php:536-541`), scopes to the planninq task schema through `TaskScopeResolver::isPlanninqTask` (`lib/Listener/TaskActivityListener.php:131-136`), diffs old against new data, and catches every error so OpenRegister's dispatch never breaks (`:92-118`).
- Per-user preferences are Nextcloud user config values, read in `SettingsService::getSettings` (`lib/Service/SettingsService.php:340-361`), written by `updateUserSettings` (`:376`) behind `POST /api/settings/user` (`lib/Controller/SettingsController.php:147-162`, `#[NoAdminRequired]` by docblock), and shown in `src/views/settings/UserSettings.vue` as `NcCheckboxRadioSwitch` toggles (`:12-17`).
- Tasks for one project are read with `fetchTasks(projectId)` (`src/store/projects.js:775-783`), which follows every page through `fetchEvery` (`:61-94`).
- There is no calendar component and no calendar page. The board header carries Backlog and Timeline buttons (`src/views/ProjectBoard.vue:41-55`).

## Goals / non-goals

Goals:
- A readable calendar of tasks by due date, per project and per person, that works with a keyboard and a screen reader.
- The user's assigned tasks visible in Nextcloud Tasks and Calendar and in any CalDAV client, without a second place to maintain them.

Non-goals:
- Two-way sync (decided no).
- Editing dates on the calendar.

## Decisions

### Decision 1: one calendar component, two pages
`src/components/TaskCalendar.vue` takes a list of tasks and renders a month grid or a week row, placing each task on its `dueDate`; a task with only a `startDate` sits on that date, marked "starts". A "List" view shows the same tasks grouped by date as a plain list. Two custom pages use it: `ProjectCalendar` at `/projects/:id/calendar` (reads `fetchTasks(projectId)`) and `MyCalendar` at `/my-calendar`, which reads the same set of tasks as the My tasks page of `portfolio-my-work-dashboard`: tasks whose `assignedTo` is the current user plus tasks whose `sharedWith` contains them (the field `tasks-assignment-priority-labels` adds), merged. The grid is a `table` with a caption naming the month, each day cell a `td` whose tasks are links to the task, and Previous, Today and Next buttons; the list view is the fallback for narrow screens.

### Decision 2: placement
The project calendar is a project view (Projecten > project): a Calendar tab after Timeline in the row of project tabs (`PROJECT_TABS`, `src/components/ProjectTabs.vue`). Amended at build: the board header no longer carries Backlog and Timeline buttons; `projects-overview-logs-risks` moved every project page into that tab row, so the calendar joins it rather than adding a header button. "My calendar" is a Mijn werk sub-page (ADR-001 rule 6). It gets no top-level menu entry: it is reached from a "Show as calendar" link on the My tasks page (`/my-tasks`, `portfolio-my-work-dashboard`) and from the dashboard's "Quick actions" panel (`src/components/DashboardPanels.vue:51`), and moves with My tasks when `adopt-five-menu-navigation-ia` builds the Mijn werk group.

### Decision 3: the export is per user and opt-in
A user switches on "Show my tasks in Nextcloud Tasks" in the personal settings. Planninq then keeps a task list named "Planninq" in that user's calendar home, holding one VTODO per task whose `assignedTo` is them or whose `sharedWith` contains them. It is per user because the Tasks app is personal and because the task schema already scopes reads to project members. It is opt-in because it writes into the user's own calendar data. Switching it off deletes the "Planninq" list. Switching it on queues a background job that exports the user's current assigned tasks once.

### Decision 4: a listener keeps the list in step, planninq always wins
`TaskCalendarExportListener`, registered for the same three OpenRegister events as `TaskActivityListener`, calls `TaskCalendarExportService` for every planninq task change. On create or update it writes the full VTODO for each person on the task (the `assignedTo` user and everyone in `sharedWith`) who opted in; when someone leaves the task it deletes the VTODO from their list; on delete it deletes it everywhere. The service maps the task to a VTODO: SUMMARY from `title`; DESCRIPTION from `description` plus the line "Managed by Planninq. Changes made here are replaced by the next change in Planninq."; DUE and DTSTART as all-day dates; STATUS open and blocked to NEEDS-ACTION, in_progress to IN-PROCESS, done to COMPLETED, cancelled to CANCELLED; PRIORITY urgent 1, high 3, normal 5, low 9; PERCENT-COMPLETE; COMPLETED from `completedAt`, which `boards-configurable-columns` stamps on the server when a task enters done; CATEGORIES the project title; URL the task's deep link (`src/manifest.json` `deepLinks`). Every write is a full rewrite, so an edit made in the Tasks app lasts only until the next planninq change. Errors are caught and logged, never thrown into OpenRegister's save.

### Decision 5: `calendarEventUid` is written once per task
The first export generates the UID `planninq-task-<task uuid>@<instance id>` and writes it to `task.calendarEventUid` through `ObjectService` as a system write. The same UID is used in every assignee's list. The listener ignores an update whose only change is `calendarEventUid`, so the write-back does not trigger itself. Storing it follows the recorded decision; it also lets another app find the VTODO for a task without knowing the rule.

### Decision 6: the alternative Deck uses is recorded, not chosen
Deck exposes each board as a read-only calendar through a Sabre calendar plugin, with no copies to keep in step (matrix evidence for `pln-caldav`). That design would make `calendarEventUid` unnecessary. Section 5 question 1 chose writing VTODOs to the Tasks app instead, and this change follows it. If the product owner wants to revisit, the calendar page of Decision 1 is unaffected.

## Risks / trade-offs

- [A task assigned to many people fans out to many lists] -> One write per opted-in assignee; users who did not opt in cost nothing.
- [Backfill for a user with thousands of tasks] -> A queued background job, paged through `fetchEvery`-style reads, never the settings request itself.
- [Drift after a failed write] -> Logged; the next change to the task rewrites the VTODO in full.

## Task 2.2 outcome: the write path (decided at build, 30 Sep)

Checked against Nextcloud server master (`apps/dav/lib/CalDAV/CalDavBackend.php`) and OCP 34:
- `OCP\Calendar\IManager` with a writable calendar's `ICreateFromString::createFromString()` creates an object, but it is a Sabre create that refuses a URI already present, so it cannot replace a VTODO. OCP has no call to delete a calendar object and none to create a calendar.
- `OCA\DAV\CalDAV\CalDavBackend` has all seven calls the export needs: `getCalendarByUri`, `createCalendar` (with `components => 'VTODO'`), `deleteCalendar`, `getCalendarObject`, `createCalendarObject`, `updateCalendarObject`, `deleteCalendarObject`. It is not a public API.

Decision: `TaskCalendarExportService` resolves the backend by class name behind `class_exists()` and a probe of those seven methods, from the container, the way hermiq's task tools resolve it (`OCA\Hermiq\Service\NcNative\TaskLists::davBackend()`). When it is absent or has drifted, the export reports `caldavAvailable: false`, the personal settings show the switch disabled with the reason, and the listener writes nothing. Every backend call sits in that one class, so moving to a public API later touches one file.

Also decided at build:
- The switch lives in `TaskCalendarExportService` (`values()`, `apply()`), applied by `SettingsController` next to `NotificationSwitchService`, not in `SettingsService`, which is at phpmd's complexity and coupling limits.
- The VTODO text is built by a separate pure class, `TaskVtodoBuilder`, so every mapping is tested without Nextcloud.
- The backfill job reads every task as the system and keeps the user's own (assigned or shared), because OpenRegister filters a list property such as `sharedWith` by equality, not membership. This is the same read the My tasks page makes.
- The object in the list is named `planninq-task-<task uuid>.ics`, so removal needs no UID lookup. The UID write-back is a silent system write (`SystemOperationContext`, `silent: true`), and the listener also ignores an update that changes only `calendarEventUid`.

## Open questions

- (Answered above, Task 2.2 outcome.) Which write path does `TaskCalendarExportService` use? Nextcloud's public calendar API (`OCP\Calendar\IManager` with a writable calendar's create-from-string call) covers creating a VTODO; updating and deleting an existing object, and creating the "Planninq" list itself, may need the DAV app's CalDAV backend. The implementer confirms which calls the supported Nextcloud versions offer before task 2.2 starts; the rest of the design does not depend on the answer.
