---
kind: code
---

# Switch between the board and a list of the same tasks

## Why

The only planninq-owned parity row still marked `building` is `brd-list-toggle`, "Switch between
the board and a list of the same tasks", and it named no change. Its built evidence pointed at the
backlog placeholder, which `backlog-list` (archived 2026-09-28) turned into a list of the tasks in
no column: that is the backlog, not the board's own cards. So the row is still not built, and a
`building` row with no change is a claim nobody backs.

Decision: build. The row is in the boards area (a core area of the product) and four competitors
rate it yes.

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq. Row: `brd-list-toggle`
(origin own-code).
- OpenProject yes: "Work packages list, plus Gantt, board and calendar views" over the same work
  packages (corpus openproject/round4/menu-tree.md).
- Plane yes: "spreadsheet, board, calendar, gantt and list layouts"
  (apps/web/core/components/issues/issue-layouts/filters/header/layout-selection.tsx:24-36 at v1.4.2).
- Kanboard yes: "Overview · Board · List" with the same search (app/Template/project_header/views.php:12
  and :18 at v1.2.54).
- Jira yes: a board is based on a saved filter, and the same filter opens as a list in the issue
  navigator (https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html).
- Nextcloud Deck partial: Kanban and Gantt view modes only (src/components/Controls.vue).

## What changes

- The board page gets a Board and List switch. The list shows the same cards the board shows,
  with the same label filter, one row per card with its column, priority and due date, in lane
  order and then card order.
- The choice lives in the query string, so a link opens the same view.

## Impact

- `src/views/ProjectBoard.vue`, `src/utils/columnHelpers.js`, catalogues.
- No schema or server change.
