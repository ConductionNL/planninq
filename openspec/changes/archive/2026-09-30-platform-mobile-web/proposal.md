---
kind: code
---

# Work on tasks from a phone browser

## Why

A person away from their desk cannot work in planninq on a phone. Nextcloud's own Android and iOS apps do not show third-party app screens, so there is no planninq screen in them, and planninq has no client of its own. The one path left is the web UI in a phone browser, and nobody has built or tested for it: only three components carry a `@media` rule, and two of those are `prefers-reduced-motion` (`src/views/ProjectBoard.vue:788`, `src/components/ProjectListItem.vue:171`); the only width rule is in `src/components/DashboardPanels.vue:147`. Board columns keep a 240 pixel minimum in a horizontal scroller (`src/views/ProjectBoard.vue:698-712`), cards move by HTML drag events that most phone browsers do not send for touch (`:134-138`), and the Playwright suite runs one desktop Chrome project only (`tests/e2e/playwright.config.ts:96-106`).

Deck has third-party Android and iOS clients, Jira has mobile apps with push notifications, and Untis has Untis Mobile.

Parity rows: `int-mobile` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because three competitors can be used from a phone, and for planninq the phone browser is the only way there.

This change extends the flat spec `openspec/specs/app-shell-and-data-store.md` through a new capability, `mobile-web`.

## What changes

- The daily flows work in a phone browser at 360 pixels wide: My tasks, a task's page, moving a card, logging time and the timer, and the timesheet.
- The board shows one column at a time on a phone, with a column switcher, and moving a card uses the card's move menu, which works with a finger.
- Touch targets meet WCAG 2.2 AA, nothing needs hover, and no page scrolls sideways except the board's own columns.
- A phone project in the Playwright suite keeps it that way.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `int-mobile`.

### `int-mobile`: Work on tasks from a mobile app.

- Area `integration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no mobile client, PWA manifest or mobile API in the repo; only three components carry a @media rule (src/views/ProjectBoard.vue, src/components/DashboardPanels.vue, src/components/ProjectListItem.vue)"
- Note: "Nextcloud's mobile apps do not surface third-party app screens, so there is no app to use; a phone browser is untested territory."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "source: README.md:24-27 'Nextcloud Deck app for Android' (F-Droid, Google Play) and 'Nextcloud Deck app for iOS' (App Store), third-party clients on the REST API ; not in the round-4 drive ; source read at v1.19.0: README.md:24-27 'Nextcloud Deck app for Android' (F-Droid, Google Play) and 'Nextcloud Deck app for iOS' (App Store), third-party clients built on the REST API at appinfo/routes.php:102-114; not Deck's own code"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/push-notifications-993923359.html, https://confluence.atlassian.com/jirasoftware/jira-software-11-2-x-release-notes-1653834634.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/push-notifications-993923359.html push notifications 'on iOS and Android devices' for the Jira Data Center mobile app (corpus: jira-data-center/round4/M1-column.md 6.8 'the mobile app has one') ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/push-notifications-993923359.html push for the 'Jira Data Center mobile app'; https://confluence.atlassian.com/jirasoftware/jira-software-11-2-x-release-notes-1653834634.html 'we're removing the Jira Mobile plugin in favor of the Jira Data Center mobile app for iOS and Android' (read 2026-09-26)"
  - Untis, WebUntis and Untis Mobile (https://www.untis.at/produkte/webuntis/untis-mobile-app, https://apps.apple.com/nl/app/untis-mobile/id926186904, https://play.google.com/store/apps/details?id=com.grupet.web.app): "docs read 2026-09-27: https://www.untis.at/produkte/webuntis/untis-mobile-app 'Die Untis Mobile App ist im Appstore und Google Play Store kostenfrei verfügbar' with timetable, messages, class register, room changes ; https://apps.apple.com/nl/app/untis-mobile/id926186904 and https://play.google.com/store/apps/details?id=com.grupet.web.app list Untis Mobile by Untis GmbH"


## Scope

### In scope

- Layout and interaction changes for phone widths in My tasks, the task page, the board, the time entry dialog, the running timer and the timesheet.
- Two Playwright device projects (an Android and an iPhone profile) running a phone smoke suite on every pull request.

### Out of scope

- A native or third-party app.
- Offline use.
- The timeline, the roadmap, reports and admin settings on a phone. They remain usable by scrolling, but are laid out for larger screens.
- Push notifications, which reach phones through the Nextcloud app once `collaboration-notifications` declares them.

## Impact

- Views and components: `src/views/ProjectBoard.vue`, `src/views/TaskDetail.vue`, `src/views/Timesheet.vue`, the My tasks page of `portfolio-my-work-dashboard`, `src/dialogs/TimeEntryDialog.vue`, `src/components/TaskCard.vue`, and `RunningTimer` from `time-timer-and-work-type`.
- Tests: `tests/e2e/playwright.config.ts` gains phone projects; a new `tests/e2e/mobile.spec.ts`.
- Schema and backend: none.
- Depends on: `portfolio-my-work-dashboard` (My tasks) and `boards-configurable-columns` (the columns the phone board switches between).

## Risks

### Risk 1: a desktop change silently breaks the phone layout
**Severity**: Medium
**Mitigation**: the phone smoke suite runs on every pull request in two device profiles and fails on any sideways page scroll or on a core action it cannot reach.

### Risk 2: the one-column board hides the flow
**Severity**: Low
**Mitigation**: the column switcher shows every column's name and card count, so the whole board's state is one glance away, and wider screens keep the full board.
