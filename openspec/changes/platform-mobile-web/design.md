# Design: work on tasks from a phone browser

## Context

What exists at de35541:

- There is no mobile client and no phone layout. `@media` rules: `src/views/ProjectBoard.vue:788` and `src/components/ProjectListItem.vue:171` (both `prefers-reduced-motion`), `src/components/DashboardPanels.vue:147` (`max-width: 900px`).
- The board is a flex row of columns, `flex: 1 0 240px` with `min-width: 240px`, in an `overflow-x: auto` container (`src/views/ProjectBoard.vue:698-712`). Cards are `draggable="true"` with HTML drag handlers (`:130-140`). Each card also has a "Move task to another column" `NcActions` menu, the keyboard equivalent of dragging (`:142-165`), which works with a tap as well.
- The task page combines the task form and time log with a `CnObjectSidebar` (`src/views/TaskDetail.vue:124-137`); `NcAppSidebar` already opens full screen on narrow viewports in Nextcloud.
- `TimeEntryDialog` uses `NcDialog` and a native date input (`src/dialogs/TimeEntryDialog.vue:1-40`); the Timesheet groups entries per day in a week grid (`src/views/Timesheet.vue`).
- Playwright runs a single `chromium` project with `devices['Desktop Chrome']` (`tests/e2e/playwright.config.ts:96-106`).
- Nextcloud's Android and iOS apps show Files, Talk and a few core screens; third-party app screens open in the browser.

## Goals / non-goals

Goals:
- The daily flows usable at 360 CSS pixels with touch only.
- A test that fails when that breaks.

Non-goals:
- A native app, offline use, phone layouts for timeline, roadmap, reports and admin pages.

## Decisions

### Decision 1: the phone flows are named, and only those get a phone layout
My tasks, the task page (details, comments, attachments, log time, timer), the board with card moves, the time entry dialog, the running timer and the timesheet. These are the ADR-001 medewerker flows (Mijn werk and Borden). Planning and PMO pages keep their layouts; on a phone they scroll, and that is stated rather than half-fixed.

### Decision 2: breakpoints from Nextcloud, no new tokens
Layouts switch at `max-width: 600px` for the phone layout and follow Nextcloud's own navigation collapse. Sizes and colours stay NL Design and Nextcloud tokens (no hard-coded colours). Every interactive element on these flows is at least 44 by 44 CSS pixels, above the 24 by 24 of WCAG 2.2 success criterion 2.5.8, and no action depends on hover.

### Decision 3: the phone board shows one column at a time
Under 600 pixels the board shows one column full width, with a column switcher above it: a horizontal row of buttons with each column's name and card count, the current one pressed (`aria-pressed`). Swiping between columns uses CSS scroll snap on the existing scroller, so the switcher and the swipe agree. Moving a card is the card's move menu, which is already the accessible path; the drag handlers stay for mouse users and are not relied on for touch. Lane A's `boards-configurable-columns` decides which columns exist; this change only lays them out.

### Decision 4: lists instead of grids on the timesheet
Under 600 pixels the Timesheet shows each day as a list of entries with their task, duration and work type, and the week total at the top, instead of the week grid. Editing opens the same `TimeEntryDialog`, full screen.

### Decision 5: a phone smoke suite on every pull request
`tests/e2e/playwright.config.ts` gains two projects, `phone-android` (`devices['Pixel 7']`) and `phone-ios` (`devices['iPhone 14']`), that run only `tests/e2e/mobile.spec.ts`. The suite walks the named flows by tapping, asserts that `document.documentElement.scrollWidth` does not exceed the viewport on every page except inside the board scroller, and checks the tap-target size of the flows' buttons.

## Risks / trade-offs

- [Desktop regressions on phones] -> The phone projects run on every pull request.
- [The one-column board] -> The switcher shows every column's count; wide screens are unchanged.

## Open questions

- Can a user add planninq to their phone's home screen through the web app manifest that Nextcloud's theming app serves per app, so it opens straight on My tasks? If it can, the mobile suite gains a check of the manifest's start URL; if not, it is left out, because planninq does not ship a manifest of its own.
