---
kind: code
---

# Reschedule tasks on the timeline, move dependent work along, and plan in working days

## Why

A project member can look at the timeline but cannot change anything on it. `ProjectTimeline` says so in its own doc comment: "It is strictly read-only: it fetches once via the stateless timeline API and never creates or mutates an object" (`src/views/ProjectTimeline.vue:140-150`). The bars are plain `div`s with no drag, pointer or keyboard handlers (`src/views/ProjectTimeline.vue:97-104`). To move a task a week, the member leaves the timeline and edits dates elsewhere, and every task that waits on it stays where it was.

Nothing knows about working days either. The timeline shades Saturday and Sunday (`src/views/ProjectTimeline.vue:56` and `:312`), and that is cosmetic: no calculation uses it, and there is no list of public holidays anywhere. A task dragged to start on Easter Monday would start on Easter Monday.

The shipped capability `gantt-timeline-view` makes the read-only shape a requirement: the timeline "MUST NOT introduce a new schema, new storage, or a scheduling engine" (`openspec/specs/gantt-timeline-view/spec.md:12-13`). This change lifts the "scheduling engine" part of that sentence on purpose and keeps the rest.

Deck, OpenProject, Plane and Jira let a user drag a bar to change its dates. OpenProject and Jira move dependent work when the work before it slips. OpenProject plans against administered working days and holidays, and the four timetabling products in the matrix (Zermelo, Untis, Xedule, TimeEdit) all keep a holiday calendar.

Parity rows: `pln-gantt-edit`, `pln-auto-schedule`, `pln-working-days` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because four competitors let a user reschedule on the chart, two move dependent work automatically and five plan against working days and holidays, while planninq's timeline can only be looked at.

## What changes

- A project member can drag a task bar to move it, or drag either end to change its start or due date. The same change is possible from the keyboard.
- A project owner can switch on "Move dependent tasks along" for a project. A task that slips then pushes the tasks it blocks, after the member sees and confirms which tasks move.
- An admin maintains the working week and a list of non-working days in Beheer. Dates set on the timeline land on working days, durations count working days, and the timeline shades every non-working day.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `pln-gantt-edit`, `pln-auto-schedule`, `pln-working-days`.

### `pln-gantt-edit`: Reschedule a task by dragging its bar on the timeline.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectTimeline.vue:140-150 doc comment states 'It is strictly read-only: it fetches once ... and never creates or mutates an object'; no drag handlers on .project-timeline__bar"
- Note: "the timeline is explicitly read-only by design; bars have no drag/mouse handlers at all."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "source: src/components/board/GanttView.vue:275 dragging a bar calls updateTaskDate, :373-377 dispatches updateCardDates with the new startdate and duedate ; not in the round-4 drive (menu-tree.md) ; source read at v1.19.0: src/components/board/GanttView.vue:369 bars editable when the user can edit, :371-372 on_date_change keeps the drag, :276-279 commits it through updateTaskDate, :379-384 dispatches updateCardDates with the new startdate and duedate; lib/Controller/CardController.php:67 update"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/gantt-chart/README.md (gantt-chart.gif, create-new-element-gantt-chart.gif) drag bars to change dates; corpus: openproject/round4/journeys.md duration derivation on edit (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-table/timeline/cells/wp-timeline-cell-mouse-handler.ts:63-97 mousedown on a bar starts a drag that shifts or resizes the dates; the change is saved through the work package update service"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source: apps/web/core/components/gantt-chart/helpers/draggable.tsx LeftResizable, RightResizable and onMouseDown handleBlockDrag(e, "move"); chart/timeline-drag-helper.tsx ; corpus: plane/round4/menu-tree.md gantt layout ; source read at v1.4.2: apps/web/core/components/gantt-chart/helpers/draggable.tsx:15-16,34 LeftResizable, RightResizable and handleBlockDrag move; apps/web/core/components/gantt-chart/chart/main-content.tsx:40 blockUpdateHandler; apps/api/plane/app/views/issue/base.py:628 partial_update stores start_date and target_date"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'adjust the dates by clicking and dragging the ends of the schedule bar' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'adjust the dates by clicking and dragging the ends of the schedule bar' (read 2026-09-26)"

### `pln-auto-schedule`: Have dependent tasks move automatically when the task before them slips.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no code searched (rescheduling, cascade, slip) in src/ or lib/; ProjectTimeline.vue is read-only and dependency edges are display-only"
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/gantt-chart/scheduling/README.md:15 'there is an automatic scheduling mode and a manual scheduling mode (default) (new in release 15.4) ... determine how work packages behave when the dates of related work packages change'; corpus: openproject/round4/M1-column.md 8.9 set_schedule_service.rb (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/work_packages/date_picker/dialog_content_component.html.erb:10-16 manual or automatic scheduling in the date picker; app/contracts/work_packages/base_contract.rb:116 schedule_manually; app/services/work_packages/set_schedule_service.rb:31-41 ... (shortened; full text in the matrix row)"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/auto-schedule-issues-in-advanced-roadmaps-1044784172.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/auto-schedule-issues-in-advanced-roadmaps-1044784172.html 'The Auto-scheduler constructs a plan using issue details based on your plan settings', considering dependencies, capacity and estimates ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/auto-schedule-issues-in-advanced-roadmaps-1044784172.html 'The Auto-scheduler constructs a plan using issue details based on your plan settings'; it considers 'dependencies' and 'issue rankings' (read 2026-09-26)"

### `pln-working-days`: Plan against working days and public holidays rather than calendar days.

- Area `planning`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectTimeline.vue:56 only visually shades weekend columns (project-timeline__tick--weekend); there is no scheduling logic at all (the timeline is read-only) and no holiday calendar concept anywhere"
- Note: "weekend shading is cosmetic only, not used in any scheduling calculation."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/journeys.md 'The derived date cannot land on a weekend or an administered holiday: ... 0 non-working results in 1675 computations'; /admin/settings/working_days_and_hours ; source read at v17.8.0: config/routes.rb:885 admin working days and hours settings; app/models/non_working_day.rb holidays; app/services/work_packages/shared/days.rb:38 duration uses WorkingDays unless ignore_non_working_days"
  - Zermelo (Desktop, Portal and WebApp) (https://support.zermelo.nl/guides/roostermaker/tijdvakken-en-vakanties-aanmaken, https://docs.zportal.nl/docs/print.html): "docs read 2026-09-27: https://support.zermelo.nl/guides/roostermaker/tijdvakken-en-vakanties-aanmaken 'Een tijdvak bestaat uit een aantal weken van ieder vijf dagen. Indien er vrije dagen in deze weken zijn, regelt u dat in het tabblad Vakanties' ; docs read 2026-09-27: https://docs.zportal.nl/docs/print.html endpoint '/holidays (Vakanties)'"
  - Untis, WebUntis and Untis Mobile (https://www.untis.at/produkte/untis-express, https://untisroostersoftware.eu/untishome/, https://help.untis.at/hc/de/articles/360016423100): "docs read 2026-09-27: https://www.untis.at/produkte/untis-express lists 'Zeitraster, Ferien festlegen' ; https://untisroostersoftware.eu/untishome/ module Jaarplanning: 'Doordat je op voorhand rekening houdt met absenties, worden de jaaruren verdeeld over de daadwerkelijk beschikbare weken' ; https://help.untis.at/hc/de/articles/360016423100 exam calendar marks 'Sonn- und Feiertage' and 'Ferien'"
  - Xedule (https://support.xedule.nl/hc/nl/articles/35791217266834-Het-uitroosteren-van-een-gehele-dag-i-v-m-een-activiteit-of-studiedag, https://developer.connect.xedule.nl/developer/apis?api-version=2022-04-01-preview): "docs read 2026-09-27: https://support.xedule.nl/hc/nl/articles/35791217266834-Het-uitroosteren-van-een-gehele-dag-i-v-m-een-activiteit-of-studiedag ; public API has Vakantie (holiday) resources in calendar-xedule, yearplan and students-groups (https://developer.connect.xedule.nl/developer/apis?api-version=2022-04-01-preview)"
  - TimeEdit (https://developer.timeedit.com/reference/get_holidays, https://www.academy.timeedit.com/release-notes): "docs read 2026-09-27: https://developer.timeedit.com/reference/get_holidays 'LIST Holidays ... Region from GET /holidays/regions' ; https://www.academy.timeedit.com/release-notes 'Don't allow bookings on holidays' setting in Preferences"


## Scope

### In scope

- Drag to move and resize bars on `ProjectTimeline`, plus a keyboard and dialog path to the same change.
- Writing the new dates to the existing task through the OpenRegister object API.
- A per-project "Move dependent tasks along" switch, and a preview of every task that would move before anything is written.
- An app-wide working calendar (working weekdays and non-working days) in the admin settings, readable by every user.
- The dependency `type` in the timeline payload, so only `blocks` edges move work.

### Out of scope

- Pulling successors earlier when a task finishes early. Moves only go later, so a member's manual plan is never shortened behind their back.
- Resource levelling and capacity-aware scheduling (Jira's auto-scheduler weighs capacity; planninq does not).
- Per-person calendars and personal leave.
- Creating tasks or dependencies by drawing on the chart.

## Impact

- Capability `gantt-timeline-view`: one requirement modified (the read-only sentence), new requirements added.
- Schema: new `project.autoSchedule` boolean.
- Admin settings: new keys `working_weekdays` and `non_working_days` in `lib/Service/SettingsService.php` and a new section in `src/views/settings/Settings.vue`.
- Controller: `lib/Controller/TimelineController.php` adds each edge's `type` to its read payload. It stays read-only.
- View and helpers: `src/views/ProjectTimeline.vue`, a new `src/utils/workingCalendar.js` and `src/utils/scheduling.js`.
- Dialogs: `src/dialogs/TaskDatesDialog.vue`, `src/dialogs/RescheduleDialog.vue`.
- Store: `updateTask` in `src/store/projects.js` is the write path.
- Depends on: `planning-dependencies-on-task-page` (lane A) for members to create the `blocks` edges that auto-scheduling follows; editing dates works without it.

## Risks

### Risk 1: a cascade half-writes
**Severity**: Medium
**Mitigation**: the member confirms a preview first. Writes go one task at a time in dependency order, and a failure stops the run and names what moved and what did not. The timeline reloads from the server afterwards, so the screen never shows dates that were not saved.

### Risk 2: the read-only guarantee is lost by accident
**Severity**: Medium
**Mitigation**: the timeline endpoint stays a GET that writes nothing, and the modified requirement says so. All writes go through the object API with the member's own rights, the same PATCH the board uses (`src/store/projects.js:871-890`).

### Risk 3: holidays differ per organisation
**Severity**: Low
**Mitigation**: the list is admin-maintained per instance, not hard-coded. The admin screen can fill in the Dutch national holidays for a chosen year as a starting point, and every entry can be removed.
