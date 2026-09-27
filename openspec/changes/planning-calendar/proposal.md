---
kind: code
---

# See tasks in a calendar, and export them one way to Nextcloud Tasks

## Why

A project member cannot see their tasks laid out by due date on a calendar, in planninq or in the Nextcloud Calendar and Tasks apps. `grep -rni calendar src lib` finds only icon names. The task schema has had a `calendarEventUid` field for this since the start (`lib/Settings/planninq_register.json:311-316`, "UID of the linked NC Tasks / CalDAV VTODO event"), and nothing ever writes it. `docs/ARCHITECTURE.md` lists "CalDAV sync: `calendarEventUid` stores VTODO UID" in the task entity table and names `OCP\Calendar\IManager` as the reuse point, and `docs/FEATURES.md` lists "CalDAV/VTODO export (sync to Nextcloud Tasks app)" as a V1 feature. None of it is built.

The design question was settled before this pass: `docs/ARCHITECTURE.md` section 5, question 1, "One-way export to Nextcloud Tasks app in V1. The `calendarEventUid` field on Task stores the VTODO UID. Planninq writes tasks to CalDAV; changes made in the Tasks app are not synced back. Two-way sync was rejected due to data model mismatch." This change follows that answer. Writing back from the Tasks app is the separate row `pln-calendar-writeback`, recorded as decided-no, and is not specced here.

OpenProject and Plane show work items on a calendar, and the three timetabling products in the matrix have day, week and month views. Deck publishes every card with a due date as a VTODO that CalDAV clients can read, and OpenProject offers an iCalendar subscription per calendar.

Parity rows: `pln-calendar-view`, `pln-caldav` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because five competitors show tasks on a calendar and two publish them to CalDAV clients, while planninq has neither and its own architecture document already chose one-way VTODO export.

## What changes

- A project member can open a month or week calendar of a project's tasks by due date, with a list equivalent.
- Every user gets a "My calendar" of the tasks assigned to them across projects.
- A user can switch on "Show my tasks in Nextcloud Tasks". Their assigned tasks then appear as VTODOs in a "Planninq" task list in their own Nextcloud calendar home, and stay in step with planninq.
- Changes made to those VTODOs in the Tasks app or another CalDAV client are not read back. The next change in planninq overwrites them, and the VTODO says so.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `pln-calendar-view`, `pln-caldav`.

### `pln-calendar-view`: See tasks with due dates in a calendar view.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no calendar-view component found; grep for 'calendar' across src/ and lib/ returns only icon names"
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md project 'Calendars'; docs: opf/openproject HEAD 27a58131 docs/user-guide/calendar/README.md 'How to create, use and subscribe to a calendar' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/calendar/config/routes.rb:2-6 project calendars; modules/calendar/lib/open_project/calendar/engine.rb:29 calendar_view module; frontend/src/app/features/calendar/calendar-entry.component.ts the calendar page"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md 'calendar ... layouts' ; code-census.md §12 'There is a calendar layout for issues in the web app' ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/roots/project-layout-root.tsx:22,34-35 CALENDAR renders CalendarLayout; apps/web/core/components/issues/issue-layouts/calendar/base-calendar-root.tsx:100 groups by target_date; apps/api/plane/db/models/issue.py:148 target_date"
  - Zermelo (Desktop, Portal and WebApp) (https://support.zermelo.nl/guides/leerling-ouder/je-rooster-bekijken, https://support.zermelo.nl/guides/leerling-ouder/alle-roosters-bekijken): "docs read 2026-09-27: https://support.zermelo.nl/guides/leerling-ouder/je-rooster-bekijken 'Je ziet het rooster van een dag of een week in één oogopslag' ; docs read 2026-09-27: https://support.zermelo.nl/guides/leerling-ouder/alle-roosters-bekijken week timetables per teacher, student or room"
  - Xedule (https://support.xedule.nl/hc/nl/articles/36237746145170-Toelichting-gebruikte-iconen-in-My-Xedule): "docs read 2026-09-27: https://support.xedule.nl/hc/nl/articles/36237746145170-Toelichting-gebruikte-iconen-in-My-Xedule rooster views 'timeline', 'dag', 'werkweek', 'week', 'maand', 'lijst' (for timetable activities rather than tasks)"
  - TimeEdit (https://www.academy.timeedit.com/products/core, https://timeedit.com/platform/scheduling/viewer): "docs read 2026-09-27: https://www.academy.timeedit.com/products/core 'Adjust your scheduling views to represent how you prefer to look at the scheduling, whether by time, day or week' ; https://timeedit.com/platform/scheduling/viewer 'Graphical and list views' (timetable reservations, not tasks)"

### `pln-caldav`: See task due dates in the Nextcloud Calendar or another CalDAV client.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "grep for 'caldav' across src/ and lib/ returns nothing"
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'every card with a due date a VTODO in it ... Verified live with a REPORT on the board's calendar' ; open-core.md caveat: API-set time zones republished as UTC ; source read at v1.19.0: appinfo/info.xml:93 lib/DAV/CalendarPlugin.php platform CalDAV plugin; lib/DAV/Calendar.php:207 VTODO calendar per board; lib/Db/Card.php:138-160 DUE, DTSTART and STATUS from the card; lib/DAV/CalendarObject.php:58-59 objects are read-only to CalDAV clients; src/components/DeckAppSettings.vue:22-23 per-user switch"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/calendar/README.md 'Subscribe to a calendar: OpenProject allows you to subscribe to and access any of your calendars using an external client that supports the iCalendar format'; corpus: openproject/round4/menu-tree.md admin 'Calendar subscriptions' (iCal feed, read-only, counts as the generic capability) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/calendar/config/routes.rb:17 per calendar '/ical' feed; modules/calendar/app/controllers/calendar/ical_controller.rb:35 show; config/locales/js-en.yml:396 'use the URL (iCalendar) to subscribe to this calendar in an external client'; ... (shortened; full text in the matrix row)"


## Scope

### In scope

- A calendar component with month and week views and a list view, used on a project page and on a personal page.
- A per-user opt-in to export assigned tasks as VTODOs to a "Planninq" task list, kept in step on create, update, reassignment, completion and delete.
- Writing `calendarEventUid` on the task.

### Out of scope

- Reading changes back from CalDAV (row `pln-calendar-writeback`, decided no in `docs/ARCHITECTURE.md` section 5 question 1).
- Dragging a task to another day on the calendar. Dates are changed on the task or on the timeline (`planning-timeline-editing`).
- Exporting tasks that are not assigned to the user, or a whole project, to CalDAV.
- A public iCalendar feed URL. Anything outside Nextcloud reads the Planninq list through the user's own CalDAV account.

## Impact

- Components and views: a new `src/components/TaskCalendar.vue`, a project calendar page `ProjectCalendar` at `/projects/:id/calendar`, a personal page `MyCalendar` at `/my-calendar`, manifest and `src/registry.js` entries, a Calendar button in the `ProjectBoard` header.
- Backend: a new `lib/Listener/TaskCalendarExportListener.php` on OpenRegister's object events and a new `lib/Service/TaskCalendarExportService.php` that builds and writes the VTODO through Nextcloud's calendar layer.
- Settings: a new per-user key `export_tasks_to_caldav` (default off) in `lib/Service/SettingsService.php` and a switch in `src/views/settings/UserSettings.vue`.
- Schema: none. `calendarEventUid` already exists.
- Depends on: `portfolio-my-work-dashboard` (lane A) for the Mijn werk surface the personal calendar hangs under; `tasks-assignment-priority-labels` (lane A) if it changes `assignedTo` to hold several people, in which case the export goes to each of them.

## Risks

### Risk 1: the VTODO drifts from the task
**Severity**: Medium
**Mitigation**: planninq is the only writer that counts. Every task change rewrites the VTODO in full, the VTODO's description says "Managed by Planninq. Changes made here are replaced by the next change in Planninq.", and its URL property links back to the task.

### Risk 2: the OCP calendar API covers create but not every update path
**Severity**: Medium
**Mitigation**: the open question in design.md names the choice. The service keeps every write behind one class, so switching between the OCP interface and the DAV backend touches one file.

### Risk 3: an export failure breaks a task save
**Severity**: Medium
**Mitigation**: the listener catches and logs every export error and never throws back into OpenRegister's save, the same rule `TaskActivityListener` follows. The task is always saved; only the copy lags.
